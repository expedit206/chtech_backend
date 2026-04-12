<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Produit;
use Illuminate\Support\Str;

$produits = Produit::whereNull('slug')->orWhere('slug', '')->get();
$count = 0;
foreach ($produits as $p) {
    if (!$p->slug) {
        $slug = Str::slug($p->nom);
        if (!$slug) {
            $slug = 'produit';
        }
        $p->slug = $slug;
        $p->save();
        $count++;
    }
}
echo "Updated $count products\n";
