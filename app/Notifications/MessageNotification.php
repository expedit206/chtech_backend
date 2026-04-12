<?php

namespace App\Notifications;

class MessageNotification extends BaseNotification
{
    public static function make($message, $sender)
    {
        $title = "Nouveau message";
        $bodySnippet = mb_strlen($message->content) > 50 ? mb_substr($message->content, 0, 50) . '...' : $message->content;
        $messageText = "{$sender->nom} : {$bodySnippet}";
        
        return new static($title, $messageText, [
            'sender_id' => $sender->id,
            'conversation_id' => $message->conversation_id,
            'action_url' => "/chat/{$sender->id}"
        ], 'message');
    }
}
