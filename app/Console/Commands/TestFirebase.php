<?php
// app/Console/Commands/TestFirebase.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;

class TestFirebase extends Command
{
    protected $signature = 'test:firebase';
    protected $description = 'Tester Firebase avec diagnostics détaillés';

    public function handle()
    {
        $this->info('🧪 Test Firebase détaillé...');
        
        $firebaseService = app(FirebaseService::class);

        // 1. Test connexion
        $this->info('1. Test connexion Firebase...');
        try {
            $token = $firebaseService->getAccessToken();
            $this->info('   ✅ Token obtenu: ' . substr($token, 0, 20) . '...');
        } catch (\Exception $e) {
            $this->error('   ❌ Erreur token: ' . $e->getMessage());
            return 1;
        }

        // 2. Test simple
        $this->info('2. Test notification simple...');
        $result = $firebaseService->testSimpleNotification();
        
        $this->line('   Status: ' . $result['status']);
        $this->line('   Body: ' . $result['body']);
        
        if ($result['success']) {
            $this->info('   ✅ Test simple réussi');
        } else {
            $this->error('   ❌ Test simple échoué');
        }

        // 3. Test normal
        $this->info('3. Test notification normale...');
        $result = $firebaseService->sendNotification(
            'ya29.c.c0ASRK0GYYCdQ',
            "Test Production", 
            "Ceci est un test de notification",
            ['type' => 'test', 'action_url' => 'https://espacecameroun.com']
        );

        if ($result['success']) {
            $this->info('   ✅ Notification envoyée: ' . $result['message_id']);
        } else {
            $this->error('   ❌ Échec: ' . $result['error']);
            $this->line('   Détails: ' . $result['details']);
        }

        return 0;
    }
}