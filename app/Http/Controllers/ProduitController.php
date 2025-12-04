<?php
// app/Http/Controllers/ProduitController.php
namespace App\Http\Controllers;

use App\Models\user;
use App\Models\Boost;
use Ramsey\Uuid\Uuid;
use App\Models\Produit;
use App\Models\ProductView;
use Illuminate\Http\Request;
use App\Jobs\RecordProductView;
use App\Models\ProductFavorite;
use App\Models\JetonTransaction;
use App\Models\JetonsTransaction;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class ProduitController extends Controller
{
   public function index(Request $request)
{
    $user = \Auth::user(); // Utilisateur connecté
    $sort = $request->query('sort', 'default');
    $perPage = $request->query('per_page', 5) === 'all' ? null : (int)$request->query('per_page', 10);
    $search = $request->query('search');
    $category = $request->query('category');
    $prixMin = $request->query('prix_min');
    $prixMax = $request->query('prix_max');
    $ville = $request->query('ville');
    $collaboratif = filter_var($request->query('collaboratif'), FILTER_VALIDATE_BOOLEAN, ['default' => null]);
    $page = (int)$request->query('page', 1);

    $query = Produit::query()
        ->with(['user', 'category', 'counts', 'boosts' => function ($q) {
            $q->where('statut', 'actif');
        }])
        ->leftJoin('product_counts', 'product_counts.produit_id', '=', 'produits.id')
        ->leftJoin('boosts', function ($join) {
            $join->on('boosts.produit_id', '=', 'produits.id')
                ->where('boosts.statut', 'actif');
        });

    // Produits vus et favoris par l'utilisateur
    $viewedProductIds = $user ? ProductView::where('user_id', $user->id)->pluck('produit_id')->toArray() : [];
    $favoriteProductIds = $user ? ProductFavorite::where('user_id', $user->id)->pluck('produit_id')->toArray() : [];

    // Récupérer les catégories favorites de l'utilisateur
    $favoriteCategoryIds = $user
        ? ProductFavorite::where('user_id', $user->id)
            ->join('produits', 'produits.id', '=', 'product_favorites.produit_id')
            ->pluck('produits.category_id')
            ->unique()
            ->toArray()
        : [];

    // Construire la clause IN avec quotes pour UUID
    $favoriteCategoryList = !empty($favoriteCategoryIds)
        ? "'" . implode("','", $favoriteCategoryIds) . "'"
        : "'0'"; // valeur par défaut (ne match rien)

        
        
        
if ($search) {
        $searchTerms = array_filter(explode(' ', trim($search))); // Supprime les termes vides
        // $searchTerms = array_filter( trim($search)); // Supprime les termes vides
        $query->where(function ($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $term = trim($term);
                if ($term) {
                    $q->where(function ($subQ) use ($term) {
                        // Filtre initial avec LIKE pour réduire le jeu de données
                        $subQ->where('produits.nom', 'like', "%{$term}%")
                            ->orWhere('produits.description', 'like', "%{$term}%")
                            ->orWhereHas('category', fn($cat) => $cat->where('nom', 'like', "%{$term}%"))
                            ->orWhereHas('user', fn($com) =>
                                $com->where('nom', 'like', "%{$term}%")
                                    ->orWhere('description', 'like', "%{$term}%")
                            );
                    })->orWhere(function ($subQ) use ($term) {
                        // Filtre flou avec Levenshtein (seuil de 2)
                        $subQ
                        ->whereRaw("LEVENSHTEIN(LOWER(produits.nom), LOWER(?)) <= 4", [$term])
                            ->orWhereRaw("LEVENSHTEIN(LOWER(produits.description), LOWER(?)) <= 4", [$term])
                            ;
                    });

                }
            }
            // return response()->json(['term'=>$searchTerms], 403);
        });
//         $distance = DB::selectOne("SELECT LEVENSHTEIN(LOWER(?), LOWER(?)) AS dist", ['dil', 'dil2']);
// return response()->json($distance);
    }

    
    if ($category) $query->where('category_id', $category);
    if ($prixMin) $query->where('prix', '>=', (float)$prixMin);
    if ($prixMax) $query->where('prix', '<=', (float)$prixMax);
    if ($ville) $query->where('ville', $ville);
    if ($request->collaboratif) $query->where('collaboratif', $collaboratif);

    // Calcul du score révisé : plus de poids à la nouveauté
   $query->select('produits.*')
    ->selectRaw("
        COALESCE(product_counts.views_count, 0) as raw_views_count,
        COALESCE(product_counts.favorites_count, 0) as favorites_count,
        LEAST((
            0.5 * LEAST(COALESCE(product_counts.views_count, 0), 1000) / 1000 + -- normalisé sur 1000
            0.10 * LEAST(COALESCE(product_counts.favorites_count, 0), 100) / 100 + -- normalisé sur 100
            0.30 * (
                CASE WHEN boosts.id IS NOT NULL AND boosts.target_views > 0 THEN
                    LEAST(1, (boosts.target_views - COALESCE(product_counts.views_count, 0)) / boosts.target_views)
                ELSE 0 END
            ) +
            0.30 * (1 / (DATEDIFF(NOW(), produits.created_at)/30  + 1)) + -- max = 0.35
            0.25 * (
                CASE WHEN produits.category_id IN ($favoriteCategoryList)
                THEN 1 ELSE 0 END
            )
        ), 1) as score
    ")
    ->withCasts(['score' => 'float']);

    // Pénalité pour produits déjà vus
    if (!empty($viewedProductIds)) {
        $query->addSelect(DB::raw("CASE WHEN produits.id IN (" . implode(',', array_map('intval', $viewedProductIds)) . ") THEN -1 ELSE 0 END as view_penalty"))
            ->orderByRaw('score + view_penalty DESC');
    } else {
        $query->orderBy('score', 'desc');
    }

    // Tri supplémentaire
 switch ($sort) {
    case 'favorites':
        // Filtrer uniquement les produits favoris de l'utilisateur connecté
        $userId = $user ? $user->id : null;
            $query->whereHas('favorites', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        
        break;

    default:
        // Tri normal par score puis favoris
        $query->orderBy('score', 'desc')
              ->orderBy('favorites_count', 'desc');
        break;
}

    // Pagination
    $produits = $perPage === null
        ? $query->get()
        : $query->paginate($perPage, ['*'], 'page', $page);

    // Ajouter propriétés dynamiques
    $collection = $perPage === null ? $produits : $produits->getCollection();
    $collection->each(function ($produit) use ($user, $favoriteProductIds) {
        $produit->is_favorited_by = $user && in_array($produit->id, $favoriteProductIds);

        // Vérifier si le boost est encore actif
        $boost = $produit->boosts->first();
        $produit->boosted = $boost && ($produit->counts->views_count ?? 0) < ($boost->target_views ?? 0);
        unset($produit->boosts);
    });

    // Désactiver les boosts atteints
    $activeBoosts = Boost::where('statut', 'actif')->get();
    foreach ($activeBoosts as $boost) {
        $produit = Produit::with('counts')->find($boost->produit_id);
        if ($produit && $produit->counts && $produit->counts->views_count >= ($boost->target_views ?? 0)) {
            $boost->update(['statut' => 'inactif']);
        }
    }

    return response()->json($produits);
}
    public function publicIndex(Request $request)
    {
        $sort = $request->query('sort', 'default');
        $perPage = $request->query('per_page', 5) === 'all' ? null : (int)$request->query('per_page', 10);
        $search = $request->query('search');
        $category = $request->query('category');
        $prixMin = $request->query('prix_min');
        $prixMax = $request->query('prix_max');
        $ville = $request->query('ville');
        $collaboratif = filter_var($request->query('collaboratif'), FILTER_VALIDATE_BOOLEAN, ['default' => null]);
        $page = (int)$request->query('page', 1);

        // Construction de la requête
        $query = Produit::query()
            ->with(['user', 'category', 'counts', 'boosts' => function ($q) {
                $q->where('statut', 'actif');
            }])
            ->leftJoin('product_counts', 'product_counts.produit_id', '=', 'produits.id')
            ->leftJoin('boosts', function ($join) {
                $join->on('boosts.produit_id', '=', 'produits.id')
                    ->where('boosts.statut', 'actif');
            })
            ;
            
            // Filtres (accessibles à tous)
            if ($search) {
                $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($category) $query->where('category_id', $category);
        if ($prixMin) $query->where('prix', '>=', (float)$prixMin);
        if ($prixMax) $query->where('prix', '<=', (float)$prixMax);
        if ($ville) $query->where('ville', $ville);
        // if ($collaboratif !== null) $query->where('collaboratif', $collaboratif);
        if ($request->collaboratif) $query->where('collaboratif', $collaboratif);

        // return response()->json($query->get());

        // Calcul du score (basé sur données publiques)
    $query->select('produits.*')
    ->selectRaw("
        COALESCE(product_counts.views_count, 0) as raw_views_count,
        COALESCE(product_counts.favorites_count, 0) as favorites_count,
        LEAST((
            0.20 * LEAST(COALESCE(product_counts.views_count, 0), 1000) / 1000 + -- normalisé sur 1000
            0.15 * LEAST(COALESCE(product_counts.favorites_count, 0), 100) / 100 + -- normalisé sur 100
            0.25 * (
                CASE WHEN boosts.id IS NOT NULL AND boosts.target_views > 0 THEN
                    LEAST(1, (boosts.target_views - COALESCE(product_counts.views_count, 0)) / boosts.target_views)
                ELSE 0 END
            ) +
            0.40 * (1 / (DATEDIFF(NOW(), produits.created_at)/30 + 1)) -- priorité aux récents (réparti 0.45 + 0.15)
        ), 1) as score
    ")
    ->withCasts(['score' => 'float']);


        // Tri par score par défaut (pas de pénalité vue sans utilisateur)
        $query->orderBy('score', 'desc');

        // Tri supplémentaire
        switch ($sort) {
            case 'popular':
                $query->orderBy('raw_views_count', 'desc');
                break;
            case 'favorites':
                $query->orderBy('favorites_count', 'desc')->orderBy('score', 'desc');
                break;
            default:
                $query->orderBy('score', 'desc')->orderBy('favorites_count', 'desc');
                break;
        }

        // Pagination
        $produits = $perPage === null
            ? $query->get()
            : $query->paginate($perPage, ['*'], 'page', $page);
        // return response()->json($query->get());

        // Ajouter propriétés non dépendantes de l'utilisateur
        $collection = $perPage === null ? $produits : $produits->getCollection();
        $collection->each(function ($produit) {
            // Vérifier si le boost est encore actif
            $boost = $produit->boosts->first();
            $produit->boosted = $boost && ($produit->counts->views_count ?? 0) < ($boost->target_views ?? 0);
            unset($produit->boosts);
        });

        // Désactiver les boosts atteints
        $activeBoosts = Boost::where('statut', 'actif')->get();
        foreach ($activeBoosts as $boost) {
            $produit = Produit::with('counts')->find($boost->produit_id);
            if ($produit && $produit->counts && $produit->counts->views_count >= ($boost->target_views ?? 0)) {
                $boost->update(['statut' => 'inactif']);
            }
        }

        return response()->json($produits);
    }





    public function show($id): JsonResponse
    {
        try {
            $produit = Produit::with([
                'user:id,nom,email,telephone,photo,created_at',
                'category:id,nom',
                'reviews.user:id,nom,photo'
            ])
            ->findOrFail($id);

            // Incrémenter le compteur de vues
            // $produit->increment('views_count');

            // Produits similaires (même catégorie)
            $similarProduits = Produit::with(['user', 'category'])
                ->where('category_id', $produit->category_id)
                ->where('id', '!=', $produit->id)
                ->limit(6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'produit' => $produit,
                    'similar_produits' => $similarProduits,
                    'statistics' => [
                        'average_rating' => $produit->note_moyenne,
                        'total_reviews' => $produit->nombre_avis
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 404);
        }
    }


public function publicShow($id, Request $request)
    {
        // Récupérer le produit sans dépendre de l'utilisateur
        $produit = Produit::with(['user', 'category'])
            ->with('counts')
            ->findOrFail($id);

        // Ajouter uniquement les propriétés publiques
        $produit->user->rating = $produit->user->average_rating; // Attribut calculé
        $produit['boosted_until'] = $produit->boosts->first()?->end_date;
        $produit->is_favorited_by = false; // Par défaut pour les non-connectés

        return response()->json(['produit' => $produit->load('counts')]);
    }

   public function publicRecordView(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:produits,id',
        ]);

        $produitId = $validated['product_id'];
        $produit = Produit::findOrFail($produitId);

        // Incrémenter views_count pour les non-connectés
        $produit->counts()->updateOrCreate(
            ['produit_id' => $produitId],
            ['views_count' => \DB::raw('views_count + 1')]
        );

        return response()->json(['message' => 'Vue enregistrée']);
    }

    public function recordView(Request $request)
    {

        $validated = $request->validate([
            'product_id' => 'required|exists:produits,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        // return response()->json(['message' => $request->all()]);


        $produitId = $validated['product_id'];
        $userId = $validated['user_id'] ?? null;
        $produit = Produit::findOrFail($produitId);

        if (!$request->user()) {

            $produit->counts()->updateOrCreate(
                ['produit_id' => $produitId],
                ['views_count' => \DB::raw('views_count + 1')]
            );

            return response()->json([
                'message' => 'Vue enregistrée1',
                'user' => $request->all()
            ]);
        }
        // Vérifier si l'utilisateur a déjà vu ce produit
        $existingView = ProductView::where('produit_id', $produitId)
            ->where('user_id', $userId)
            ->first();

        if (!$existingView) {
            // Nouvelle vue, incrémenter views_count et enregistrer dans product_views
            $produit->counts()->updateOrCreate(
                ['produit_id' => $produitId],
                ['views_count' => \DB::raw('views_count + 1')]
            );

            ProductView::create([
                'produit_id' => $produitId,
                'user_id' => $userId,
            ]);

            return response()->json(['message' => 'Vue enregistrée']);
        } else {
            // Vue déjà enregistrée
            return response()->json(['message' => 'Vue déjà enregistrée'], 200);
        }
    }


    


    public function Favorite($id, Request $request)
    {
        // Validation de l'ID
        $produit = Produit::findOrFail($id);

        // Récupérer l'utilisateur authentifié
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Connexion requise'], 401);
        }

        $favorite = ProductFavorite::where('produit_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($favorite) {
            // Supprimer le favori
            $favorite->delete();

            $produit->counts()->updateOrCreate(
                ['produit_id' => $id],
                ['favorites_count' => DB::raw('favorites_count - 1')]
            );
            $message = 'Produit retiré des favoris';
            $isFavoritedBy = false;
        } else {
            // Ajouter le favori
            ProductFavorite::create([
                'produit_id' => $id,
                'user_id' => $user->id,
            ]);

            $produit->counts()->updateOrCreate(
                ['produit_id' => $id],
                ['favorites_count' => DB::raw('favorites_count + 1')]
            );
            $message = 'Produit ajouté aux favoris';
            $isFavoritedBy = true;
        }

        // Recharger les données du produit avec les counts mis à jour
        $updatedProduit = Produit::with('counts')->find($id);
        $updatedProduit->is_favorited_by = $isFavoritedBy; // Ajouter l'état mis à jour

        return response()->json([
            'message' => $message,
            'produit' => $updatedProduit, // Retourner le produit mis à jour avec is_favorited_by
        ]);
    }


    public function boost(Request $request, $id)
    {
        $produit = Produit::findOrFail($id);
        $user = $request->user()->load('user');

        if ($produit->user_id !== $user->user?->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifier s'il existe déjà un boost actif
        $activeBoost = Boost::where('produit_id', $id)
            ->where('statut', 'actif')
            ->first();

        if ($activeBoost) {
            return response()->json(['message' => 'Un boost est déjà actif pour ce produit'], 400);
        }

        $targetViews = $request->input('target_views', 100); // Par défaut 100 vues

        if (!in_array($targetViews, [100, 500, 1000, 5000, 10000])) {
            return response()->json(['message' => 'Objectif de vues invalide'], 400);
        }

        $viewFactors = [100 => 5, 500 => 15, 1000 => 25, 5000 => 50, 10000 => 75];
        $coutJetons = $viewFactors[$targetViews];
        $coutJetons = max(5, $coutJetons);

        if ($user->jetons < $coutJetons) {
            return response()->json(['message' => 'Pas assez de Jetons'], 400);
        }

        $boost = Boost::create([
            'user_id' => $request->user()->id,
            'produit_id' => $id,
            'type' => 'produit',
            'statut' => 'actif',
            'cout_jetons' => $coutJetons,
            'target_views' => $targetViews,
        ]);

        $user->jetons -= $coutJetons;
        $user->save();

        JetonTransaction::create([
            'user_id' => $request->user()->id,
            'type' => 'depense',
            'montant' => -$coutJetons,
            'methode_paiement' => 'Espace_cameroun',
            'statut' => 'validé',
            'date_transaction' => now('Africa/Douala'),
            'nombre_jetons' => $coutJetons,
            'description' => "Dépense de {$coutJetons} Jetons pour booster le produit #{$id} avec un objectif de {$targetViews} vues",
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Produit boosté avec un objectif de {$targetViews} vues au coût de {$coutJetons} Jetons",
            'data' => $boost,
        ]);
    }
}