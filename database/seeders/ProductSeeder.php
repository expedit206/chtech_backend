<?php

namespace Database\Seeders;

use App\Models\Produit;
use App\Models\User;
use App\Models\CategoryProduit;
use App\Models\ProduitCount;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Seeder;

/**
 * ProductSeeder — données extraites de index.html, images téléchargées dans storage.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::where('email', 'aaa@aaa.com')->first()
            ?? User::first();

        if (!$seller) {
            $this->command->error('Aucun utilisateur trouvé. Lancez UserSeeder en premier.');
            return;
        }

        // Correspondances catégories nom -> id
        $cats = CategoryProduit::pluck('id', 'nom');

        $get = fn(string $nom) => $cats[$nom]
            ?? $cats->first()
            ?? Str::uuid();

        $products = [

            // ─────────────── PROCESSEURS ───────────────
            [
                'nom'         => 'Processeur Intel Core i9-13900K',
                'category'    => 'Processeurs & CPU',
                'description' => "Le processeur haut de gamme d'Intel, idéal pour le gaming extrême et la création de contenu. 24 cœurs (8P + 16E), fréquence boost 5.8 GHz. Compatible socket LGA1700. Livré avec ventirad.",
                'prix'        => 385000,
                'quantite'    => 5,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.9,
                'avis'        => 47,
                'vues'        => 1850,
                'photos'      => [
                    'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=800',
                    'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Processeur AMD Ryzen 9 7950X',
                'category'    => 'Processeurs & CPU',
                'description' => "16 cœurs / 32 threads, 4.5 GHz de base (boost 5.7 GHz). Architecture Zen 4, plateforme AM5. Exceptionnel pour le multitâche, le rendu 3D et le streaming simultané.",
                'prix'        => 320000,
                'quantite'    => 4,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.8,
                'avis'        => 39,
                'vues'        => 1620,
                'photos'      => [
                    'https://images.unsplash.com/photo-1555617778-02518510b9eb?q=80&w=800',
                    'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Processeur Intel Core i5-13600K',
                'category'    => 'Processeurs & CPU',
                'description' => "Le meilleur rapport qualité/prix du moment. 14 cœurs (6P + 8E), fréquence max 5.1 GHz. Parfait pour le gaming et la bureautique avancée. Non overclockable mais débloqué.",
                'prix'        => 165000,
                'quantite'    => 10,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.7,
                'avis'        => 88,
                'vues'        => 3240,
                'photos'      => [
                    'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Processeur AMD Ryzen 5 5600X — Occasion',
                'category'    => 'Processeurs & CPU',
                'description' => "6 cœurs / 12 threads, 4.6 GHz boost. Occason — testé, fonctionnel à 100 %. Idéal pour construire un PC gaming milieu de gamme. Vendu sans boîte, avec pâte thermique.",
                'prix'        => 68000,
                'quantite'    => 3,
                'condition'   => 'occasion',
                'ville'       => 'Bafoussam',
                'note'        => 4.5,
                'avis'        => 22,
                'vues'        => 960,
                'photos'      => [
                    'https://images.unsplash.com/photo-1555617778-02518510b9eb?q=80&w=800',
                ],
            ],

            // ─────────────── LAPTOPS ───────────────
            [
                'nom'         => 'MacBook Pro 14" M3 Pro — 18 Go RAM',
                'category'    => 'Laptops & PC Portables',
                'description' => "Puce M3 Pro, 18 Go mémoire unifiée, SSD 512 Go. Écran Liquid Retina XDR 14.2\", ProMotion 120 Hz. Autonomie jusqu'à 18h. Parfait pour les créatifs et les développeurs exigeants.",
                'prix'        => 1250000,
                'quantite'    => 3,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.9,
                'avis'        => 31,
                'vues'        => 2100,
                'photos'      => [
                    'https://images.unsplash.com/photo-1517336714731-489689fd1ca4?q=80&w=800',
                    'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?q=80&w=800',
                    'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?q=80&w=800',
                    'https://images.unsplash.com/photo-1525547718511-ad749e739379?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'ASUS ROG Strix G16 — RTX 4070',
                'category'    => 'Laptops & PC Portables',
                'description' => "PC gaming haute performance : Intel Core i9-13980HX, RTX 4070 8 Go, 16 Go DDR5, SSD 1 To. Écran 16\" QHD 240 Hz, ROG Armory Crate, RGB per-key. Refroidissement ROG Intelligent Cooling.",
                'prix'        => 980000,
                'quantite'    => 2,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.8,
                'avis'        => 19,
                'vues'        => 1490,
                'photos'      => [
                    'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?q=80&w=800',
                    'https://images.unsplash.com/photo-1603302576837-37561b2e2302?q=80&w=800',
                    'https://images.unsplash.com/photo-1525547718511-ad749e739379?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Dell XPS 15 9530 — OLED Touch',
                'category'    => 'Laptops & PC Portables',
                'description' => "Intel Core i7-13700H, 32 Go RAM DDR5, RTX 4060 6 Go. Écran OLED 3.5K tactile 60 Hz. Chassis aluminium ultra-fin. SSD PCIe 512 Go. Parfait pour les professionnels nomades.",
                'prix'        => 875000,
                'quantite'    => 4,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.7,
                'avis'        => 26,
                'vues'        => 1870,
                'photos'      => [
                    'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?q=80&w=800',
                    'https://images.unsplash.com/photo-1517336714731-489689fd1ca4?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'HP EliteBook 840 G9 — i7 12e Gén',
                'category'    => 'Laptops & PC Portables',
                'description' => "Laptop pro ultra-sécurisé. Intel Core i7-1255U, 16 Go RAM, SSD 512 Go. Écran 14\" FHD antireflets. Empreinte digitale, webcam 5MP, WiFi 6E. Certifié MIL-STD-810H.",
                'prix'        => 520000,
                'quantite'    => 6,
                'condition'   => 'neuf',
                'ville'       => 'Bafoussam',
                'note'        => 4.6,
                'avis'        => 34,
                'vues'        => 1240,
                'photos'      => [
                    'https://images.unsplash.com/photo-1603302576837-37561b2e2302?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Lenovo ThinkPad X1 Carbon Gen 11 — Occasion',
                'category'    => 'Laptops & PC Portables',
                'description' => "L'ultra-portable business par excellence. i7-1365U, 16 Go, SSD 256 Go. Écran 14\" IPS 2.8K. Poids 1.12 kg. Occasion — état cosmétique 9/10, batterie 87 %. Livraison garantie sous 48h à Douala.",
                'prix'        => 390000,
                'quantite'    => 2,
                'condition'   => 'occasion',
                'ville'       => 'Douala',
                'note'        => 4.4,
                'avis'        => 15,
                'vues'        => 810,
                'photos'      => [
                    'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?q=80&w=800',
                ],
            ],

            // ─────────────── SMARTPHONES ───────────────
            [
                'nom'         => 'iPhone 15 Pro Max 256 Go — Titane Naturel',
                'category'    => 'Smartphones',
                'description' => "Puce A17 Pro, caméra ProRes 4K 60fps, Dynamic Island, USB-C. Chassis titane grade 5 ultraléger. Autonomie 29h vidéo. Neuf, scellé, facture incluse. Garantie Apple 1 an.",
                'prix'        => 1299000,
                'quantite'    => 8,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 5.0,
                'avis'        => 124,
                'vues'        => 5670,
                'photos'      => [
                    'https://images.unsplash.com/photo-1695048133142-1a20484d2569?q=80&w=800',
                    'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?q=80&w=800',
                    'https://images.unsplash.com/photo-1696446701796-da61225697cc?q=80&w=800',
                    'https://images.unsplash.com/photo-1696429119934-51006be14925?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Samsung Galaxy S24 Ultra — 512 Go',
                'category'    => 'Smartphones',
                'description' => "Snapdragon 8 Gen 3, stylet S Pen intégré, caméra 200 MP, zoom optique 100x. Écran Dynamic AMOLED 6.8\" 120 Hz. RAM 12 Go. Intelligence artificielle Galaxy AI. Neuf sous scellé.",
                'prix'        => 1085000,
                'quantite'    => 6,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.8,
                'avis'        => 89,
                'vues'        => 3980,
                'photos'      => [
                    'https://images.unsplash.com/photo-1610945264803-c22b62831734?q=80&w=800',
                    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=800',
                    'https://images.unsplash.com/photo-1707412705703-a25b396e9596?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Xiaomi 14 Ultra — Caméra Leica',
                'category'    => 'Smartphones',
                'description' => "Co-développé avec Leica, quadruple caméra 50 MP, Snapdragon 8 Gen 3, 16 Go RAM. Charge rapide HyperCharge 90W. Écran AMOLED 6.73\". Un monstre photo à prix compétitif.",
                'prix'        => 720000,
                'quantite'    => 5,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.7,
                'avis'        => 43,
                'vues'        => 2150,
                'photos'      => [
                    'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'iPhone 13 Pro 256 Go — Occasion Excellent état',
                'category'    => 'Smartphones',
                'description' => "Puce A15 Bionic, triple caméra Pro 12 MP (télé + grand-angle + ultra grand-angle). Écran ProMotion 120 Hz Super Retina XDR 6.1\". Occasion — 95 % batterie, aucune rayure. Déverrouillé.",
                'prix'        => 510000,
                'quantite'    => 3,
                'condition'   => 'occasion',
                'ville'       => 'Bafoussam',
                'note'        => 4.6,
                'avis'        => 37,
                'vues'        => 1890,
                'photos'      => [
                    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=800',
                    'https://images.unsplash.com/photo-1695048133142-1a20484d2569?q=80&w=800',
                ],
            ],

            // ─────────────── ACCESSOIRES TECH (comme dans index.html) ───────────────
            [
                'nom'         => 'Montre Connectée Apple Watch Series 9 — 45mm',
                'category'    => 'Montres Connectées',
                'description' => "Puce S9, double tap, détection crash, ECG, SpO2, GPS précis. Écran retina LTPO toujours allumé. Compatible iPhone. Bracelet Sport Midnight inclus. Autonomie 18h.",
                'prix'        => 295000,
                'quantite'    => 12,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.9,
                'avis'        => 76,
                'vues'        => 3450,
                'photos'      => [
                    'https://images.unsplash.com/photo-1546868871-7041f2a55e12?q=80&w=800',
                    'https://images.unsplash.com/photo-1551816230-ef5deaed4a26?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Casque Sony WH-1000XM5 — Noir',
                'category'    => 'Audio & Casques',
                'description' => "Référence absolue en annulation de bruit active. Autonomie 30h, charge rapide (10 min = 5h). Codec LDAC haute résolution. Micro array 8 capsules pour appels cristallins. Pliable.",
                'prix'        => 175000,
                'quantite'    => 15,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.8,
                'avis'        => 112,
                'vues'        => 4210,
                'photos'      => [
                    'https://images.unsplash.com/photo-1583394838336-acd977736f90?q=80&w=800',
                    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'AirPods Pro 2e gén — USB-C (2024)',
                'category'    => 'Audio & Casques',
                'description' => "Annulation active du bruit H2, audio adaptatif, mode transparence amélioré. Version USB-C compatible Vision Pro. Autonomie 30h avec boîtier. Résistance IP54.",
                'prix'        => 149000,
                'quantite'    => 20,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.9,
                'avis'        => 203,
                'vues'        => 7820,
                'photos'      => [
                    'https://images.unsplash.com/photo-1606741965509-717b6b73f80a?q=80&w=800',
                    'https://images.unsplash.com/photo-1583394838336-acd977736f90?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Clavier Mécanique Keychron Q1 Max — Sans-fil',
                'category'    => 'Clavier & Souris',
                'description' => "Clavier 75 % gasket-mount. Switch Gateron G Pro Red. Boitier aluminium CNC. Tri-mode (Bluetooth 5.1 / 2.4 GHz / USB-C). RGB south-facing. Compatible Mac et Windows. Impression de luxe.",
                'prix'        => 89000,
                'quantite'    => 8,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.7,
                'avis'        => 54,
                'vues'        => 2340,
                'photos'      => [
                    'https://images.unsplash.com/photo-1587829741301-dc798b83add3?q=80&w=800',
                    'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Souris Logitech MX Master 3S — Graphite',
                'category'    => 'Clavier & Souris',
                'description' => "La souris de productivité ultime. Capteur Darkfield 8000 DPI. Roue MagSpeed électromagnétique. Bouton Bolt USB ultra-stable. Personnalisable avec Logi Options+. Compatible multi-OS.",
                'prix'        => 65000,
                'quantite'    => 18,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.8,
                'avis'        => 87,
                'vues'        => 3100,
                'photos'      => [
                    'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Écran Dell UltraSharp 27" 4K — USB-C',
                'category'    => 'Écrans & Moniteurs',
                'description' => "Dalle IPS 4K UHD (3840×2160), dE <2, sRGB 100 %, DCI-P3 95 %. USB-C 90W Power Delivery. Hub intégré (USB-A, HDMI, DisplayPort). Pied réglable en hauteur / pivot / rotation.",
                'prix'        => 289000,
                'quantite'    => 5,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.7,
                'avis'        => 41,
                'vues'        => 1680,
                'photos'      => [
                    'https://images.unsplash.com/photo-1547082299-de196ea013d6?q=80&w=800',
                    'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'SSD Samsung 990 Pro 2 To — PCIe 4.0',
                'category'    => 'Stockage & Mémoire',
                'description' => "Lecture séquentielle 7450 Mo/s, écriture 6900 Mo/s. Format M.2 2280 NVMe. Idéal pour PS5 et PC gaming. Dissipateur inclus. Garantie 5 ans. Le plus rapide de sa génération.",
                'prix'        => 98000,
                'quantite'    => 14,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.9,
                'avis'        => 66,
                'vues'        => 2890,
                'photos'      => [
                    'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Carte Graphique NVIDIA RTX 4070 Ti Super',
                'category'    => 'Cartes Graphiques',
                'description' => "GPU ultra-performant pour le gaming 4K et la création 3D. 16 Go GDDR6X, bus 256 bits. DLSS 3.5, ray tracing 3e gen. Connectique : HDMI 2.1, 3× DisplayPort 1.4a. Ventilation triple fan.",
                'prix'        => 620000,
                'quantite'    => 3,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.8,
                'avis'        => 28,
                'vues'        => 2100,
                'photos'      => [
                    'https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=800',
                    'https://images.unsplash.com/photo-1555617778-02518510b9eb?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Routeur WiFi 6E TP-Link Archer BE900',
                'category'    => 'Réseaux & WiFi',
                'description' => "Tri-bande WiFi 7 (2.4 + 5 + 6 GHz), débit combiné 24 Gbps. 10 antennes haute performance. Port 10 GbE + 2.5 GbE. Idéal pour couvrir une villa 300 m². Application tpMesh incluse.",
                'prix'        => 145000,
                'quantite'    => 7,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.6,
                'avis'        => 29,
                'vues'        => 1120,
                'photos'      => [
                    'https://images.unsplash.com/photo-1544724569-5f546fd6f2b5?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Batterie Externe Anker 737 — 24 000 mAh',
                'category'    => 'Énergie & Batteries',
                'description' => "Charge 3 appareils simultanément. 2× USB-C 140W + 1× USB-A. Écran LCD intégré (état batterie, watt, time restant). Charge un MacBook Pro 14\" à 100 % en 1h30. Standard militaire MIL-STD-810G.",
                'prix'        => 72000,
                'quantite'    => 22,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.7,
                'avis'        => 95,
                'vues'        => 3670,
                'photos'      => [
                    'https://images.unsplash.com/photo-1491553895911-0055eca6402d?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Webcam Logitech Brio 500 — FHD 1080p',
                'category'    => 'Accessoires Tech',
                'description' => "Webcam plug-and-play pour meeting professionnel. FHD 1080p 30fps, correction automatique de la lumière RightLight 4, cadrage automatique Show Mode. Micro stéréo antiparasite. USB-C.",
                'prix'        => 48000,
                'quantite'    => 30,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.5,
                'avis'        => 63,
                'vues'        => 2450,
                'photos'      => [
                    'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Tablette iPad Pro 13" M4 — 256 Go WiFi',
                'category'    => 'Tablettes',
                'description' => "La tablette la plus puissante du marché. Puce M4, écran Ultra Retina XDR OLED tandem 2800 nits peak. Supporte Apple Pencil Pro. Universelle créateurs / pros. Légèreté record 582g.",
                'prix'        => 1150000,
                'quantite'    => 4,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 5.0,
                'avis'        => 58,
                'vues'        => 2980,
                'photos'      => [
                    'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?q=80&w=800',
                    'https://images.unsplash.com/photo-1561154464-82e9adf32764?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'DJI Mini 4 Pro — Kit Fly More Combo',
                'category'    => 'Drones & Caméras',
                'description' => "Drone ultra-compact < 249g. Vidéo 4K HDR 100fps, omnidirectional obstacle sensing. Temps de vol max 34 min. Télécommande RCN3 incluse. Portée FHD 20 km. Parfait pour la vidéo aérienne prof.",
                'prix'        => 540000,
                'quantite'    => 3,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.8,
                'avis'        => 22,
                'vues'        => 1540,
                'photos'      => [
                    'https://images.unsplash.com/photo-1508614589041-895b88991e3e?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'Manette Xbox Elite Series 2 — Blanc',
                'category'    => 'Consoles & Gaming',
                'description' => "La manette pro pour Xbox et PC. Joysticks à tension ajustable, paddles arrière, déclencheurs à course variable. Autonomie 40h. Câble USB-C tissé inclus. Compatible Xbox Series X|S et PC.",
                'prix'        => 85000,
                'quantite'    => 10,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.7,
                'avis'        => 49,
                'vues'        => 2120,
                'photos'      => [
                    'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?q=80&w=800',
                ],
            ],
            [
                'nom'         => 'PlayStation 5 Console — DualSense Special',
                'category'    => 'Consoles & Gaming',
                'description' => "La console de salon la plus puissante de Sony. Profitez de temps de chargement ultra-rapides grâce à son SSD ultra-rapide, une immersion plus profonde avec le retour haptique, des gâchettes adaptatives et l'audio 3D.",
                'prix'        => 450000,
                'quantite'    => 5,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.9,
                'avis'        => 150,
                'vues'        => 8500,
                'photos'      => [
                    'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?q=80&w=800',
                    'https://images.unsplash.com/photo-1594132174009-08cb03f4abbd?q=80&w=800',
                    'https://images.unsplash.com/photo-1607853202273-797f1c22a38e?q=80&w=800',
                    'https://images.unsplash.com/photo-1592155931584-901ac15763e3?q=80&w=800',
                ],
            ],

            // ─────────────── AJOUTS LOCAUX (ASSETS) ───────────────
            [
                'nom'         => 'Ordinateur Dell Latitude — Performance Pro',
                'category'    => 'Laptops & PC Portables',
                'description' => "Ordinateur robuste et performant, idéal pour le travail en entreprise. Équipé d'un processeur puissant et d'un écran haute définition.",
                'prix'        => 350000,
                'quantite'    => 5,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.5,
                'avis'        => 12,
                'vues'        => 450,
                'photos'      => [
                    'produits/dell.jpg',
                    'produits/dell2.jpg',
                    'produits/dell3.jpg',
                    'produits/dell4.jpg',
                ],
            ],
            [
                'nom'         => 'Laptop Lenovo ThinkPad — Édition Spéciale',
                'category'    => 'Laptops & PC Portables',
                'description' => "Le classique de chez Lenovo. Clavier ergonomique, autonomie longue durée et robustesse à toute épreuve.",
                'prix'        => 420000,
                'quantite'    => 3,
                'condition'   => 'neuf',
                'ville'       => 'Yaoundé',
                'note'        => 4.7,
                'avis'        => 8,
                'vues'        => 320,
                'photos'      => [
                    'produits/lenovo.jpg',
                    'produits/lenovo2.jpg',
                ],
            ],
            [
                'nom'         => 'PC Bureau Complet — Setup Gaming Starter',
                'category'    => 'Laptops & PC Portables',
                'description' => "Un pack complet pour commencer le gaming ou le travail productif. Comprend l'unité centrale performante.",
                'prix'        => 280000,
                'quantite'    => 2,
                'condition'   => 'neuf',
                'ville'       => 'Douala',
                'note'        => 4.3,
                'avis'        => 5,
                'vues'        => 580,
                'photos'      => [
                    'produits/pc.jpg',
                    'produits/intel_core_i7.jpg',
                ],
            ],
            [
                'nom'         => 'iPhone 11 Pro — Occasion Certifiée',
                'category'    => 'Smartphones',
                'description' => "iPhone 11 Pro en excellent état. Triple capteur photo, écran Super Retina XDR. Testé et garanti 6 mois.",
                'prix'        => 275000,
                'quantite'    => 4,
                'condition'   => 'occasion',
                'ville'       => 'Douala',
                'note'        => 4.6,
                'avis'        => 21,
                'vues'        => 1100,
                'photos'      => [
                    'produits/iphone11pro.png',
                ],
            ],
        ];

        // S'assurer que le répertoire existe
        Storage::disk('public')->makeDirectory('produits');
        $this->command->info('📥 Téléchargement des images en cours...');

        foreach ($products as $data) {
            $catId = $get($data['category']);

            // Télécharger chaque image et stocker le chemin local
            $localPhotos = [];
            foreach ($data['photos'] as $url) {
                $localPhotos[] = $this->downloadImage($url);
            }

            $produit = Produit::create([
                'id'           => Str::uuid(),
                'user_id'      => $seller->id,
                'category_id'  => $catId,
                'nom'          => $data['nom'],
                'description'  => $data['description'],
                'prix'         => $data['prix'],
                'quantite'     => $data['quantite'],
                'condition'    => $data['condition'],
                'ville'        => $data['ville'],
                'note_moyenne' => $data['note'],
                'nombre_avis'  => $data['avis'],
                'photos'       => $localPhotos,
                'revendable'   => true,
            ]);

            // Créer les compteurs de statistiques
            ProduitCount::firstOrCreate(
                ['produit_id' => $produit->id],
                [
                    'favorites_count' => rand(5, 80),
                    'clics_count'     => $data['vues'],
                    'contacts_count'  => rand(1, 30),
                    'partages_count'  => rand(1, 50),
                ]
            );

            $this->command->line("  ✓ {$data['nom']}");
        }

        $this->command->info('✅ ' . count($products) . ' produits tech créés avec images locales !');
        $this->command->line('  → Images stockées dans : storage/app/public/produits/');
        $this->command->line('  → Accessibles via : http://localhost:8000/storage/produits/...');
    }

    /**
     * Télécharge une image depuis une URL et la stocke dans storage/public/produits/.
     * Retourne le chemin relatif (ex: produits/abc123.jpg) ou l'URL originale si échec.
     */
    private function downloadImage(string $url): string
    {
        // Si c'est déjà un chemin local (ex: produits/dell.jpg), on le retourne
        if (str_starts_with($url, 'produits/')) {
            return $url;
        }

        // Chemin stable dérivé du hash de l'URL
        $filename = 'produits/' . md5($url) . '.jpg';

        // Ne pas re-télécharger si déjà présent
        if (Storage::disk('public')->exists($filename)) {
            return $filename;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'CH-Tech/1.0 Seeder (Laravel)'])
                ->get($url);

            if ($response->successful() && strlen($response->body()) > 1000) {
                Storage::disk('public')->put($filename, $response->body());
                return $filename;
            }
        } catch (\Throwable $e) {
            $this->command->warn("  ⚠ Échec téléchargement : {$url} — {$e->getMessage()}");
        }

        // Fallback : conserver l'URL externe
        return $url;
    }
}
