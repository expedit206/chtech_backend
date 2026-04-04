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

        // 1. Récupérer les ID des interlocuteurs, product_id et la date du dernier message
        // On modifie pour grouper également par `product_id` (Facebook Marketplace style)
        $rawConvs = Message::selectRaw("
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as interlocutor_id,
                product_id,
                MAX(created_at) as last_msg_at
            ", [$user->id])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->groupBy('interlocutor_id', 'product_id')
            ->orderBy('last_msg_at', 'desc')
            ->get();

        $interlocutorIds = $rawConvs->pluck('interlocutor_id')->unique();
        $productIds = $rawConvs->pluck('product_id')->filter()->unique();

        // 2. Charger les infos des utilisateurs en une seule fois
        $usersMap = User::whereIn('id', $interlocutorIds)->get()->keyBy('id');

        // 3. Charger les comptes de messages non lus en une seule fois par interlocuteur ET produit
        $unreadCounts = Message::select('sender_id', 'product_id', DB::raw('count(*) as count'))
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->whereIn('sender_id', $interlocutorIds)
            ->groupBy('sender_id', 'product_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->sender_id . '_' . $item->product_id => $item->count];
            });

        // 4. Charger les produits concernés
        $productsMap = \App\Models\Produit::whereIn('id', $productIds)->get()->keyBy('id');

        // 5. Pré-charger les derniers messages pour toutes les conversations
        // Utilisation d'une sous-requête pour trouver l'ID du dernier message pour chaque groupe (sender, receiver, product)
        $latestMessageIds = DB::table('messages')
            ->select(DB::raw('MAX(id) as id'))
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->groupBy(DB::raw('LEAST(sender_id, receiver_id)'), DB::raw('GREATEST(sender_id, receiver_id)'), 'product_id')
            ->pluck('id');

        $lastMessages = Message::whereIn('id', $latestMessageIds)
            ->with(['sender', 'receiver', 'product'])
            ->get()
            ->keyBy(function ($message) use ($user) {
                $interlocutorId = ($message->sender_id == $user->id) ? $message->receiver_id : $message->sender_id;
                return $interlocutorId . '_' . ($message->product_id ?? 'null');
            });

        $ordersMap = collect();
        if ($productIds->isNotEmpty() && $interlocutorIds->isNotEmpty()) {
            // UNE SEULE requête JOIN au lieu de N requêtes — O(1) au lieu de O(N)
            $orders = \App\Models\Order::select('orders.user_id', 'orders.status', 'order_items.produit_id')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.user_id', $interlocutorIds)
                ->whereIn('order_items.produit_id', $productIds)
                ->latest('orders.created_at')
                ->get();

            foreach ($orders as $order) {
                $key = $order->user_id . '_' . $order->produit_id;
                // Garder seulement le plus récent (already ordered by latest)
                if (!$ordersMap->has($key)) {
                    $ordersMap->put($key, $order->status);
                }
            }
        }

        // 7. Construire la liste des conversations
        $conversations = $rawConvs->map(function ($raw) use ($user, $usersMap, $unreadCounts, $productsMap, $lastMessages, $ordersMap) {
            $otherUser = $usersMap->get($raw->interlocutor_id);
            if (!$otherUser) return null;

            $product = $raw->product_id ? $productsMap->get($raw->product_id) : null;
            $unreadKey = $raw->interlocutor_id . '_' . $raw->product_id;
            $lastMessageKey = $raw->interlocutor_id . '_' . ($raw->product_id ?? 'null');
            $lastMessage = $lastMessages->get($lastMessageKey);

            // Check Order Status if product exists
            $orderStatus = null;
            if ($product) {
                $participantId = ($user->id === $raw->interlocutor_id) ? $user->id : $raw->interlocutor_id;
                $orderStatus = $ordersMap->get($participantId . '_' . $product->id);
            }

            // 7. Déterminer le nom de la conversation (Produit - Client)
            // On ne doit JAMAIS afficher le nom de l'admin.
            $isAdminInterlocutor = ($otherUser->role === 'admin' || $otherUser->email === 'aaa@aaa.com');
            
            // Le "Client" est celui qui n'est pas l'admin. 
            // Si l'utilisateur actuel est l'admin, le client est $otherUser.
            // Si l'utilisateur actuel est le client, le client est $user.
            $clientName = ($user->role === 'admin' || $user->email === 'aaa@aaa.com') ? $otherUser->nom : $user->nom;
            
            $displayName = $product ? ($product->nom . ' - ' . $clientName) : $otherUser->nom;

            return [
                'user_id' => $raw->interlocutor_id,
                'product_id' => $raw->product_id,
                'product_name' => $product ? $product->nom : null,
                'product_slug' => $product ? $product->slug : null,
                'product_image' => $product && !empty($product->photos) ? $product->photos[0] : null,
                'user_name' => $otherUser->nom,
                'user_photo' => $otherUser->photo ?? null,
                'order_status' => $orderStatus,
                'name' => $displayName,
                'profile_photo' => $product && !empty($product->photos) ? $product->photos[0] : ($otherUser->photo ?? null),
                'last_message' => $lastMessage->content ?? '',
                'last_message_type' => $lastMessage->type ?? 'text',
                'updated_at' => $raw->last_msg_at,
                'unread_count' => $unreadCounts->get($unreadKey, 0),
            ];
        })->filter()->values();

        // 8. Logique Service Client (AAAA@aaa.com)
        $serviceClient = User::where('email', 'aaa@aaa.com')->first();
        if ($serviceClient) {
            $hasServiceConv = $conversations->contains('user_id', $serviceClient->id);
            if (!$hasServiceConv) {
                // On l'ajoute par défaut s'il n'existe pas
                $conversations->push([
                    'user_id' => $serviceClient->id,
                    'name' => $serviceClient->nom ?? 'Service Client',
                    'last_message' => 'Écrivez-moi pour tout besoin',
                    'last_message_type' => 'text',
                    'updated_at' => now(),
                    'unread_count' => 0,
                    'profile_photo' => $serviceClient->photo ?? null,
                ]);
            }
        }

        // Tri final
        $finalConversations = $conversations->sortByDesc('updated_at')->values();

        return response()->json(['conversations' => $finalConversations]);
    }

    /**
     * Récupère les messages d'une conversation spécifique (historique paginé)
     */
    /**
     * Récupère les messages d'une conversation spécifique (historique paginé)
     */
    public function index($receiverId, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $offset = $request->query('offset', 0);
        $limit = 30;
        $productId = $request->query('product_id', null);

        // Récupérer les messages privés
        $messagesQuery = Message::where(function ($query) use ($user, $receiverId) {
            $query->where('sender_id', $user->id)->where('receiver_id', $receiverId)
                  ->orWhere('sender_id', $receiverId)->where('receiver_id', $user->id);
        })->with(['sender', 'receiver', 'product']);

        if ($productId !== null && $productId !== 'null' && $productId !== '') {
             $messagesQuery->where('product_id', $productId);
        } else {
             $messagesQuery->whereNull('product_id');
        }

        $privateMessages = $messagesQuery->latest('created_at')
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        // Récupérer les messages de diffusion si le partenaire est un admin ou le service client
        // On suppose que l'émetteur des broadcasts est 'receiverId'
        $broadcastMessages = collect([]);
        $receiver = User::find($receiverId);

        if ($receiver && ($receiver->role === 'admin' || $receiver->email === 'aaa@aaa.com')) {
            // On récupère les broadcasts actifs
            // Idéalement, on filtre ceux créés après l'inscription de l'utilisateur, etc.
            $broadcasts = \App\Models\BroadcastMessage::where('sender_id', $receiverId)
                ->where('is_active', true)
                ->latest('created_at')
                ->take($limit)
                ->get();

            // Transformer les broadcasts en format compatible Message
            $formattedBroadcasts = $broadcasts->map(function ($b) use ($receiver, $user) {
                // Créer un objet fictif compatible avec la structure Message
                // On utilise un ID négatif ou un indicateur pour dire que c'est un broadcast (optionnel)
                // Pour le front, c'est juste un message reçu.
                $msg = new Message();
                $msg->id = 'broadcast_' . $b->id; // ID string pour éviter collisions
                $msg->sender_id = $b->sender_id;
                $msg->receiver_id = $user->id; // Virtuellement pour nous
                $msg->content = $b->content;
                $msg->type = $b->type;
                $msg->is_read = true; // On considère comme lu car pas de tracking précis
                $msg->created_at = $b->created_at;
                $msg->updated_at = $b->updated_at;

                // Relations manuelles
                $msg->setRelation('sender', $receiver);
                $msg->setRelation('receiver', $user);
                $msg->setRelation('product', null);

                return $msg;
            });

            $broadcastMessages = $formattedBroadcasts;
        }

        // Fusionner et trier
        $allMessages = $privateMessages->concat($broadcastMessages)
            ->sortByDesc('created_at')
            ->values();

        // Pagination manuelle après fusion (approximation)
        // Note: La pagination est tricky avec deux sources. 
        // Si on a beaucoup de broadcasts, ils risquent d'inonder.
        // Pour l'instant on renvoie tout ce qu'on a trouvé (limit private + limit broadcast)
        // Le front triera.

        $hasMore = $privateMessages->count() > $limit;
        $finalMessages = $allMessages->take($limit)->sortBy('created_at')->values();

        return response()->json([
            'messages' => $finalMessages,
            'hasMore' => $hasMore,
            'user' => $receiver,
            'product' => $productId ? \App\Models\Produit::find($productId) : null
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
        if (!$receiver) return response()->json([
            'message' => 'Destinataire non trouvé',
            'receiverId' => $receiverId
        ], 404);

        $validated = $request->validate([
            'type' => 'nullable|string|in:text,audio,image,video',
            'content' => 'nullable|string|max:1000',
            'audio' => 'nullable|file|mimes:mp3,wav,ogg,webm|max:10240',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:20480', // Max 20MB
            'product_id' => 'nullable|exists:produits,id',
        ]);

        $message = new Message();
        $message->sender_id = $user->id;
        $message->receiver_id = $receiverId;
        $message->product_id = $validated['product_id'] ?? null;
        $message->type = $validated['type'] ?? 'text';

        // Gestion des types
        $attachmentUrl = null;

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = public_path('storage/messages/audio/' . $filename);
            $file->move(public_path('storage/messages/audio'), $filename);
            $attachmentUrl = asset('storage/messages/audio/' . $filename);
            $message->type = 'audio';
        } elseif ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = public_path('storage/messages/images/' . $filename);
            $file->move(public_path('storage/messages/images'), $filename);
            $attachmentUrl = asset('storage/messages/images/' . $filename);
            $message->type = 'image';
        } elseif ($request->hasFile('video')) {
            $file = $request->file('video');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = public_path('storage/messages/videos/' . $filename);
            if (!file_exists(public_path('storage/messages/videos'))) {
                mkdir(public_path('storage/messages/videos'), 0777, true);
            }
            $file->move(public_path('storage/messages/videos'), $filename);
            $attachmentUrl = asset('storage/messages/videos/' . $filename);
            $message->type = 'video';
        }

        $message->attachment_url = $attachmentUrl;
        $message->content = $validated['content'] ?? null;

        $message->save();
        $message->load('sender', 'receiver', 'product');

        $unreadMessages = Message::where('receiver_id', $receiverId)
            ->where('is_read', false)
            ->count();

        try {
            broadcast(new MessageSent($message, $user, $receiver, $unreadMessages))->toOthers();
            $token = $message->receiver->deviceTokens?->where('is_active', true)->pluck('device_token')?->first() ?? null;
            if ($token) {
                $notificationService = app()->make(NotificationService::class);
                $template = NotificationTemplateService::newMessage($message);
                $notificationService->sendToDevice(
                    $token,
                    $template['notification'],
                    $template['data']
                );
            } else {
                Log::info('Aucun device token actif pour l\'utilisateur ' . $receiver->id);
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

    /**
     * Marque tous les messages reçus par l'utilisateur comme lus
     */
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


    /**
     * Récupère le nombre total de messages non lus pour l'utilisateur
     */
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


    /**
     * Met à jour le compteur de badges de messages dans la base de données
     */
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
     * Éditer un message existant (Seul l'expéditeur peut le faire)
     */
    public function update(Request $request, $messageId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $message = Message::find($messageId);
        if (!$message) {
            return response()->json(['message' => 'Message non trouvé'], 404);
        }

        // Seul l'expéditeur peut éditer (même un admin ne devrait pas changer le contenu d'un autre)
        if ($message->sender_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé à modifier ce message'], 403);
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
     * Supprimer un message (L'expéditeur ou un Admin peut le faire)
     */
    public function destroy(Request $request, $messageId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $message = Message::find($messageId);
        if (!$message) {
            return response()->json(['message' => 'Message non trouvé'], 404);
        }

        // Autoriser l'expéditeur OU un administrateur
        if ($message->sender_id !== $user->id && $user->role !== 'admin') {
            Log::warning('Tentative de suppression non autorisée', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'sender_id' => $message->sender_id
            ]);
            return response()->json(['message' => 'Non autorisé à supprimer ce message'], 403);
        }

        // Supprimer le fichier si audio, image ou vidéo
        if (in_array($message->type, ['audio', 'image', 'video']) && $message->attachment_url) {
            // Extraire le chemin relatif de l'URL asset()
            $filePath = public_path(str_replace(asset(''), '', $message->attachment_url));
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $receiverId = $message->receiver_id;
        $senderId = $message->sender_id;
        $message->delete();

        try {
            broadcast(new MessageDeleted($messageId, $senderId, $receiverId));
        } catch (\Exception $e) {
            Log::error('Diffusion MessageDeleted échouée : ' . $e->getMessage());
        }

        return response()->json(['message' => 'Message supprimé avec succès']);
    }
}
