<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'aaa@aaa.com')->first() ?? User::first();

        if (!$author) {
            $this->command->error('Aucun utilisateur trouvé pour le blog.');
            return;
        }

        $posts = [
            [
                'title'   => 'L’IA générative : Révolution ou simple tendance ?',
                'excerpt' => 'Découvrez comment ChatGPT, Claude et Midjourney transforment le paysage technologique en 2024.',
                'image'   => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?q=80&w=800',
                'views'   => 1250,
            ],
            [
                'title'   => 'Top 5 des meilleurs PC Portables pour le développement au Cameroun',
                'excerpt' => 'Notre guide complet pour choisir la machine idéale pour coder à Douala et Yaoundé.',
                'image'   => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=800',
                'views'   => 850,
            ],
            [
                'title'   => 'Sécuriser ses données personnelles : Les bonnes pratiques',
                'excerpt' => 'Comment protéger votre vie privée en ligne face aux menaces de cybercriminalité croissantes.',
                'image'   => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=800',
                'views'   => 420,
            ],
            [
                'title'   => 'L’Internet par satellite : Starlink arrive en Afrique',
                'excerpt' => 'Quel impact pour les zones rurales et la connectivité globale sur le continent ?',
                'image'   => 'https://images.unsplash.com/photo-1517433670267-08bbd4be890f?q=80&w=800',
                'views'   => 2100,
            ],
            [
                'title'   => 'Construire son Setup Gaming en 2024 : Le guide ultime',
                'excerpt' => 'Écran, clavier mécanique et carte graphique : les composants essentiels pour une expérience immersive.',
                'image'   => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=800',
                'views'   => 3400,
            ],
            [
                'title'   => 'Introduction au Développement Web avec Vue.js 3',
                'excerpt' => 'Pourquoi Vue reste le framework préféré des développeurs pour sa simplicité et sa puissance.',
                'image'   => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?q=80&w=800',
                'views'   => 980,
            ],
            [
                'title'   => 'Le télétravail : Comment rester productif depuis Douala',
                'excerpt' => 'Conseils d’ergonomie et outils collaboratifs pour réussir sa carrière en remote.',
                'image'   => 'https://images.unsplash.com/photo-1527689368864-3a821dbccc34?q=80&w=800',
                'views'   => 620,
            ],
            [
                'title'   => 'Blockchain et Crypto : Quel futur pour les paiements mobiles ?',
                'excerpt' => 'Analyse de l’intégration des cryptomonnaies dans les services financiers africains.',
                'image'   => 'https://images.unsplash.com/photo-1621761191319-c6fb620040bc?q=80&w=800',
                'views'   => 1150,
            ],
        ];

        Storage::disk('public')->makeDirectory('blog');
        $this->command->info('📥 Téléchargement des images du blog...');

        foreach ($posts as $p) {
            $localImage = $this->downloadImage($p['image']);

            Post::create([
                'title'        => $p['title'],
                'slug'         => Str::slug($p['title']),
                'excerpt'      => $p['excerpt'],
                'content'      => "Ceci est le contenu détaillé de l'article sur : " . $p['title'] . "\n\n" . str_repeat("Lorem ipsum dolor sit amet, consectetur adipiscing elit. ", 20),
                'image'        => $localImage,
                'is_published' => true,
                'published_at' => now()->subDays(rand(1, 30)),
                'views_count'  => $p['views'],
                'author_id'    => $author->id,
            ]);

            $this->command->line("  ✓ {$p['title']}");
        }
    }

    private function downloadImage(string $url): string
    {
        $filename = 'blog/' . md5($url) . '.jpg';

        if (Storage::disk('public')->exists($filename)) {
            return $filename;
        }

        try {
            $response = Http::timeout(30)->get($url);
            if ($response->successful()) {
                Storage::disk('public')->put($filename, $response->body());
                return $filename;
            }
        } catch (\Throwable $e) {
            $this->command->warn("  ⚠ Échec image blog : {$url}");
        }

        return $url;
    }
}
