<?php

namespace Database\Seeders;

use App\Models\CategoryProduit;
use App\Models\CategoryService;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class CategoryProduitSeeder extends Seeder
{
    public function run(): void
    {
        // Catégories extraites du index.html + enrichies
        // Le HTML montre : Processeurs, Laptops, Accessoires
        // On ajoute les catégories tech complètes du site CH-Tech
        $produitCategories = [
            ['nom' => 'Processeurs & CPU',      'icon' => 'fas fa-microchip'],
            ['nom' => 'Laptops & PC Portables', 'icon' => 'fas fa-laptop'],
            ['nom' => 'Smartphones',             'icon' => 'fas fa-mobile-alt'],
            ['nom' => 'Accessoires Tech',        'icon' => 'fas fa-plug'],
            ['nom' => 'Écrans & Moniteurs',      'icon' => 'fas fa-desktop'],
            ['nom' => 'Clavier & Souris',        'icon' => 'fas fa-keyboard'],
            ['nom' => 'Audio & Casques',         'icon' => 'fas fa-headphones'],
            ['nom' => 'Stockage & Mémoire',      'icon' => 'fas fa-hdd'],
            ['nom' => 'Cartes Graphiques',       'icon' => 'fas fa-tv'],
            ['nom' => 'Réseaux & WiFi',          'icon' => 'fas fa-wifi'],
            ['nom' => 'PC de Bureau',            'icon' => 'fas fa-computer'],
            ['nom' => 'Tablettes',               'icon' => 'fas fa-tablet-alt'],
            ['nom' => 'Imprimantes',             'icon' => 'fas fa-print'],
            ['nom' => 'Énergie & Batteries',     'icon' => 'fas fa-battery-full'],
            ['nom' => 'Drones & Caméras',        'icon' => 'fas fa-camera'],
            ['nom' => 'Montres Connectées',      'icon' => 'fas fa-clock'],
            ['nom' => 'Consoles & Gaming',       'icon' => 'fas fa-gamepad'],
            ['nom' => 'Téléphonie Fixe',         'icon' => 'fas fa-phone'],
            ['nom' => 'Électroménager',          'icon' => 'fas fa-blender'],
            ['nom' => 'Mode & Vêtements',        'icon' => 'fas fa-tshirt'],
        ];

        $serviceCategories = [
            ['nom' => 'Réparation Informatique'],
            ['nom' => 'Développement Web'],
            ['nom' => 'Design Graphique'],
            ['nom' => 'Réparation Smartphones'],
            ['nom' => 'Formation Tech'],
            ['nom' => 'Installation Réseau'],
            ['nom' => 'Support & Maintenance'],
            ['nom' => 'Cybersécurité'],
            ['nom' => 'Conseil IT'],
            ['nom' => 'Vidéosurveillance'],
        ];

        foreach ($produitCategories as $cat) {
            CategoryProduit::firstOrCreate(
                ['nom' => $cat['nom']],
                ['id' => Str::uuid()]
            );
        }

        foreach ($serviceCategories as $cat) {
            CategoryService::firstOrCreate(
                ['nom' => $cat['nom']],
                ['id' => Str::uuid()]
            );
        }
    }
}