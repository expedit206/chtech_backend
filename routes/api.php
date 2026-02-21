<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\JetonController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReventeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ParrainageController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\ProduitUserController;
use App\Http\Controllers\ServiceUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProduitReviewController;
use App\Http\Controllers\ServiceReviewController;
use App\Http\Controllers\CategoryProduitController;
use App\Http\Controllers\CategoryServiceController;
use App\Http\Controllers\ProduitController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/password/generate-token', [PasswordResetController::class, 'generateResetToken']);
Route::post('/password/verify-token', [PasswordResetController::class, 'verifyToken']);
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);

// Social Auth
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');

// Marketplace & Global Search
Route::prefix('marketplace')->group(function () {
    Route::get('/produits', [MarketplaceController::class, 'getProduits']);
    Route::get('/services', [MarketplaceController::class, 'getServices']);
    Route::get('/search', [MarketplaceController::class, 'globalSearch']);
});

// Categories
Route::get('/produits/categories', [CategoryProduitController::class, 'index']);
Route::get('/services/categories', [CategoryServiceController::class, 'index']);

// Blog Routes
Route::prefix('blog')->group(function () {
    Route::get('/posts', [\App\Http\Controllers\BlogController::class, 'index']);
    Route::get('/posts/search', [\App\Http\Controllers\BlogController::class, 'search']);
    Route::get('/posts/{slug}', [\App\Http\Controllers\BlogController::class, 'show']);
    Route::get('/posts/{slug}/comments', [\App\Http\Controllers\BlogController::class, 'getComments']);
});

// Public Product/Service Access
Route::get('/produits/{produit}', [ProduitController::class, 'show'])->name('produits.show');
Route::post('/public-record-view', [ProduitController::class, 'publicRecordView']);

Route::prefix('services')->group(function () {
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/search', [ServiceController::class, 'search']);
    Route::get('/{id}', [ServiceController::class, 'show']);
    Route::get('/{id}/getReviews', [ServiceReviewController::class, 'index']);
});

Route::prefix('produits')->group(function () {
    Route::get('/{produit}', [ProduitController::class, 'show'])->name('produits.show');

    Route::get('/{id}/getReviews', [ProduitReviewController::class, 'index']);
});

// Public Profiles
Route::get('/profile/public/{id}', [ProfileController::class, 'publicProfile']);

// External callbacks
Route::get('/subscription/callback', [SubscriptionController::class, 'handleCallback'])->name('subscription.callback');
Route::get('/jeton/callback', [JetonController::class, 'handleCallback'])->name('jeton.callback');


/*
|--------------------------------------------------------------------------
| Protected Routes (Auth Required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Panier
    Route::prefix('cart')->group(function () {
        Route::get('/', [\App\Http\Controllers\CartController::class, 'getCart']);
        Route::post('/add', [\App\Http\Controllers\CartController::class, 'addToCart']);
        Route::post('/remove', [\App\Http\Controllers\CartController::class, 'removeFromCart']);
        Route::post('/update', [\App\Http\Controllers\CartController::class, 'updateQuantity']);
        Route::post('/clear', [\App\Http\Controllers\CartController::class, 'clearCart']);
    });

    // User Profile & Settings
    Route::get('/user', [UserController::class, 'profile']);
    Route::post('/updateProfile', [ProfileController::class, 'updateProfile']);
    Route::post('/updatePassword', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto']);
    Route::get('/user/badges', [UserController::class, 'badges'])->name('user.badges');
    Route::get('/stats', [StatsController::class, 'index']);

    // Chat & Messaging
    Route::prefix('messages')->group(function () {
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
        Route::put('/mark-all-as-read', [ChatController::class, 'markAllAsRead']);
    });
    Route::get('/conversations', [ChatController::class, 'conversations']);
    Route::get('/chat/{receiverId}', [ChatController::class, 'index']);
    Route::post('/chat/{receiverId}', [ChatController::class, 'store']);
    Route::put('/chat/message/{messageId}', [ChatController::class, 'update']);
    Route::delete('/chat/message/{messageId}', [ChatController::class, 'destroy']);

    // Products (Inventory Management)
    Route::prefix('user')->group(function () {
        Route::get('/mesProduits', [ProduitUserController::class, 'produits']);
        Route::post('/produits', [ProduitUserController::class, 'storeProduit']);
        Route::post('/produits/{id}', [ProduitUserController::class, 'updateProduit']);
        Route::post('/delete/produit/{produit}', [ProduitUserController::class, 'destroyProduit']);
    });

    // Favorites (General)
    Route::get('/favorites', [FavoriteController::class, 'index']);

    // Services (Inventory Management)
    Route::prefix('services')->group(function () {
        Route::get('/mes-services', [ServiceUserController::class, 'mesServices']);
        Route::post('/', [ServiceUserController::class, 'store']);
        Route::post('/{id}', [ServiceUserController::class, 'update']);
        Route::delete('/{id}', [ServiceUserController::class, 'destroy']);
        Route::patch('/{id}/toggle-disponibilite', [ServiceUserController::class, 'toggleDisponibilite']);
        
        // Service-specific Favorites & Reviews
        Route::post('/{id}/favorite', [FavoriteController::class, 'toggleServiceFavorite']);
        Route::post('/{id}/reviews', [ServiceReviewController::class, 'storeServiceReview']);
    });

    // Product Actions
    Route::prefix('produits')->group(function () {
        Route::post('/{id}/favorite', [FavoriteController::class, 'toggleProduitFavorite'])->name('toggleProduitFavorite');
        Route::post('/{id}/reviews', [ProduitReviewController::class, 'storeProduitReview']);
        Route::get('/{id}/counts', [InteractionController::class, 'getProductInteraction']);
    });
    Route::post('/record_view', [ProduitController::class, 'recordView']);

    // Reventes (Resales)
    Route::prefix('reventes')->group(function () {
        Route::get('/', [ReventeController::class, 'index'])->name('reventes.index');
        Route::get('/unread-count', [ReventeController::class, 'getUnreadCount']);
        Route::put('/mark-all-as-read', [ReventeController::class, 'markAllAsRead']);
        Route::get('/{id}/status', [ReventeController::class, 'status']);
        Route::post('/{id}', [ReventeController::class, 'store']);
        Route::put('/{id}/updateStatus', [ReventeController::class, 'update']);
    });

    // Promotions
    Route::prefix('promotions')->group(function () {
        Route::post('/create', [PromotionController::class, 'create']);
        Route::get('/active/{productId}', [PromotionController::class, 'getActive']);
        Route::post('/{promotionId}/stop', [PromotionController::class, 'stop']);
    });

    // Jeton Market & Transactions
    Route::prefix('jeton')->group(function () {
        Route::get('/market', [JetonController::class, 'index']);
        Route::post('/market/buy/{offer_id}', [JetonController::class, 'buy']);
        Route::post('/purchase/platform', [JetonController::class, 'purchaseFromPlatform']);
        Route::get('/transaction/{transaction_id}/status', [JetonController::class, 'checkTransactionStatus']);
        Route::get('/transactions/history', [JetonController::class, 'userTransactions']);
    });
    Route::get('/jeton_market/offers', [JetonController::class, 'index']);
    
    Route::prefix('jeton_market')->group(function () {
        Route::post('/offer', [OfferController::class, 'store']);
        Route::get('/my-offers', [OfferController::class, 'myOffers']);
        Route::put('/updateOffer/{id}', [OfferController::class, 'updateOffer']);
        Route::delete('/deleteOffer/{id}', [OfferController::class, 'destroyOffer']);
    });

    // Wallet
    Route::apiResource('wallets', WalletController::class);

    // Subscriptions & Premium
    Route::post('/upgrade-to-premium', [SubscriptionController::class, 'upgradeToPremium']);
    Route::get('/premium/transaction/pending', [SubscriptionController::class, 'getPendingTransaction']);
    Route::get('/premium/transaction/{id}/status', [SubscriptionController::class, 'checkTransactionStatus']);

    // Team & Parrainage
    Route::prefix('parrainages')->group(function () {
        Route::get('/unread-count', [ParrainageController::class, 'getUnreadCount']);
        Route::put('/mark-all-as-read', [ParrainageController::class, 'markAllAsRead']);
        Route::post('/createCode', [ParrainageController::class, 'createCode']);
    });
    Route::prefix('parrainage')->group(function () {
        Route::post('/demander-verification', [ParrainageController::class, 'demanderVerificationEmail']);
        Route::post('/verifier-email', [ParrainageController::class, 'verifierEmail']);
        Route::get('/mes-parrainages', [ParrainageController::class, 'mesParrainages']);
    });

    // Interactions
    Route::prefix('interactions')->group(function () {
        Route::get('/', [InteractionController::class, 'index']);
        Route::post('/', [InteractionController::class, 'store']);
        Route::get('/preferred-categories', [InteractionController::class, 'preferredCategories']);
    });

    // Badges & Notification Tokens
    Route::prefix('badges')->group(function () {
        Route::get('/count', [BadgeController::class, 'getUnreadCount']);
        Route::post('/mark-read', [BadgeController::class, 'markAllAsRead']);
        Route::post('/sync', [BadgeController::class, 'syncBadges']);
    });
    
    Route::post('/token-store', [NotificationController::class, 'store']);
    Route::delete('/token/{id}', [NotificationController::class, 'destroy']);
    Route::get('/tokens', [NotificationController::class, 'getUserTokens']);
    Route::post('/test-notification', [NotificationController::class, 'TestNotification']);

    // Admin/Service Review Responses
    Route::put('/service-reviews/{reviewId}', [ServiceReviewController::class, 'update']);
    Route::delete('/service-reviews/{reviewId}', [ServiceReviewController::class, 'destroy']);
    Route::post('/service-reviews/{reviewId}/respond', [ServiceReviewController::class, 'respond']);

    // Blog Interactions
    Route::prefix('blog')->group(function () {
        Route::post('/posts/{slug}/comments', [\App\Http\Controllers\BlogController::class, 'storeComment']);
        Route::post('/posts/{slug}/like', [\App\Http\Controllers\BlogController::class, 'toggleLike']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index']);
        
        // Users
        Route::prefix('users')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'show']);
            Route::put('/{id}/role', [\App\Http\Controllers\Admin\AdminUserController::class, 'updateRole']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy']);
        });

        // Products
        Route::prefix('produits')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminProductController::class, 'index']);
            Route::patch('/{id}/toggle-status', [\App\Http\Controllers\Admin\AdminProductController::class, 'toggleStatus']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminProductController::class, 'destroy']);
        });

        // Services
        Route::prefix('services')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminServiceController::class, 'index']);
            Route::patch('/{id}/toggle-status', [\App\Http\Controllers\Admin\AdminServiceController::class, 'toggleStatus']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminServiceController::class, 'destroy']);
        });

        // Categories
        Route::prefix('categories')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminCategoryController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\AdminCategoryController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\AdminCategoryController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminCategoryController::class, 'destroy']);
        });
        // Finance
        Route::prefix('finance')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'index']);
            Route::get('/transactions', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'transactions']);
        });

        // Chat Broadcast
        Route::post('/chat/broadcast', [\App\Http\Controllers\Admin\AdminChatController::class, 'broadcast']);

        // Blog Management
        Route::prefix('blog')->group(function () {
            Route::get('/posts', [\App\Http\Controllers\BlogController::class, 'adminIndex']);
            Route::post('/posts', [\App\Http\Controllers\BlogController::class, 'store']);
            Route::post('/posts/{id}', [\App\Http\Controllers\BlogController::class, 'update']);
            Route::delete('/posts/{id}', [\App\Http\Controllers\BlogController::class, 'destroy']);
            Route::patch('/posts/{id}/toggle-publish', [\App\Http\Controllers\BlogController::class, 'togglePublish']);
            Route::delete('/comments/{id}', [\App\Http\Controllers\BlogController::class, 'deleteComment']);
        });

        // Partenaires
        Route::prefix('partenaires')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PartenaireController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\PartenaireController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\PartenaireController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\PartenaireController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Admin\PartenaireController::class, 'destroy']);
        });
    });

    });

