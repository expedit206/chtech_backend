<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Events\MessageSent;
use App\Models\BadgeUnread;
use Illuminate\Http\Request;
use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Broadcast;
use App\Services\NotificationTemplateService;

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
                $otherUser = User::find($otherUserId);

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
                    'last_message_type' => $lastMessageType,
                    'updated_at' => $lastMessage->updated_at ?? now(),
                    'unread_count' => $unreadCount,
                    'profile_photo' => $otherUser->photo,
                ];
            })
            ->sortByDesc(function ($conversation) {
                return $conversation['updated_at'];
            })
            ->values();

        // Ajouter la conversation avec le service client (ID 3)
        $serviceClientId = User::where('email', 'aaa@aaa.com')->first()?->id;
        $isServiceClientConversation = $conversations->firstWhere('user_id', $serviceClientId) === null;

        if ($isServiceClientConversation) {
            $serviceClient = User::find($serviceClientId);

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
                'profile_photo' => $serviceClient->photo ?? null,
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
        $limit = 30;

        // Récupérer les 30 derniers messages dans l'ordre décroissant, puis les trier en ordre ascendant
        $messages = Message::where(function ($query) use ($user, $receiverId) {
            $query->where('sender_id', $user->id)->where('receiver_id', $receiverId)
                ->orWhere('sender_id', $receiverId)->where('receiver_id', $user->id);
        })
            ->with(['sender', 'receiver', 'product'])
            ->latest('created_at')
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->sortBy('created_at');

        return response()->json([
            'messages' => $messages->values(),
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
        if (!$receiver) return response()->json(['message' => 'Destinataire non trouvé',
    'receiverId'=>$receiverId
    ], 404);

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

        // Gestion des types
        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = public_path('storage/messages/audio/' . $filename);
            $file->move(public_path('storage/messages/audio'), $filename);
            $message->content = asset('storage/messages/audio/' . $filename);
            $message->type = 'audio';
        } elseif ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = public_path('storage/messages/images/' . $filename);
            $file->move(public_path('storage/messages/images'), $filename);
            $message->content = asset('storage/messages/images/' . $filename);
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
            $token = $message->receiver->deviceTokens?->where('is_active', true)->pluck('device_token')?->first() ?? null;
            if($token){
                $notificationService = app()->make(NotificationService::class);
                $template = NotificationTemplateService::newMessage($message);
                $notificationService->sendToDevice(
                    $token,
                    $template['notification'], 
                    $template['data']
                );
            } else {
                Log::info('Aucun device token actif pour l\'utilisateur '.$receiver->id);
            }

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
        try {
            $userId = Auth::id();
            
            // Marquer tous les messages non lus comme lus
            Message::where('receiver_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
            
            // Mettre à jour le badge
            $this->updateMessageBadge($userId, 0);
            
            // Optionnel: Émettre un événement pour mise à jour en temps réel
            // broadcast(new \App\Events\BadgesUpdated($userId, 'messages', 0));
            
            return response()->json([
                'success' => true,
                'message' => 'Tous les messages ont été marqués comme lus',
                'data' => [
                    'unread_count' => 0
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    

      public function getUnreadCount(Request $request)
    {
        try {
            $userId = Auth::id();
            
            // Compter les messages non lus où l'utilisateur est le destinataire
            $unreadCount = Message::where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();
            
            // Mettre à jour le badge dans la table badge_unreads
            $this->updateMessageBadge($userId, $unreadCount);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount,
                    'total_unread' => $unreadCount
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des messages non lus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    private function updateMessageBadge($userId, $count)
    {
        $badge = BadgeUnread::firstOrCreate(
            ['user_id' => $userId],
            [
                'messages' => 0,
                'reventes' => 0,
                'parrainages' => 0,
            ]
        );
        
        $badge->messages = $count;
        $badge->save();
        
        return $badge;
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
        if (!$message || $message->sender_id !== $user->id) {
            return response()->json(['message' => 'Message non trouvé ou non autorisé'.$messageId], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $message->content = $validated['content'];
        $message->updated_at = now();
        $message->save();

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