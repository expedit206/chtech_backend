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
    public function TestNotification(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string'
        ]);

        $notification = [
            'title' => 'Notification de Test',
            'body' => 'Ceci est une notification de test depuis Laravel! 🎉',
            'icon' => '/images/storage/commercant/logos/1759669437_favi_logo.jpg',
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
    public function destroy($id)
    {
        $token = DeviceToken::where('id', $id)
                           ->where('user_id', Auth::id())
                           ->firstOrFail();

        $token->update(['is_active' => false]);

        return response()->json(['message' => 'Token disabled successfully']);
    }
}