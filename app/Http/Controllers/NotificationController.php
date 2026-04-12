<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class NotificationController extends Controller
{
    protected $firebaseService;

    public function __construct(NotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Stocker un nouveau device token
     */
    /**
     * Enregistre ou met à jour un jeton de périphérique (FCM) pour les notifications push
     */
    public function store(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string'
        ]);

        try {
            $user = Auth::user();
            $deviceToken = $request->device_token;

            // Vérifier si le token existe déjà
            $existingToken = DeviceToken::where('device_token', $deviceToken)->first();

            if ($existingToken) {
                // Mettre à jour le token existant
                $existingToken->update([
                    'user_id' => $user ? $user->id : null,
                    'is_active' => true
                ]);

                return response()->json([
                    'message' => 'Token updated successfully',
                    'token' => $existingToken
                ]);
            }

            // Créer un nouveau token
            $token = DeviceToken::create([
                'user_id' => $user ? $user->id : null,
                'device_token' => $deviceToken,
                'browser' => $request->header('User-Agent'),
                'device_type' => 'web'
            ]);

            return response()->json([
                'message' => 'Token stored successfully',
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error storing device token: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error storing token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer une notification de test
     */
    /**
     * Envoie une notification push de test au périphérique spécifié
     */
    public function TestNotification(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string'
        ]);

        $notification = [
            'title' => 'Notification de Test',
            'body' => 'Ceci est une notification de test depuis Laravel! 🎉',
            'icon' => asset('storage/profile_photos/default_icon.png'),
            'click_action' => url('/login')
        ];

        $data = [
            'type' => 'test',
            'url' => url('/login'),
            'timestamp' => now()->toISOString()
        ];

        $result = $this->firebaseService->sendToDevice(
            $request->device_token, 
            $notification, 
            $data
        );

        return response()->json(["result"=>$result]);
     
    }

    /**
     * Récupérer les tokens de l'utilisateur
     */
    /**
     * Liste tous les jetons de périphérique actifs pour l'utilisateur authentifié
     */
    public function getUserTokens()
    {
        $user = Auth::user();
        $tokens = DeviceToken::where('user_id', $user->id)
                            ->active()
                            ->get();

        return response()->json($tokens);
    }

    /**
     * Désactiver un token
     */
    /**
     * Désactive un jeton de périphérique spécifique
     */
    public function destroy($id)
    {
        $token = DeviceToken::where('id', $id)
                           ->where('user_id', Auth::id())
                           ->firstOrFail();

        $token->update(['is_active' => false]);

        return response()->json(['message' => 'Token deactivated successfully']);
    }

    /**
     * Liste toutes les notifications de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);
        
        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count()
        ]);
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues']);
    }

    /**
     * Supprime une notification
     */
    public function deleteNotification($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification supprimée']);
    }
}