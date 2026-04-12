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
        $senderName = $message->sender->nom ?? 'Quelqu\'un';
        
        if (isset($message->type) && $message->type === 'image') {
            $body = "📷 {$senderName} vous a envoyé une photo.";
        } elseif (isset($message->type) && $message->type === 'video') {
            $body = "🎥 {$senderName} vous a envoyé une vidéo.";
        } elseif (isset($message->type) && $message->type === 'audio') {
            $body = "🎤 {$senderName} vous a envoyé un message vocal.";
        } else {
            $content = $message->content ?? '';
            // Supprimer les sauts de ligne pour la notif
            $content = str_replace(["\r", "\n"], ' ', $content);
            $body = mb_strlen($content) > 90 ? mb_substr($content, 0, 90) . '...' : $content;
            $body = "{$senderName}: {$body}";
        }
        
        return [
             'notification' => [ 
                'title' => 'Nouveau message',
                'body' => $body
            ],
            'data' => [
                'type' => 'new_message',
                'conversation_id' => $message->conversation_id ?? $message->sender_id // Fallback
            ]
        ];
    }

    //reventes
    // Template pour demande de revente
    public static function reventeRequested($revente)
    {
        $productName = $revente->produit->nom ?? $revente->produit->title ?? 'produit';
        $revendeurName = $revente->revendeur->nom ?? 'Utilisateur';

        $content = "Nouvelle demande de revente : {$revendeurName} propose de revendre « {$productName} »";
        // Ajouter les points de suspension seulement si la longueur dépasse 100 caractères
        $body = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '...' : $content;

        return [
            'notification' => [
                'title' => 'Demande de revente',
                'body'  => $body,
            ],
            'data' => [
                'type' => 'revente_requested',
                'statut' => $revente->statut ?? 'en_attente',
                // 'url' => url("/reventes/{$revente->id}"),
            ],
        ];
    }

    // Template pour changement de statut de revente (acceptée / refusée)
    public static function reventeStatusChanged($revente)
    {
        $statusLabel = $revente->statut === 'valider' ? 'acceptée' : 'refusée';
        $productName = $revente->produit->nom ?? 'produit';
        $content = "Votre demande de revente pour « {$productName} » a été {$statusLabel}.";
        $body = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '...' : $content;

        return [
            'notification' => [
                'title' => 'Mise à jour revente',
                'body'  => $body,
            ],
            'data' => [
                'type' => 'revente_status_changed',
                'revente_id' => $revente->id,
                'produit_id' => $revente->produit_id ?? null,
                'statut' => $revente->statut ?? null,
                // 'url' => url("/reventes/{$revente->id}"),
            ],
        ];
    }


}
// $template = NotificationTemplateService::orderConfirmed($order);
// $notificationService->sendToDevice($userToken, $template['notification'], $template['data']);