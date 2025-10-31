<?php
// app/Http/Controllers/Api/NotificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPushToken;
use App\Services\NotificationService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;
    protected $firebaseService;

    public function __construct(
        NotificationService $notificationService,
        FirebaseService $firebaseService
    ) {
        $this->notificationService = $notificationService;
        $this->firebaseService = $firebaseService;
    }

    /**
     * @route POST /api/notifications/token
     * Enregistrer le token FCM pour les notifications Web
     */
    public function storeToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|min:10',
            'browser' => 'nullable|string',
            'user_agent' => 'nullable|string'
        ]);

        $user = Auth::user();

        try {
            // Valider le token avec Firebase
            $isValid = $this->firebaseService->validateToken($request->fcm_token);
            
            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token FCM invalide'
                ], 400);
            }

            UserPushToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'fcm_token' => $request->fcm_token
                ],
                [
                    'device_type' => 'web',
                    'browser' => $request->browser ?? $this->detectBrowser($request->user_agent),
                    'user_agent' => $request->user_agent,
                    'is_active' => true
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Token Web Push enregistré avec succès'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur enregistrement token Web', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'enregistrement du token'
            ], 500);
        }
    }

    /**
     * @route POST /api/notifications/test
     * Tester les notifications Web Push
     */
    public function testNotification(Request $request)
    {
        $user = Auth::user();

        $result = $this->notificationService->notifyUser(
            $user->id,
            "🔔 Test Web Push",
            "Les notifications push fonctionnent parfaitement!",
            [
                'type' => 'test',
                'action_url' => '/',
                'test' => true
            ],
            [
                'icon' => '/icons/icon-192x192.png',
                'require_interaction' => true
            ]
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Notification Web Push test envoyée',
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Échec de l\'envoi'
        ], 500);
    }

    /**
     * @route DELETE /api/notifications/token
     * Désactiver un token
     */
    public function disableToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $user = Auth::user();

        UserPushToken::where('user_id', $user->id)
            ->where('fcm_token', $request->fcm_token)
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Token Web Push désactivé'
        ]);
    }

    /**
     * @route GET /api/notifications/tokens
     * Lister les tokens de l'utilisateur
     */
    public function getUserTokens()
    {
        $user = Auth::user();

        $tokens = UserPushToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->get(['fcm_token', 'device_type', 'browser', 'created_at']);

        return response()->json([
            'success' => true,
            'tokens' => $tokens
        ]);
    }

    private function detectBrowser($userAgent)
    {
        if (strpos($userAgent, 'Chrome') !== false) return 'chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'safari';
        if (strpos($userAgent, 'Edge') !== false) return 'edge';
        return 'unknown';
    }
}