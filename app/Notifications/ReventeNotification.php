<?php

namespace App\Notifications;

class ReventeNotification extends BaseNotification
{
    /**
     * Notification pour le propriétaire du produit (Nouvelle demande)
     */
    public static function requested($revente)
    {
        $productName = $revente->produit->nom ?? 'produit';
        $revendeurName = $revente->revendeur->nom ?? 'Un utilisateur';
        
        $title = "Demande de revente ! 🤝";
        $message = "{$revendeurName} souhaite revendre votre produit : {$productName}";
        
        return new static($title, $message, [
            'revente_id' => $revente->id,
            'produit_id' => $revente->produit_id,
            'action_url' => "/profile" // Les reventes sont gérées dans le dashboard pour l'instant
        ], 'info');
    }

    /**
     * Notification pour le revendeur (Statut mis à jour)
     */
    public static function statusChanged($revente)
    {
        $statusLabel = $revente->statut === 'valider' ? 'acceptée ✅' : 'refusée ❌';
        $productName = $revente->produit->nom ?? 'produit';
        
        $title = "Mise à jour revente";
        $message = "Votre demande pour « {$productName} » a été {$statusLabel}.";
        
        $actionUrl = $revente->statut === 'valider' 
            ? "/profile/my-products" 
            : "/profile";

        return new static($title, $message, [
            'revente_id' => $revente->id,
            'produit_id' => $revente->produit_id,
            'action_url' => $actionUrl
        ], $revente->statut === 'valider' ? 'success' : 'error');
    }
}
