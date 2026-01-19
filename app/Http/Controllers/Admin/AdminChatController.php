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
     * Envoyer un message à tous les utilisateurs (Broadcast)
     */
    public function broadcast(Request $request)
    {
        $admin = $request->user();
        
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            'type' => 'nullable|string|in:text,image,audio',
            'image' => 'nullable|image|max:5120',
        ]);

        $content = $validated['content'];
        $type = $validated['type'] ?? 'text';
        
        // Gestion de l'image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('storage/messages/images'), $filename);
            $content = asset('storage/messages/images/' . $filename);
            $type = 'image';
        }

        // 1. Créer le message de diffusion unique dans la BD
        $broadcastMessage = \App\Models\BroadcastMessage::create([
            'sender_id' => $admin->id,
            'content' => $content,
            'type' => $type,
            'is_active' => true
        ]);

        // 2. (Optionnel) Envoyer les notifications PUSH à tout le monde
        // Pour éviter de bloquer le serveur, idéalement ceci devrait être dans un Job (Queue)
        // On va le faire ici pour l'instant mais de manière légère (sans création de Message)
        
        $users = User::where('id', '!=', $admin->id)->get();
        $count = $users->count();

        // On peut lancer un job ici, ou itérer si pas trop d'utilisateurs.
        // Simulons l'envoi de notif simple
        
        // Note: Pour que le message apparaisse en temps réel, on pourrait émettre un event global
        // broadcast(new GlobalBroadcastMessage($broadcastMessage))->toOthers();
        
        return response()->json([
            'message' => 'Message de diffusion créé avec succès.',
            'count' => $count,
            'data' => $broadcastMessage
        ]);
    }
}
