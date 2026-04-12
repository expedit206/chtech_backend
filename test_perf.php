<?php
use Illuminate\Support\Facades\DB;
use App\Models\Produit;

// Load environment configuration
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::enableQueryLog();
$start = microtime(true);

$produit = Produit::with([
    'user:id,nom,email,telephone,photo,created_at',
    'category:id,nom',
    'reviews.user:id,nom,photo',
    'counts',
    'commercant', 
    'boosts' => function($q) {
        $q->where('statut', 'actif')->where('end_date', '>', now());
    }
])
->first();

if($produit) {
   $similarProduits = Produit::with(['user', 'category'])
       ->where('category_id', $produit->category_id)
       ->where('id', '!=', $produit->id)
       ->latest()
       ->limit(6)
       ->get();
}

$end = microtime(true);
$timeTaken = "Time taken: " . ($end - $start) . " seconds\n\n";

file_put_contents('perf_results_utf8.txt', $timeTaken);
