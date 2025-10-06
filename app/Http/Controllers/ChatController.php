<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class ChatController extends Controller
{
    /**
     * Récupérer la liste des conversations de l'utilisateur connecté
     */
   public function conversations(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        // Récupérer les conversations existantes
        $conversations = Message::where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->selectRaw('LEAST(sender_id, receiver_id) as user1, GREATEST(sender_id, receiver_id) as user2')
            ->groupBy('user1', 'user2')
            ->get()
            ->map(function ($message) use ($user) {
                $otherUserId = $message->user1 == $user->id ? $message->user2 : $message->user1;
                $otherUser = User::with('commercant')->find($otherUserId);

                // Récupérer le dernier message de la conversation
                $lastMessage = Message::where(function ($q) use ($user, $otherUserId) {
                    $q->where('sender_id', $user->id)->where('receiver_id', $otherUserId);
                })->orWhere(function ($q) use ($user, $otherUserId) {
                    $q->where('sender_id', $otherUserId)->where('receiver_id', $user->id);
                })->latest()->first();

                // Calculer le nombre de messages non lus
                $unreadCount = Message::where('receiver_id', $user->id)
                    ->where('sender_id', $otherUserId)
                    ->where('is_read', false)
                    ->count();
$lastMessageType = $lastMessage->type ?? 'text';
                return [
                    'user_id' => $otherUserId,
                    'name' => $otherUser ? $otherUser->nom : 'Inconnu',
                    'last_message' => $lastMessage->content ?? '',
                    'last_message_type  ' => $lastMessageType ?? $lastMessage->content,
                    'updated_at' => $lastMessage->updated_at ?? now(),
                    'unread_count' => $unreadCount,
                    'is_commercant' => $otherUser->commercant ? true : false,
                    'profile_photo' => $otherUser->photo, // Assurez-vous que photo_url existe dans User
                ];
            })
            ->sortByDesc(function ($conversation) {
                return $conversation['updated_at'];
            })
            ->values();

        // Ajouter la conversation avec le service client (ID 3)
        $serviceClientId = 3;
        $isServiceClientConversation = $conversations->firstWhere('user_id', $serviceClientId) === null;

        if ($isServiceClientConversation) {
            $serviceClient = User::with('commercant')->find($serviceClientId);

            // Récupérer le dernier message avec le service client
            $lastMessageWithService = Message::where(function ($q) use ($user, $serviceClientId) {
                $q->where('sender_id', $user->id)->where('receiver_id', $serviceClientId);
            })->orWhere(function ($q) use ($user, $serviceClientId) {
                $q->where('sender_id', $serviceClientId)->where('receiver_id', $user->id);
            })->latest()->first();

            // Calculer le nombre de messages non lus avec le service client
            $unreadCountWithService = Message::where('receiver_id', $user->id)
                ->where('sender_id', $serviceClientId)
                ->where('is_read', false)
                ->count();

            $serviceClientConversation = [
                'user_id' => $serviceClientId,
                'name' => $serviceClient ? $serviceClient->nom : 'Service Client',
                'last_message' => $lastMessageWithService->content ?? 'ecrivez moi pour tout besoin',
                'last_message_type' => $lastMessageWithService ? $lastMessageWithService->type : 'text',
                'updated_at' => $lastMessageWithService->updated_at ?? now(),
                'unread_count' => $unreadCountWithService,
                'is_commercant' => $serviceClient->commercant ? true : false,
                'profile_photo' => $serviceClient->photo ?? null, // Image par défaut si absente
            ];

            $conversations->push($serviceClientConversation);
        }

        // Trier à nouveau après avoir ajouté la conversation du service client
        $conversations = $conversations->sortByDesc(function ($conversation) {
            return $conversation['updated_at'];
        })->values();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Récupérer les messages d'une conversation spécifique
     */
    public function index($receiverId, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $offset = $request->query('offset', 0);
        $limit = 30; // Limite à 30 messages

        // Récupérer les 30 derniers messages dans l'ordre décroissant, puis les trier en ordre ascendant
        $messages = Message::where(function ($query) use ($user, $receiverId) {
            $query->where('sender_id', $user->id)->where('receiver_id', $receiverId)
                ->orWhere('sender_id', $receiverId)->where('receiver_id', $user->id);
        })
            ->with(['sender', 'receiver', 'product'])
            ->latest('created_at') // Trier par created_at desc pour obtenir les derniers messages
            ->skip($offset)
            ->take($limit + 1) // Prendre un message supplémentaire pour vérifier hasMore
            ->get();

        $hasMore = $messages->count() > $limit; // Vérifier s'il y a plus de messages
        $messages = $messages->take($limit)->sortBy('created_at'); // Limiter à 30 et trier par created_at asc

        return response()->json([
            'messages' => $messages->values(), // Réindexer la collection
            'hasMore' => $hasMore,
            'user' => User::find($receiverId),
        ]);
    }

    /**
     * Envoyer un nouveau message
     */
    public function store(Request $request, $receiverId)
{
    $user = $request->user();
    if (!$user) return response()->json(['message' => 'Utilisateur non authentifié'], 401);

    $receiver = User::find($receiverId);
    if (!$receiver) return response()->json(['message' => 'Destinataire non trouvé'], 404);

    $validated = $request->validate([
        'type' => 'nullable|string|in:text,audio,image',
        'content' => 'nullable|string|max:1000',
        'audio' => 'nullable|file|mimes:mp3,wav,ogg,webm|max:10240',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
        'product_id' => 'nullable|exists:produits,id',
    ]);

    $message = new Message();
    $message->sender_id = $user->id;
    $message->receiver_id = $receiverId;
    $message->product_id = $validated['product_id'] ?? null;
    $message->type = $validated['type'] ?? 'text';

    // 🔹 Gestion des types
    if ($request->hasFile('audio')) {
        $file = $request->file('audio');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = public_path('storage/messages/audio/' . $filename);
        $file->move(public_path('storage/messages/audio'), $filename);
        $message->content = asset('storage/messages/audio/' . $filename); // URL directe
        $message->type = 'audio';
    } elseif ($request->hasFile('image')) {
        $file = $request->file('image');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = public_path('storage/messages/images/' . $filename);
        $file->move(public_path('storage/messages/images'), $filename);
        $message->content = asset('storage/messages/images/' . $filename); // URL directe
        $message->type = 'image';
    } else {
        $message->content = $validated['content'] ?? '';
    }

    $message->save();
    $message->load('sender', 'receiver', 'product');

    $unreadMessages = Message::where('receiver_id', $receiverId)
        ->where('is_read', false)
        ->count();

    try {
        broadcast(new MessageSent($message, $user, $receiver, $unreadMessages))->toOthers();
        Log::info('MessageSent diffusé', ['message_id' => $message->id]);
    } catch (\Exception $e) {
        Log::error('Diffusion échouée : ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'Message envoyé avec succès',
        'data' => $message,
    ], 201);
}
    
    public function markAllAsRead(Request $request)
    {
       
        $user = $request->user();
        Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
        $unreadMessagesCount = Message::where('receiver_id', $user->id)->where('is_read', false)->count();
        return response()->json(['message' => 'Tous les messages marqués comme lus', 'unread_messages' => $unreadMessagesCount]);
    }




    /**
     * Éditer un message existant
     */
    public function update(Request $request, $messageId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $message = Message::find($messageId);
        if (!$message || $message->sender_id !== $user->id ) {
            return response()->json(['message' => 'Message non trouvé ou non autorisé'.$messageId], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $message->content = $validated['content'];
        $message->updated_at = now();
        $message->save();

        // Charger les relations pour la réponse
        $message->load('sender', 'receiver', 'product');
 try {
            broadcast(new MessageUpdated($message, $user, User::find($message->receiver_id)))->toOthers();
        } catch (\Exception $e) {
            Log::error('Diffusion MessageUpdated échouée : ' . $e->getMessage());
        }
        return response()->json([
            'message' => 'Message mis à jour avec succès',
            'data' => $message,
        ]);
    }

    /**
     * Supprimer un message
     */
    public function destroy(Request $request, $messageId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $message = Message::find($messageId);
        if (!$message || $message->sender_id !== $user->id) {
            return response()->json(['message' => 'Message non trouvé ou non autorisé'.$messageId], 403);
        }

        // Supprimer le fichier si audio ou image
        if (in_array($message->type, ['audio', 'image'])) {
            $filePath = public_path(str_replace(asset(''), '', $message->content));
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }


        $receiverId = $message->receiver_id;
        $message->delete();

         try {       
             broadcast(new MessageDeleted($messageId, $user->id, $receiverId));
        } catch (\Exception $e) {
            Log::error('Diffusion MessageDeleted échouée : ' . $e->getMessage());
        }
        return response()->json(['message' => 'Message supprimé avec succès']);
    }

}