<?php
// app/Console/Commands/TestProduction.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use App\Models\User;
use App\Models\UserPushToken;

class TestProduction extends Command
{
    protected $signature = 'test:production';
    protected $description = 'Tester le système en production';

    public function handle()
    {
        $this->info('🧪 Début des tests production...');

        // 1. Test Firebase
        $this->info('1. Test connexion Firebase...');
        try {
            $firebaseService = app(FirebaseService::class);
            $token = $firebaseService->getAccessToken();
            $this->info('   ✅ Firebase connecté');
        } catch (\Exception $e) {
            $this->error('   ❌ Erreur Firebase: ' . $e->getMessage());
            return 1;
        }

        // 2. Test notification
        $this->info('2. Test envoi notification...');
        $result = $firebaseService->sendNotification(
            'test-cmd-' . uniqid(),
            "🧪 Test Artisan", 
            "Test depuis la commande Artisan",
            ['type' => 'artisan_test']
        );

        if ($result['success']) {
            $this->info('   ✅ Notification envoyée');
        } else {
            $this->error('   ❌ Échec: ' . $result['error']);
        }

        // 3. Statistiques
        $this->info('3. Statistiques système...');
        $this->line('   - Tokens actifs: ' . UserPushToken::where('is_active', true)->count());
        $this->line('   - Utilisateurs: ' . User::count());
        $this->line('   - Environnement: ' . app()->environment());

        $this->info('🎯 Tests terminés!');
        return 0;
    }
}