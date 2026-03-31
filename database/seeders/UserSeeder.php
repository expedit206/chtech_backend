<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    // ajouter le faker 
    public function run(): void
    {

        // Créer 50 utilisateurs
        User::factory(2)->create();

        // Créer un utilisateur admin spécifique
        //      protected $fillable = [
        //     'id',
        //     'nom',
        //     'telephone',
        //     'email',
        //     'ville',
        //     'mot_de_passe',
        //     'role',
        //     'premium',
        //     'parrain_id',
        //     'parrainage_code',
        //     'jetons',
        //     'photo',
        //     'subscription_ends_at'
        // ];

        // Administrateur
        User::firstOrCreate(
            ['email' => 'admin@chtech.com'],
            [
                'nom' => 'Admin CHTECH',
                'telephone' => '+237600000001',
                'ville' => 'Douala',
                'mot_de_passe' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'premium' => true,
            ]
        );

        // Fournisseur
        User::firstOrCreate(
            ['email' => 'fournisseur@chtech.com'],
            [
                'nom' => 'HighTech Solutions',
                'telephone' => '+237600000002',
                'ville' => 'Yaoundé',
                'mot_de_passe' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => User::ROLE_FOURNISSEUR,
                'premium' => false,
            ]
        );

        // Utilisateur Normal
        User::firstOrCreate(
            ['email' => 'user@chtech.com'],
            [
                'nom' => 'Jean Dupont',
                'telephone' => '+237600000003',
                'ville' => 'Douala',
                'mot_de_passe' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => User::ROLE_USER,
                'premium' => false,
            ]
        );

        // Ancien admin fallback
        User::firstOrCreate(
            ['email' => 'aaa@aaa.com'],
            [
                'nom' => 'Admin Espace',
                'telephone' => '+237690000000',
                'ville' => 'Douala',
                'mot_de_passe' => \Illuminate\Support\Facades\Hash::make('aaaaaaaa'),
                'role' => User::ROLE_ADMIN,
                'premium' => true,
            ]
        );

        // Associer quelques utilisateurs à un parrain
        $users = User::all();
        $users->each(function ($user) use ($users) {
            if (Faker::create()->boolean(30) // 30% de chance d'avoir un parrain    
            ) {

                // 30% de chance d'avoir un parrain
                $user->parrain_id = $users->random()->id;
                $user->save();
            }
        });
    }
}
