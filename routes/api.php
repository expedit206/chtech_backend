<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\JetonController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WalletController;

use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\ReventeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\AbonnementController;
use App\Http\Controllers\ParrainageController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\ProduitUserController;
use App\Http\Controllers\ServiceUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProduitReviewController;
use App\Http\Controllers\ServiceReviewController;
use App\Http\Controllers\CategoryProduitController;
use App\Http\Controllers\CategoryServiceController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// Route::post('/broadcasting/aut', function () {
//     return \Auth::user();
// });

// Route::get('/', function () {
//     return view('welcome'); // Laravel va chercher resources/views/welcome.blade.php
// });
Route::get('/produits/categories', [CategoryProduitController::class, 'index']);
// Route::get('/produits/related/{produit}', [CategoryProduitController::class, 'relatedProduct'])->name('categories.index');
    // Routes protégées
    Route::get('produits', [ProduitController::class, 'index']);
    
          
    Route::post('/password/generate-token', [PasswordResetController::class, 'generateResetToken']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
    Route::post('/password/verify-token', [PasswordResetController::class, 'verifyToken']);

    Route::middleware('auth:sanctum')->group(function () {

        


        
    Route::get('produits', [ProduitController::class, 'index']);




        
    Route::get('/conversations', [ChatController::class, 'conversations']);

    Route::get('user', [UserController::class, 'profile']);

    Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto']); // Nouvelle route
    Route::post('/updatePassword', [ProfileController::class, 'updatePassword']); // Nouvelle route
    // Route::post('logout', [ProduitUserController::class, 'logout']);


    // Route::post('produits', [ProduitController::class, 'store'])->middleware('premium:product');


        Route::post('reventes/{id}', [ReventeController::class, 'store']);
        Route::put('reventes/{id}/updateStatus', [ReventeController::class, 'update']);
        Route::get('reventes/{id}/status', [ReventeController::class, 'status']);
    Route::get('/reventes', [ReventeController::class, 'index'])->name('reventes.index');
        
        

    Route::get('/user/mesProduits', [ProduitUserController::class, 'produits']);
    Route::post('/user/produits', [ProduitUserController::class, 'storeProduit']);
    Route::post('/user/delete/produit/{produit}', [ProduitUserController::class, 'destroyProduit']);
    // Route::post('/commercant/update', [ProduitUserController::class, 'updateProfil'])->name('commercant.profil.update');
    Route::post('/user/produits/{id}', [ProduitUserController::class, 'updateProduit']);
    // Route::get('/commercant/{commercant}', [ProduitUserController::class, 'show'])->name('commercant.show');
    // Route::post('/commercant/{commercantId}/rate', [ProduitUserController::class, 'rate']);
    
    // Route::post('/commercant/verify-email', [ProduitUserController::class, 'verifyEmail']);
    // Route::post('/commercant/resend-verification', [ProduitUserController::class, 'resendVerification']);

    // Route::put('/user/notifications', [ProduitUserController::class, 'updateNotifications'])->name('user.notifications.update');   


    
    Route::get('/user/badges', [UserController::class, 'badges'])->name('user.badges');


    Route::post('/produits/{id}/favorite', [FavoriteController::class, 'toggleProduitFavorite'])->name('toggleProduitFavorite');
    Route::get('/produits/{produit}', [ProduitController::class, 'show'])->name('produits.show');


    Route::post('/parrainages/generateCode', [ParrainageController::class, 'generateCode']);
    Route::post('/parrainages/createCode', [ParrainageController::class, 'createCode']);
    // ... autres routes
     
    Route::post('/parrainage/demander-verification', [ParrainageController::class, 'demanderVerificationEmail']);
    Route::post('/parrainage/verifier-email', [ParrainageController::class, 'verifierEmail']);
    
    // Pour les parrains
    Route::get('/parrainage/mes-parrainages', [ParrainageController::class, 'mesParrainages']);

    Route::post('/produits/{id}/boost', [ProduitController::class, 'boost']);


    Route::get('/stats', [StatsController::class, 'index']);

    Route::get('/chat/{receiverId}', [ChatController::class, 'index']);
    Route::post('/chat/{receiverId}', [ChatController::class, 'store']);
    Route::put('/messages/mark-all-as-read', [ChatController::class, 'markAllAsRead']);
    Route::put('/chat/message/{messageId}', [ChatController::class, 'update']);
Route::delete('/chat/message/{messageId}', [ChatController::class, 'destroy']);


Route::post('/upgrade-to-premium', [SubscriptionController::class, 'upgradeToPremium']);

  Route::get('/premium/transaction/pending', [SubscriptionController::class, 'getPendingTransaction']);
    Route::get('/premium/transaction/{id}/status', [SubscriptionController::class, 'checkTransactionStatus']);

    Route::get('/payment/status', [SubscriptionController::class, 'checkPaymentStatus']);
    Route::get('/transactions', [SubscriptionController::class, 'listTransactions']);
// routes/api.php

    Route::post('/updateProfile', [ProfileController::class, 'updateProfile']);

// Marketplace
Route::get('/jeton/market', [JetonController::class, 'index']);
Route::post('/jeton/market/buy/{offer_id}', [JetonController::class, 'buy']);

// Achat direct plateforme
Route::post('/jeton/purchase/platform', [JetonController::class, 'purchaseFromPlatform']);

// Callback
Route::get('/jeton/payment/callback', [JetonController::class, 'handleCallback']);

// Vérification et historique
Route::get('/jeton/transaction/{transaction_id}/status', [JetonController::class, 'checkTransactionStatus']);
Route::get('/jeton/transactions/history', [JetonController::class, 'userTransactions']);


    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::put('/wallets/{id}', [WalletController::class, 'update']);
    Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
    
    Route::post('/jeton_market/offer', [OfferController::class, 'store']);
    Route::get('/jeton_market/my-offers', [OfferController::class, 'myOffers']);
    Route::put('/jeton_market/updateOffer/{id}', [OfferController::class, 'updateOffer']);
    
    Route::delete('/jeton_market/deleteOffer/{id}', [OfferController::class, 'destroyOffer']);

    // Redirection vers Google
    
    
    Route::post('/record_view', [ProduitController::class, 'recordView']);

     Route::post('/token-store', [NotificationController::class, 'store']);
    Route::post('/test', [NotificationController::class, 'testNotification']);
    Route::delete('/token', [NotificationController::class, 'disableToken']);
    Route::get('/tokens', [NotificationController::class, 'getUserTokens']);

        // CRUD Services

    Route::get('/services/mes-services', [ServiceUserController::class, 'mesServices']);
    Route::post('/services', [ServiceUserController::class, 'store']);
    Route::post('/services/{id}', [ServiceUserController::class, 'update']);
    Route::delete('/services/{id}', [ServiceUserController::class, 'destroy']);
    Route::patch('/services/{id}/toggle-disponibilite', [ServiceUserController::class, 'toggleDisponibilite']);
    

        
Route::prefix('services')->group(function () {
    Route::post('/{id}/favorite', [FavoriteController::class, 'toggleServiceFavorite']);

        // Routes pour les avis
    Route::post('/{id}/reviews', [ServiceReviewController::class, 'storeServiceReview']);
    Route::put('/reviews/{reviewId}', [ServiceReviewController::class, 'update']);
    Route::delete('/reviews/{reviewId}', [ServiceReviewController::class, 'destroy']);
    Route::post('/reviews/{reviewId}/respond', [ServiceReviewController::class, 'respond']);
});

    Route::post('produits/{id}/reviews', [ProduitReviewController::class, 'storeProduitReview']);

    
    Route::prefix('marketplace')->group(function () {
        Route::get('/produits', [MarketplaceController::class, 'getProduits']);
        Route::get('/services', [MarketplaceController::class, 'getServices']);
        Route::get('/search', [MarketplaceController::class, 'globalSearch']);
    });




        Route::prefix('interactions')->group(function () {
        Route::post('/', [InteractionController::class, 'store']);
        Route::get('/', [InteractionController::class, 'index']);
        Route::get('/preferred-categories', [InteractionController::class, 'preferredCategories']);
    });

        // Routes pour les badges
    Route::prefix('badges')->group(function () {
        Route::get('/count', [BadgeController::class, 'getUnreadCount']);
        Route::post('/mark-read', [BadgeController::class, 'markAllAsRead']);
        Route::post('/sync', [BadgeController::class, 'syncBadges']);
    });
    
    // Routes pour les messages
    Route::prefix('messages')->group(function () {
        Route::put('/mark-all-as-read', [ChatController::class, 'markAllAsRead']);
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
    });
    
    // Routes pour les reventes
    Route::prefix('reventes')->group(function () {
        Route::get('/unread-count', [ReventeController::class, 'getUnreadCount']);
        Route::put('/mark-all-as-read', [ReventeController::class, 'markAllAsRead']);
    });
    
    // Routes pour les parrainages
    Route::prefix('parrainages')->group(function () {
        Route::get('/unread-count', [ParrainageController::class, 'getUnreadCount']);
        Route::put('/mark-all-as-read', [ParrainageController::class, 'markAllAsRead']);
    });
    
});

// routes/api.php




Route::get('/services/categories', [CategoryServiceController::class, 'index']);


Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/search', [ServiceController::class, 'search']);
Route::get('/services/{id}', [ServiceController::class, 'show']);


    Route::get('/subscription/callback', [SubscriptionController::class, 'handleCallback'])->name('subscription.callback');




Route::get('/jeton/callback', [JetonController::class, 'handleCallback'])
    ->name('jeton.callback');

Route::get('/commercant/{commercant}', [ProduitUserController::class, 'show'])->name('commercant.show');



    Route::get('/profile/public/{id}', [ProfileController::class, 'publicProfile']);


Route::post('/public-record-view', [ProduitController::class, 'publicRecordView']); // Nouvelle route publique
Route::get('/public-produits/{produit}', [ProduitController::class, 'publicShow']); // Route publique

Route::get('/public-produits', [ProduitController::class, 'publicIndex']); // Nouvelle route publique

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('google.login');

// Callback de Google (après connexion)
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');

    Route::get('/jeton_market/offers', [JetonController::class, 'index']);

    Route::post('/auth/google/native', [ProduitUserController::class, 'handleGoogleNative']);

    
Route::prefix('services')->group(function () {
    Route::get('/{id}', [ServiceController::class, 'show']);
    
    Route::get('/{id}/getReviews', [ServiceReviewController::class, 'index']);
});

// routes/api.php

Route::prefix('produits')->group(function () {
    // ... routes existantes ...
    
    // Routes pour les avis
    Route::get('/{id}/getReviews', [ProduitReviewController::class, 'index']);
});