<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Revente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{

    // Mettre à jour les notifications
    /**
     * Met à jour les préférences de notification de l'utilisateur (email, sms)
     */
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
    /**
     * Calcule le nombre d'éléments non lus pour les badges (messages, reventes)
     */
    public function badges(Request $request)
    {
        $user = $request->user();
        
        $unreadMessagesCount = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
            
        // Reventes en attente sur les produits de l'utilisateur (actions à faire)
        $reventesPendingCount = Revente::whereHas('produit', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('statut', 'en_attente')
            ->count();
       
        return response()->json([
            'reventes_pending' => $reventesPendingCount,
            'unread_messages' => $unreadMessagesCount,
        ]);
    }

    // Récupérer le profil utilisateur
    /**
     * Récupère le profil détaillé de l'utilisateur avec ses statistiques (conversations, produits)
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $user['conversations_count'] = $user->conversations_count();
        $user['products_count'] = $user?->produits?->count() ?? 0;
        
        return response()->json(['user' => $user], 200);
    }
}