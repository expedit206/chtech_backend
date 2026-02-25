<?php

namespace App\Console\Commands;

use App\Models\Produit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * php artisan products:download-images
 *
 * Télécharge chaque image Unsplash référencée dans la table produits,
 * les stocke dans storage/app/public/produits/ et met à jour la colonne
 * `photos` avec les chemins locaux (relatifs au storage public).
 */
class DownloadProductImages extends Command
{
    protected $signature   = 'products:download-images
                                {--force : Retélécharger même si le fichier existe déjà}
                                {--limit=0 : Limiter le traitement à N produits (0 = tous)}';

    protected $description = 'Télécharge les images Unsplash des produits dans storage/public/produits/';

    public function handle(): int
    {
        // S'assurer que le lien symbolique existe
        if (! file_exists(public_path('storage'))) {
            $this->warn('Lien storage inexistant — exécution de php artisan storage:link...');
            $this->call('storage:link');
        }

        Storage::disk('public')->makeDirectory('produits');

        $limit    = (int) $this->option('limit');
        $force    = $this->option('force');
        $produits = Produit::all();

        if ($limit > 0) {
            $produits = $produits->take($limit);
        }

        $this->info("📦 {$produits->count()} produits à traiter...");
        $bar = $this->output->createProgressBar($produits->count());
        $bar->start();

        $updated  = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($produits as $produit) {
            $photos    = $produit->photos ?? [];
            $newPhotos = [];
            $changed   = false;

            foreach ($photos as $photo) {
                // Déjà un chemin local → skip (sauf --force)
                if (! str_starts_with($photo, 'http')) {
                    $newPhotos[] = $photo;
                    if (! $force) {
                        $skipped++;
                        continue;
                    }
                }

                // Générer un nom de fichier stable depuis l'URL
                $hash     = md5($photo);
                $filename = "produits/{$hash}.jpg";

                // Skip si fichier déjà présent et pas --force
                if (! $force && Storage::disk('public')->exists($filename)) {
                    $newPhotos[] = $filename;
                    $skipped++;
                    continue;
                }

                // Télécharger l'image
                try {
                    $response = Http::timeout(30)
                        ->withHeaders(['User-Agent' => 'CH-Tech Seeder/1.0'])
                        ->get($photo);

                    if ($response->successful()) {
                        Storage::disk('public')->put($filename, $response->body());
                        $newPhotos[] = $filename;
                        $changed      = true;
                    } else {
                        // Conserver l'URL originale en cas d'échec
                        $newPhotos[] = $photo;
                        $errors++;
                        $this->newLine();
                        $this->warn("  ⚠ Échec HTTP {$response->status()} pour : {$photo}");
                    }
                } catch (\Throwable $e) {
                    $newPhotos[] = $photo;
                    $errors++;
                    $this->newLine();
                    $this->warn("  ⚠ Erreur réseau : {$e->getMessage()}");
                }
            }

            if ($changed) {
                $produit->update(['photos' => $newPhotos]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Terminé !");
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['Produits mis à jour', $updated],
                ['Images déjà locales (skipped)', $skipped],
                ['Erreurs de téléchargement', $errors],
            ]
        );

        return self::SUCCESS;
    }
}
