<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Parrainage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\NiveauParrainage;
use App\Models\ParrainageNiveau;
use App\Services\ParrainageService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ParrainageController extends Controller
{

    /**
     * Récupère la liste des parrainages (en tant que parrain ou filleul en attente) et les statistiques
     */
    public function mesParrainages()
    {
        $user = auth()->user();
        
        // Parrainages où l'utilisateur est parrain
        $parrainages = Parrainage::with('filleul')
            ->where('parrain_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Vérifier si l'utilisateur est filleul (avec parrainage en attente)
        $parrainageEnAttente = Parrainage::with('parrain')
            ->where('filleul_id', $user->id)
            ->where('statut', 'en_attente')
            ->first();

        // Formater les données du parrainage en attente
        $userEstFilleul = null;
        if ($parrainageEnAttente) {
            $userEstFilleul = [
                'id' => $parrainageEnAttente->id,
                'parrain_nom' => $parrainageEnAttente->parrain->nom,
                'email_verification' => $parrainageEnAttente->email_verification,
                'email_verifie' => $parrainageEnAttente->email_verifie,
                'bonus_parrain' => $parrainageEnAttente->bonus_parrain,
                'statut' => $parrainageEnAttente->statut
            ];
        }

        $stats = [
            'total' => $parrainages->count(),
            'en_attente' => $parrainages->where('statut', 'en_attente')->count(),
            'actifs' => $parrainages->where('statut', 'bonus_attribue')->count(),
            'total_bonus' => $parrainages->where('statut', 'bonus_attribue')->count(),
            // 'total_bonus' => $parrainages->where('bonus_attribue', true)->sum('bonus_parrain'),
        ];

        return response()->json([
            'parrainages' => $parrainages,
            'statistiques' => $stats,
            'parrainageEnAttente' => $userEstFilleul
        ]);
    }

    /**
     * Vérifie le code de validation d'email et attribue les bonus de parrainage
     */
    public function verifierEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code_verification' => 'required|string|size:6'
        ]);

        $user = auth()->user();

        try {
            $parrainageService = new ParrainageService();
            $parrainage = $parrainageService->verifierEmailEtAttribuerBonus(
                $request->email, 
                $request->code_verification
            );

            // Vérifier que c'est bien le filleul qui vérifie
            if ($parrainage->filleul_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action non autorisée'
                ], 403);
            }else{
                $user->jetons +=$parrainage->bonus_parrain;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Email vérifié avec succès ! Vous avez reçu vos jetons.',
                'bonus_attribue' => $parrainage->bonus_parrain
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Génère et envoie un code de vérification d'email pour valider un parrainage
     */
    public function demanderVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = auth()->user();
        
        // Trouver le parrainage où l'utilisateur est le filleul
        $parrainage = Parrainage::where('filleul_id', $user->id)
                               ->where('statut', 'en_attente')
                               ->first();

        if (!$parrainage) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun parrainage en attente trouvé'
            ], 404);
        }

        $parrainageService = new ParrainageService();
        $code = $parrainageService->demanderVerificationEmail($parrainage, $request->email);
        
        return response()->json([
            'success' => true,
            'message' => 'Code de vérification envoyé à ' . $request->email,
            'code' => $code
        ]);
    }



    /**
     * Générer une suggestion de code de parrainage
     */
    /**
     * Génère une suggestion de code de parrainage unique basé sur le nom de l'utilisateur
     */
    public function generateCode(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $suggestedCode = Str::random(3) . '' . Str::slug($user->nom);
        $suggestedCode = strtoupper(substr($suggestedCode, 0, 6));

        while (User::where('parrainage_code', $suggestedCode)->exists()) {
            $suggestedCode = Str::slug($user->nom) . '-' . Str::random(4);
            $suggestedCode = strtoupper(substr($suggestedCode, 0, 10));
        }

        return response()->json(['suggested_code' => $suggestedCode]);
    }

    /**
     * Créer ou mettre à jour un code de parrainage personnalisé
     */
    /**
     * Personnalise le code de parrainage de l'utilisateur
     */
    public function createCode(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $request->validate([
            'code' => 'required|string|max:10',
        ]);

        $customCode = strtoupper($request->code);

        if (User::where('parrainage_code', $customCode)->where('id', '!=', $user->id)->exists()) {
            return response()->json(['message' => 'Ce code est déjà utilisé'], 400);
        }

        $user->update(['parrainage_code' => $customCode]);

        return response()->json([
            'message' => 'Code de parrainage créé avec succès',
            'code' => $customCode,
        ]);
    }



    /**
     * Récupère le nombre de parrainages non lus pour le badge de notification
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $userId = Auth::id();
            
            // Compter les parrainages non lus où l'utilisateur est le parrain
            $unreadCount = Parrainage::where('parrain_id', $userId)
                ->where('is_read', false)
                ->count();
            
            // Mettre à jour le badge dans la table badge_unreads
            $this->updateParrainageBadge($userId, $unreadCount);
            
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
                'message' => 'Erreur lors de la récupération des parrainages non lus',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Mettre à jour le badge des parrainages
     */
    private function updateParrainageBadge($userId, $count)
    {
        $badge = BadgeUnread::firstOrCreate(
            ['user_id' => $userId],
            [
                'messages' => 0,
                'reventes' => 0,
                'parrainages' => 0,
                'last_updated' => now()
            ]
        );
        
        $badge->parrainages = $count;
        $badge->last_updated = now();
        $badge->save();
        
        return $badge;
    }
    
    /**
     * Marquer tous les parrainages comme lus
     */
    /**
     * Marque tous les parrainages comme lus et réinitialise le badge
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $userId = Auth::id();
            
            // Marquer tous les parrainages non lus comme lus
            Parrainage::where('parrain_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'updated_at' => now()
                ]);
            
            // Mettre à jour le badge
            $this->updateParrainageBadge($userId, 0);
            
            // Optionnel: Émettre un événement pour mise à jour en temps réel
            broadcast(new \App\Events\BadgesUpdated($userId, 'parrainages', 0));
            
            return response()->json([
                'success' => true,
                'message' => 'Tous les parrainages ont été marqués comme lus',
                'data' => [
                    'unread_count' => 0
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage des parrainages comme lus',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}