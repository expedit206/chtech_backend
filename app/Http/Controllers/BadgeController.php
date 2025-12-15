<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Revente;
use App\Models\Parrainage;
use App\Models\BadgeUnread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class BadgeController extends Controller
{
    /**
     * Récupérer le nombre total de notifications non lues
     */
    public function getUnreadCount(Request $request)
    {
        $userId = Auth::id();
        
        $badge = BadgeUnread::firstOrCreate(
            ['user_id' => $userId],
            [
                'messages' => 0,
                'reventes' => 0,
                'parrainages' => 0
            ]
        );
        
        // Si les badges n'existaient pas, on calcule les valeurs initiales
        $this->syncAllBadges($userId);
        $badge->refresh();
        // if ($badge->wasRecentlyCreated) {
        // }
        
        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $badge->messages,
                'reventes' => $badge->reventes,
                'parrainages' => $badge->parrainages,
                'total_unread' => $badge->total_unread
            ]
        ]);
    }
    
    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request)
    {
        $userId = Auth::id();
        $type = $request->input('type'); // 'all', 'messages', 'reventes', 'parrainages'
        
        DB::beginTransaction();
        try {
            switch ($type) {
                case 'messages':
                    $this->markMessagesAsRead($userId);
                    break;
                    
                case 'reventes':
                    $this->markReventesAsRead($userId);
                    break;
                    
                case 'parrainages':
                    $this->markParrainagesAsRead($userId);
                    break;
                    
                case 'all':
                default:
                    $this->markMessagesAsRead($userId);
                    $this->markReventesAsRead($userId);
                    $this->markParrainagesAsRead($userId);
                    break;
            }
            
            $this->updateBadgeCount($userId);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Notifications marquées comme lues'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }
    
    /**
     * Synchroniser manuellement tous les badges
     */
    public function syncBadges(Request $request)
    {
        $userId = Auth::id();
        
        $this->syncAllBadges($userId);
        
        return response()->json([
            'success' => true,
            'message' => 'Badges synchronisés avec succès'
        ]);
    }
    
    /**
     * Mettre à jour le compteur de badge pour un utilisateur
     */
    private function updateBadgeCount($userId)
    {
        // Compter les messages non lus
        $messagesCount = Message::where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();

            $query  = Revente::class;
        
        // Compter les reventes non lues pour cet utilisateur
    
            $reventesCount = Revente::whereHas('produit', function($query) use ($userId) {
        $query->where('user_id', $userId);
    })
    ->where('is_read', false)
    ->count();
        // Compter les parrainages non lus (pour le parrain)
        $parrainagesCount = Parrainage::where('parrain_id', $userId)
            ->where('is_read', false)
            ->count();
        
        // Mettre à jour ou créer l'entrée dans badge_unreads
        BadgeUnread::updateOrCreate(
            ['user_id' => $userId],
            [
                'messages' => $messagesCount,
                'reventes' => $reventesCount,
                'parrainages' => $parrainagesCount,
                'last_updated' => now()
            ]
        );
    }
    
    /**
     * Synchroniser tous les badges
     */
    private function syncAllBadges($userId)
    {
        $this->updateBadgeCount($userId);
    }
    
    /**
     * Marquer tous les messages comme lus
     */
    private function markMessagesAsRead($userId)
    {
        Message::where('receiver_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }
    
    /**
     * Marquer toutes les reventes comme lues
     */
    private function markReventesAsRead($userId)
    {
        Revente::where('revendeur_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true
            ]);
    }
    
    /**
     * Marquer tous les parrainages comme lus
     */
    private function markParrainagesAsRead($userId)
    {
        Parrainage::where('parrain_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true
            ]);
    }
}