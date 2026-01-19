<?php

namespace Database\Seeders;

use App\Models\Produit;
use App\Models\User;
use App\Models\CategoryProduit;
use App\Models\ProduitCount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Supprimer les anciens produits pour avoir un test propre
        Produit::query()->delete();

        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez lancer UserSeeder d\'abord.');
            return;
        }

        $categories = CategoryProduit::pluck('id', 'nom')->toArray();

        $productsData = [
            // Téléphonie
            [
                'category' => 'Téléphonie',
                'name' => 'iPhone 15 Pro Max 256GB',
                'description' => 'Le dernier iPhone avec processeur A17 Pro, titane et un appareil photo incroyable. État neuf avec garantie.',
                'price' => 950000,
                'photos' => ['https://images.unsplash.com/photo-1696446701796-da61225697cc', 'https://images.unsplash.com/photo-1696446702183-cbd13d78905e'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Téléphonie',
                'name' => 'Samsung Galaxy S23 Ultra',
                'description' => 'Smartphone Android puissant avec S-Pen intégré, écran 120Hz et zoom 100x.',
                'price' => 750000,
                'photos' => ['https://images.unsplash.com/photo-1678911820864-e2c567c655d7'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Téléphonie',
                'name' => 'Redmi Note 12 Pro',
                'description' => 'Excellent rapport qualité prix, charge rapide 67W, écran AMOLED.',
                'price' => 185000,
                'photos' => ['https://images.unsplash.com/photo-1661347997213-989973842c0c'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Téléphonie',
                'name' => 'Tecno Camon 20',
                'description' => 'Un smartphone élégant avec un capteur photo performant pour les selfies.',
                'price' => 145000,
                'photos' => ['https://images.unsplash.com/photo-1598327105666-5b89351aff97'],
                'condition' => 'neuf'
            ],

            // Électronique
            [
                'category' => 'Électronique',
                'name' => 'MacBook Pro M2 14 pouces',
                'description' => 'Ordinateur portable ultra puissant pour les professionnels du montage et du graphisme.',
                'price' => 1200000,
                'photos' => ['https://images.unsplash.com/photo-1517336714460-d1b16d1d0f9a'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Électronique',
                'name' => 'Sony PlayStation 5 (Édition Disque)',
                'description' => 'Console de jeux next-gen avec manette DualSense. Pack avec 2 jeux inclus.',
                'price' => 450000,
                'photos' => ['https://images.unsplash.com/photo-1606813907291-d86ebb9c94ad'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Électronique',
                'name' => 'Casque Bose QuietComfort 45',
                'description' => 'Le meilleur de la réduction de bruit active. Sans fil, autonomie 24h.',
                'price' => 220000,
                'photos' => ['https://images.unsplash.com/photo-1546435770-a3e426bf472b'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Électronique',
                'name' => 'Smart TV LG 55" OLED 4K',
                'description' => 'Qualité d\'image exceptionnelle avec des noirs parfaits. Idéal pour le cinéma.',
                'price' => 650000,
                'photos' => ['https://images.unsplash.com/photo-1593359677879-a4bb92f829d1'],
                'condition' => 'neuf'
            ],

            // Vêtements
            [
                'category' => 'Vêtements',
                'name' => 'Robe Africaine en Pagne Wax',
                'description' => 'Magnifique robe cousue main avec des motifs traditionnels vibrants. Taille standard.',
                'price' => 25000,
                'photos' => ['https://images.unsplash.com/photo-1594144408214-49bc33783fd9'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Vêtements',
                'name' => 'Costume Homme Slim Fit',
                'description' => 'Costume 3 pièces bleu marine pour mariages et cérémonies. Tissu de haute qualité.',
                'price' => 85000,
                'photos' => ['https://images.unsplash.com/photo-1594932224828-b4b05928ffb8'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Vêtements',
                'name' => 'Chemise en Lin Blanche',
                'description' => 'Léger et élégant, parfait pour le climat tropical. Respirant.',
                'price' => 15000,
                'photos' => ['https://images.unsplash.com/photo-1596755094514-f87e34085b2c'],
                'condition' => 'neuf'
            ],

            // Chaussures
            [
                'category' => 'Chaussures',
                'name' => 'Nike Air Jordan 1 High',
                'description' => 'Les baskets iconiques. Coloris classique rouge et noir.',
                'price' => 120000,
                'photos' => ['https://images.unsplash.com/photo-1584735175315-9d582312995b'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Chaussures',
                'name' => 'Escarpins de luxe Noirs',
                'description' => 'Talons aiguilles élégants pour soirées. Hauteur 10cm.',
                'price' => 45000,
                'photos' => ['https://images.unsplash.com/photo-1543163521-1bf539c55dd2'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Chaussures',
                'name' => 'Adidas Ultraboost 22',
                'description' => 'Idéal pour le running ou la marche quotidienne. Confort maximal.',
                'price' => 95000,
                'photos' => ['https://images.unsplash.com/photo-1587563871167-1ee9c731aefb'],
                'condition' => 'neuf'
            ],

            // Beauté
            [
                'category' => 'Beauté',
                'name' => 'Sauvage par Dior - Eau de Parfum',
                'description' => 'Un parfum masculin frais et puissant. Flacon de 100ml.',
                'price' => 75000,
                'photos' => ['https://images.unsplash.com/photo-1523293182086-7651a899d37f'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Beauté',
                'name' => 'Kit de Maquillage Professionnel',
                'description' => 'Tout ce dont vous avez besoin pour un maquillage complet. Palettes, pinceaux, rouges à lèvres.',
                'price' => 55000,
                'photos' => ['https://images.unsplash.com/photo-1512496015851-a90fb38ba796'],
                'condition' => 'neuf'
            ],

            // Cuisine & Alimentation
            [
                'category' => 'Cuisine & Alimentation',
                'name' => 'Sac de Riz Parfumé 25kg',
                'description' => 'Riz de qualité supérieure, grains longs et parfumés.',
                'price' => 18500,
                'photos' => ['https://images.unsplash.com/photo-1586201375761-83865001e31c'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Cuisine & Alimentation',
                'name' => 'Huile Végétale Diamaor 5L',
                'description' => 'Huile raffinée produite localement, idéale pour toutes vos fritures.',
                'price' => 7500,
                'photos' => ['https://images.unsplash.com/photo-1474979266404-7eaacbadcbaf'],
                'condition' => 'neuf'
            ],

            // Maison
            [
                'category' => 'Maison',
                'name' => 'Canapé 3 places Moderne',
                'description' => 'Canapé confortable en velours gris avec pieds en bois. S\'adapte à tout salon.',
                'price' => 250000,
                'photos' => ['https://images.unsplash.com/photo-1555041469-a586c61ea9bc'],
                'condition' => 'neuf'
            ],
            [
                'category' => 'Maison',
                'name' => 'Table de Salle à Manger + 4 Chaises',
                'description' => 'Ensemble élégant en bois massif pour vos repas en famille.',
                'price' => 180000,
                'photos' => ['https://images.unsplash.com/photo-1577145946459-39a584dd517f'],
                'condition' => 'neuf'
            ],
        ];

        // Remplir jusqu'à 50 produits en dupliquant ou variant
        $additionalProducts = [
            'Appareil Photo Canon EOS R6',
            'Écouteurs AirPods Pro 2',
            'Montre Apple Watch Series 9',
            'Sac à main Louis Vuitton (Occasion)',
            'Lunettes de soleil Ray-Ban Wayfarer',
            'Veste en Cuir Noire',
            'Jean Levi\'s 501 Original',
            'Baskets New Balance 550',
            'Enceinte JBL Flip 6',
            'Tablette iPad Air 5',
            'Livre : L\'Alchimiste',
            'Parfum Coco Mademoiselle Chanel',
            'Robot Pétrin KitchenAid',
            'Machine à café Nespresso',
            'Aspirateur sans fil Dyson V15',
            'Lampe de chevet vintage',
            'Plante décorative Monstera',
            'Lot de 6 Verres en Cristal',
            'Réfrigérateur Samsung Inverter',
            'Climatiseur Gree 1.5 CV',
            'Micro-ondes Sharp digitial',
            'Drone DJI Mini 3 Pro',
            'Guitare acoustique Yamaha',
            'Balles de Tennis Wilson (Lot de 12)',
            'Tapis de Yoga Antidérapant',
            'Vélo de Montagne Rockrider',
            'Batterie de Cuisine Tefal 10 pièces',
            'Sèche-cheveux Dyson Supersonic',
            'Console Nintendo Switch OLED',
            'Souris Logitech MX Master 3S'
        ];

        $villes = ['Douala', 'Yaoundé', 'Bafoussam', 'Garoua', 'Bamenda', 'Kribi', 'Limbe'];

        // Création des 20 premiers produits détaillés
        foreach ($productsData as $data) {
            $catId = $categories[$data['category']] ?? $categories['Électronique'];
            
            $produit = Produit::create([
                'id' => Str::uuid(),
                'user_id' => $users->random()->id,
                'category_id' => $catId,
                'nom' => $data['name'],
                'description' => $data['description'],
                'prix' => $data['price'],
                'quantite' => rand(5, 50),
                'photos' => $data['photos'],
                'condition' => $data['condition'],
                'ville' => $villes[array_rand($villes)],
                'est_actif' => true,
                'revendable' => rand(0, 1) == 1,
                'marge_revente_min' => rand(1000, 5000),
            ]);

            // Créer le compteur d'interaction
            ProduitCount::create([
                'produit_id' => $produit->id,
                'favorites_count' => rand(0, 50),
                'clics_count' => rand(10, 500),
                'contacts_count' => rand(0, 20),
                'partages_count' => rand(0, 15)
            ]);
        }

        // Création des 30 autres produits aléatoires
        foreach ($additionalProducts as $name) {
            $catId = array_rand($categories);
            $price = rand(5000, 500000);
            
            $produit = Produit::create([
                'id' => Str::uuid(),
                'user_id' => $users->random()->id,
                'category_id' => $categories[$catId],
                'nom' => $name,
                'description' => "Découvrez cet article de qualité supérieure : " . $name . ". Parfait pour vos besoins quotidiens ou pour offrir en cadeau.",
                'prix' => $price,
                'quantite' => rand(2, 30),
                'photos' => ['https://images.unsplash.com/photo-' . rand(1500000000000, 1600000000000) . '?auto=format&fit=crop&q=80&w=800'],
                'condition' => rand(0, 1) == 1 ? 'neuf' : 'occasion',
                'ville' => $villes[array_rand($villes)],
                'est_actif' => true,
                'revendable' => rand(0, 1) == 1,
                'marge_revente_min' => rand(500, 3000),
            ]);

            ProduitCount::create([
                'produit_id' => $produit->id,
                'favorites_count' => rand(0, 10),
                'clics_count' => rand(1, 100),
                'contacts_count' => 0,
                'partages_count' => 0
            ]);
        }
    }
}