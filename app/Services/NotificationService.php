<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\User;
use App\Models\UserPushToken;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Notifier un utilisateur (Web Push)
     */
    public function notifyUser($userId, $title, $body, $data = [], $options = [])
    {
        try {
            $tokens = UserPushToken::where('user_id', $userId)
                ->where('is_active', true)
                ->where('device_type', 'web') // Seulement les tokens web
                ->pluck('fcm_token')
                ->toArray();

            if (empty($tokens)) {
                Log::warning('Aucun token Web Push actif', ['user_id' => $userId]);
                return ['success' => false, 'error' => 'Aucun token web actif'];
            }

            // Options par défaut pour le web
            $webOptions = array_merge([
                'icon' => '/icons/icon-192x192.png',
                'badge' => '/icons/badge-72x72.png',
                'require_interaction' => false,
                'silent' => false
            ], $options);

            $results = [];
            foreach ($tokens as $token) {
                $results[$token] = $this->firebaseService->sendNotification(
                    $token, $title, $body, $data, $webOptions
                );
            }

            Log::info('📨 Notifications Web Push envoyées', [
                'user_id' => $userId,
                'tokens_count' => count($tokens),
                'title' => $title
            ]);

            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            Log::error('Erreur notification Web Push', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notifications spécifiques avec actions
     */
    public function notifyNewMessage($userId, $senderName, $message, $chatId)
    {
        return $this->notifyUser($userId,
            "💬 {$senderName}",
            substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
            [
                'type' => 'new_message',
                'chat_id' => $chatId,
                'sender' => $senderName,
                'action_url' => "/chats/{$chatId}",
                'timestamp' => now()->toISOString()
            ],
            [
                'actions' => [
                    [
                        'action' => 'open_chat',
                        'title' => '💬 Ouvrir',
                        'icon' => '/icons/chat-icon.png'
                    ],
                    [
                        'action' => 'mark_read',
                        'title' => '✅ Lu',
                        'icon' => '/icons/check-icon.png'
                    ]
                ],
                'require_interaction' => true
            ]
        );
    }

    public function notifyJetonPurchase($userId, $jetonCount)
    {
        return $this->notifyUser($userId,
            "🎉 {$jetonCount} jetons achetés!",
            "Vos jetons ont été crédités avec succès",
            [
                'type' => 'jeton_purchase',
                'jeton_count' => $jetonCount,
                'action_url' => '/jeton-history'
            ],
            [
                'icon' => '/icons/coin-icon.png',
                'silent' => false
            ]
        );
    }
}