<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('Aucun utilisateur trouvé. Veuillez lancer UserSeeder d\'abord.');
            return;
        }

        $notifications = [];

        // Types de notifications possibles pour SASAYEE
        $notificationTypes = [
            'App\Notifications\OrderCreatedNotification',
            'App\Notifications\PaymentReceivedNotification',
            'App\Notifications\ProductApprovedNotification',
            'App\Notifications\NewMessageNotification',
            'App\Notifications\SystemAlertNotification',
        ];

        // Exemple de contenus
        $notificationData = [
            [
                'title' => 'Nouvelle commande !',
                'message' => 'Félicitations, vous avez reçu une nouvelle commande pour votre produit.',
                'action_url' => '/seller/orders',
                'icon' => 'shopping-bag'
            ],
            [
                'title' => 'Paiement confirmé',
                'message' => 'Nous avons bien reçu votre paiement de 15 000 FCFA.',
                'action_url' => '/dashboard',
                'icon' => 'check-circle'
            ],
            [
                'title' => 'Produit validé',
                'message' => 'Votre produit est désormais en ligne et visible par les acheteurs.',
                'action_url' => '/seller/products',
                'icon' => 'check'
            ],
            [
                'title' => 'Nouveau message',
                'message' => 'Un acheteur potentiel vous a envoyé un message.',
                'action_url' => '/messages',
                'icon' => 'message-circle'
            ],
            [
                'title' => 'Mise à jour système',
                'message' => 'SASAYEE a mis à jour ses conditions d\'utilisation.',
                'action_url' => '/terms',
                'icon' => 'bell'
            ]
        ];

        // Créer 5 notifications pour chaque utilisateur pour tester
        foreach ($users as $user) {
            for ($i = 0; $i < 5; $i++) {
                $typeIndex = array_rand($notificationTypes);
                $dataIndex = array_rand($notificationData);

                // Alterner entre lu et non lu (1 chance sur 3 d'être lu)
                $readAt = rand(1, 3) === 1 ? now()->subMinutes(rand(10, 1000)) : null;
                $createdAt = now()->subMinutes(rand(1, 10000));

                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'type' => $notificationTypes[$typeIndex],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode($notificationData[$dataIndex]),
                    'read_at' => $readAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        // Insérer par lots pour les performances si beaucoup d'utilisateurs
        $chunks = array_chunk($notifications, 500);
        foreach ($chunks as $chunk) {
            DB::table('notifications')->insert($chunk);
        }

        $this->command->info(count($notifications) . ' notifications créées avec succès.');
    }
}
