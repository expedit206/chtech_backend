<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\CategoryProduit;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Génère un sitemap XML dynamique pour le SEO.
     */
    public function index()
    {
        $products = Produit::where('is_active', true)->latest()->get();
        $categories = CategoryProduit::all();
        $baseUrl = config('app.frontend_url', 'https://sasayee.com');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Accueil
        $xml .= '<url>';
        $xml .= '<loc>' . $baseUrl . '/</loc>';
        $xml .= '<changefreq>daily</changefreq>';
        $xml .= '<priority>1.0</priority>';
        $xml .= '</url>';

        // Produits
        foreach ($products as $product) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . '/produit/' . $product->slug . '</loc>';
            $xml .= '<lastmod>' . $product->updated_at->toAtomString() . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.8</priority>';
            $xml .= '</url>';
        }

        // Catégories
        foreach ($categories as $category) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . '/marketplace/produits?category=' . $category->slug . '</loc>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.6</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }
}
