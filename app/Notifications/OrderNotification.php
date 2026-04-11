<?php

namespace App\Notifications;

class OrderNotification extends BaseNotification
{
    public static function make($order, $status)
    {
        $title = "Mise à jour de commande";
        $message = "Votre commande #{$order->id} est désormais : {$status}";
        
        return new static($title, $message, [
            'order_id' => $order->id,
            'status' => $status,
            'action_url' => "/profile/orders"
        ], 'order');
    }

    public static function forSeller($order)
    {
        $title = "Nouvelle commande ! 🎉";
        $message = "Vous avez reçu une nouvelle commande #{$order->id}";
        
        return new static($title, $message, [
            'order_id' => $order->id,
            'action_url' => "/seller/orders"
        ], 'sale');
    }
}
