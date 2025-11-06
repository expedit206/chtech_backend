<?php

namespace App\Services;




// App\Services\NotificationTemplateService.php
class NotificationTemplateService
{
    public static function orderConfirmed($order)
    {
        return [
            'notification' => [ 
                'title' => 'Commande confirmée! 🎉',
            'body' => "Votre commande #{$order->id} a été confirmée. Livraison prévue le {$order->delivery_date}",
           
            ],
            'data' => [
                'type' => 'order_confirmed',
                'order_id' => $order->id,
                'url' => url("/orders/{$order->id}")
            ]
        ];
    }
    
    public static function newMessage($message)
    {
          $content = $message->content ?? '';
        // Vérifier la longueur avant d'ajouter les points de suspension
        $body = mb_strlen($content) > 90 ? mb_substr($content, 0, 100) . '...' : $content;

        
        return [
             'notification' => [ 
             
            'title' => "message de {$message->sender->nom}",
            'body' =>$body
            ],
            'data' => [
                'type' => 'new_message',
                // 'message_id' => $message->id,
                'conversation_id' => $message->conversation_id
            ]
        ];
    }

    //collaborations
// Template pour demande de collaboration
    public static function collaborationRequested($collaboration)
    {
        $productName = $collaboration->produit->nom ?? $collaboration->produit->title ?? 'produit';
        $commercantName = $collaboration->commercant->nom
            ?? ($collaboration->commercant->user->nom ?? 'Commerçant');
        $price = isset($collaboration->prix_revente) ? number_format($collaboration->prix_revente, 0, ',', ' ') . ' FCFA' : '';

        $content = "Nouvelle demande de collaboration : {$commercantName} propose de revendre « {$productName} » {$price}";
        // Ajouter les points de suspension seulement si la longueur dépasse 100 caractères
        $body = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '...' : $content;

        return [
            'notification' => [
                'title' => 'Demande de collaboration',
                'body'  => $body,
            ],
            'data' => [
                'type' => 'collaboration_requested',
                // 'collaboration_id' => $collaboration->id,
                // 'produit_id' => $collaboration->produit_id ?? null,
                // 'commercant_id' => $collaboration->commercant_id ?? null,
                'statut' => $collaboration->statut ?? 'en_attente',
                'url' => url("/collaborations/{$collaboration->id}"),
            ],
        ];
    }

    // Template pour changement de statut de collaboration (acceptée / refusée)
    public static function collaborationStatusChanged($collaboration)
    {
        $statusLabel = $collaboration->statut === 'valider' ? 'acceptée' : 'refusée';
        $productName = $collaboration->produit->nom ?? 'produit';
        $content = "Votre demande de collaboration pour « {$productName} » a été {$statusLabel}.";
        $body = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '...' : $content;

        return [
            'notification' => [
                'title' => 'Mise à jour collaboration',
                'body'  => $body,
            ],
            'data' => [
                'type' => 'collaboration_status_changed',
                'collaboration_id' => $collaboration->id,
                'produit_id' => $collaboration->produit_id ?? null,
                'statut' => $collaboration->statut ?? null,
                'url' => url("/collaborations/{$collaboration->id}"),
            ],
        ];
    }

}

// Utilisation
// $template = NotificationTemplateService::orderConfirmed($order);
// $notificationService->sendToDevice($userToken, $template['notification'], $template['data']);