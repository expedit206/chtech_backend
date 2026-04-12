<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Message;
use App\Events\MessageSent;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class AdminChatController extends Controller
{
    /**
     * Liste des messages de diffusion passés
     */
    public function index()
    {
        $broadcasts = \App\Models\BroadcastMessage::with('sender')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json($broadcasts);
    }

    /**
     * Envoyer un message à tous les utilisateurs (Broadcast)
     */
    public function broadcast(Request $request)
    {
        $admin = $request->user();
        
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'type' => 'nullable|string|max:50',
        ]);

        $title = $validated['title'];
        $message = $validated['message'];
        $type = $validated['type'] ?? 'info';
        
        // Concaténer le titre et le message pour le stockage dans 'content'
        // Format: [TITRE] Message
        $fullContent = "[" . $title . "] " . $message;

        // 1. Créer le message de diffusion unique dans la BD
        $broadcastMessage = \App\Models\BroadcastMessage::create([
            'sender_id' => $admin->id,
            'content' => $fullContent,
            'type' => $type,
            'is_active' => true
        ]);

        // 2. Envoyer une notification réelle à tous les utilisateurs
        // On récupère tous les IDs sauf l'admin pour l'envoi
        $users = User::where('id', '!=', $admin->id)->get();
        
        // On utilise le NotificationService ou directement Laravel Notifications
        // Pour faire simple et efficace ici, on crée une notification système pour chacun
        foreach ($users as $user) {
            $user->notify(new \App\Notifications\BaseNotification(
                $title,
                $message,
                ['broadcast_id' => $broadcastMessage->id],
                $type
            ));
        }

        return response()->json([
            'message' => 'Message de diffusion envoyé avec succès.',
            'count' => $users->count(),
            'data' => $broadcastMessage
        ]);
    }
}
