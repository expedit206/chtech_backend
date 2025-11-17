<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Collaboration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{

    // Mettre à jour les notifications
    public function updateNotifications(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
        ]);
        $user->update($data);
        return response()->json(['user' => $user]);
    }

    // Compteurs pour badges
    public function badges(Request $request)
    {
        $user = $request->user();
        $commercant = $user->commercant;
        
        $unreadMessagesCount = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
            
        if (!$commercant) {
            return response()->json([
                'collaborations_pending' => 0,
                'unread_messages' => $unreadMessagesCount,
            ]);
        }
        
        $collaborationsPendingCount = Collaboration::where(function ($query) use ($commercant) {
            $query->where('commercant_id', $commercant->id)
                ->orWhereHas('produit.commercant', function ($query) use ($commercant) {
                    $query->where('id', $commercant->id);
                });
        })->where('statut', 'en_attente')
            ->count();
       
        return response()->json([
            'collaborations_pending' => $collaborationsPendingCount,
            'unread_messages' => $unreadMessagesCount,
        ]);
    }

    // Récupérer le profil utilisateur
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $user['favoris_count'] = $user->favoris_count();
        $user['conversations_count'] = $user->conversations_count();
        $user['products_count'] = $user->commercant?->produits?->count() ?? 0;
        $user->load('commercant', 'niveaux_users.parrainageNiveau');
        
        return response()->json(['user' => $user], 200);
    }
}