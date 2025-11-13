<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\JetonController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoryController;

use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\AbonnementController;

use App\Http\Controllers\CommercantController;
use App\Http\Controllers\ParrainageController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\CollaborationController;
use App\Http\Controllers\NotificationController;

Route::post('/login', [UserController::class, 'login']);
Route::post('register', [UserController::class, 'register']);

// Route::post('/broadcasting/aut', function () {
//     return \Auth::user();
// });

// Route::get('/', function () {
//     return view('welcome'); // Laravel va chercher resources/views/welcome.blade.php
// });
Route::get('/categories', [CategoryController::class, 'index']);
// Route::get('/produits/related/{produit}', [CategoryController::class, 'relatedProduct'])->name('categories.index');
    // Routes protégées
    // Route::get('produits', [ProduitController::class, 'index']);
    
    Route::middleware('auth:sanctum')->group(function () {

        
    Route::get('produits', [ProduitController::class, 'index']);




        
    Route::get('/conversations', [ChatController::class, 'conversations']);

    Route::get('user', [UserController::class, 'profile']);

    Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto']); // Nouvelle route
    Route::post('/updatePassword', [ProfileController::class, 'updatePassword']); // Nouvelle route
    Route::post('logout', [UserController::class, 'logout']);


    // Route::post('produits', [ProduitController::class, 'store'])->middleware('premium:product');


        Route::post('collaborations', [CollaborationController::class, 'store']);
        Route::post('collaborations/{id}', [CollaborationController::class, 'update']);
    Route::get('/collaborations', [CollaborationController::class, 'index'])->name('collaborations.index');
        
        // Route::post('abonnements', [AbonnementController::class, 'store']);
        Route::post('parrainages', [ParrainageController::class, 'store']);
        
        Route::post('/commercants', [CommercantController::class, 'create'])->name('commercant.store');

    Route::get('/commercant/produits', [CommercantController::class, 'produits'])->name('commercant.produits');
    Route::post('/commercant/produits', [CommercantController::class, 'storeProduit'])->name('commercant.produits.store')->middleware('premium:product');
    Route::delete('/commercant/produits/{produit}', [CommercantController::class, 'destroyProduit'])->name('commercant.produits.destroy');
    Route::get('/commercant/profil', [CommercantController::class, 'profil'])->name('commercant.profil');
    Route::post('/commercant/update', [CommercantController::class, 'updateProfil'])->name('commercant.profil.update');
    Route::post('/commercant/produits/{id}', [CommercantController::class, 'updateProduit'])->name('commercant.produits.update');
    // Route::get('/commercant/{commercant}', [CommercantController::class, 'show'])->name('commercant.show');
    Route::post('/commercant/{commercantId}/rate', [CommercantController::class, 'rate']);
    
    Route::post('/commercant/verify-email', [CommercantController::class, 'verifyEmail']);
    Route::post('/commercant/resend-verification', [CommercantController::class, 'resendVerification']);

    Route::put('/user/notifications', [UserController::class, 'updateNotifications'])->name('user.notifications.update');   


    
    Route::get('/user/badges', [UserController::class, 'badges'])->name('user.badges');
    Route::get('/user/parrainage', [UserController::class, 'getParrainage']);


    Route::post('/produits/{id}/favorite', [ProduitController::class, 'favorite']);
    Route::get('/produits/{produit}', [ProduitController::class, 'show'])->name('produits.show');


    Route::post('/parrainages/generateCode', [ParrainageController::class, 'generateCode']);
    Route::post('/parrainages/createCode', [ParrainageController::class, 'createCode']);
    // ... autres routes
        Route::post('/parrainages/register', [ParrainageController::class, 'registerParrainage']);
    Route::post('/parrainages/validate/{userId}', [ParrainageController::class, 'validateParrainage']);
    Route::get('/parrainages/dashboard', [ParrainageController::class, 'dashboard']);
    Route::get('/parrainages/niveaux', [ParrainageController::class, 'getAllNiveaux']);

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

    
});
    Route::get('/subscription/callback', [SubscriptionController::class, 'handleCallback'])->name('subscription.callback');




Route::get('/jeton/callback', [JetonController::class, 'handleCallback'])
    ->name('jeton.callback');

Route::get('/commercant/{commercant}', [CommercantController::class, 'show'])->name('commercant.show');



    Route::get('/profile/public/{id}', [ProfileController::class, 'publicProfile']);


Route::post('/public-record-view', [ProduitController::class, 'publicRecordView']); // Nouvelle route publique
Route::get('/public-produits/{produit}', [ProduitController::class, 'publicShow']); // Route publique

Route::get('/public-produits', [ProduitController::class, 'publicIndex']); // Nouvelle route publique

Route::get('/auth/google', [UserController::class, 'redirectToGoogle'])->name('google.login');

// Callback de Google (après connexion)
Route::get('/auth/google/callback', [UserController::class, 'handleGoogleCallback'])->name('google.callback');

    Route::get('/jeton_market/offers', [JetonController::class, 'index']);

    Route::post('/auth/google/native', [UserController::class, 'handleGoogleNative']);