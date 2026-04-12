<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CategoryProduitController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParrainageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProduitReviewController;
use App\Http\Controllers\ProduitUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReventeController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::post('/login', [AuthController::class, 'login']);
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
    Route::get('/search', [MarketplaceController::class, 'globalSearch']);
});

// Categories
Route::get('/produits/categories', [CategoryProduitController::class, 'index']);
Route::get('/promotions/active-event', [\App\Http\Controllers\Admin\AdminProductPromotionController::class, 'getActiveEvent']);

// Blog Routes
Route::prefix('blogs')->group(function () {
    Route::get('/posts', [BlogController::class, 'index']);
    Route::get('/posts/search', [BlogController::class, 'search']);
    Route::get('/posts/{slug}', [BlogController::class, 'show']);
    Route::get('/posts/{slug}/comments', [BlogController::class, 'getComments']);
});

// Public Product/Service Access
Route::get('/produits/{produit}', [ProduitController::class, 'show'])->name('produits.show');


Route::prefix('produits')->group(function () {
    Route::get('/{produit}', [ProduitController::class, 'show'])->name('produits.show');
    Route::get('/{id}/similar', [ProduitController::class, 'getSimilarProducts']);
    Route::get('/{id}/shop', [ProduitController::class, 'getShopProducts']);

    Route::get('/{id}/getReviews', [ProduitReviewController::class, 'index']);
});

// Public Profiles
Route::get('/profile/public/{id}', [ProfileController::class, 'publicProfile']);

// External callbacks
Route::get('/subscription/callback', [SubscriptionController::class, 'handleCallback'])->name('subscription.callback');


/*
|--------------------------------------------------------------------------
| Protected Routes (Auth Required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {


    // User Profile & Settings
    Route::get('/user', [UserController::class, 'profile']);
    Route::post('/updateProfile', [ProfileController::class, 'updateProfile']);
    Route::post('/updatePassword', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto']);
    Route::post('/profile/cover', [ProfileController::class, 'updateProfileCover']);
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


    // Product Actions
    Route::prefix('produits')->group(function () {
        Route::post('/{id}/favorite', [FavoriteController::class, 'toggleProduitFavorite'])->name('toggleProduitFavorite');
        Route::post('/{id}/reviews', [ProduitReviewController::class, 'storeProduitReview']);
        Route::get('/{id}/counts', [InteractionController::class, 'getProductInteraction']);
    });



    // Promotions
    Route::prefix('promotions')->group(function () {
        Route::post('/create', [PromotionController::class, 'create']);
        Route::get('/active/{productId}', [PromotionController::class, 'getActive']);
        Route::post('/{promotionId}/stop', [PromotionController::class, 'stop']);
    });

    // --- Système de Jetons (Misé en sourdine / Supprimé) ---
    // Les routes jeton et jeton_market ont été retirées.

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

    // Notifications Internes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'deleteNotification']);
    });


    // Blog Interactions
    Route::prefix('blog')->group(function () {
        Route::post('/posts/{slug}/comments', [\App\Http\Controllers\BlogController::class, 'storeComment']);
        Route::post('/posts/{slug}/like', [\App\Http\Controllers\BlogController::class, 'toggleLike']);
    });

    // Vendeur Onboarding
    Route::prefix('vendeur-onboarding')->group(function () {
        Route::post('/apply', [\App\Http\Controllers\SupplierOnboardingController::class, 'store']);
        Route::get('/status', [\App\Http\Controllers\SupplierOnboardingController::class, 'status']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/seller-stats', [App\Http\Controllers\OrderController::class, 'sellerStats']);
        Route::get('/seller-finance', [App\Http\Controllers\OrderController::class, 'sellerFinance']);
        Route::post('/admin-create', [App\Http\Controllers\OrderController::class, 'createFromAdmin']);
        Route::get('/{id}/facture', [App\Http\Controllers\FactureController::class, 'download']);
        Route::post('/', [App\Http\Controllers\OrderController::class, 'store']);
        Route::get('/', [App\Http\Controllers\OrderController::class, 'index']);
        Route::get('/seller', [App\Http\Controllers\OrderController::class, 'sellerOrders']);
        Route::put('/{id}/status', [App\Http\Controllers\OrderController::class, 'updateStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Vendeur Requests
        Route::prefix('vendeur-requests')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SupplierRequestController::class, 'index']);
            Route::put('/{id}', [\App\Http\Controllers\Admin\SupplierRequestController::class, 'update']);
        });

        // Product Promotions Management
        Route::prefix('product-promotions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminProductPromotionController::class, 'index']);
            Route::patch('/{id}/toggle', [\App\Http\Controllers\Admin\AdminProductPromotionController::class, 'togglePromotion']);
            Route::get('/event', [\App\Http\Controllers\Admin\AdminProductPromotionController::class, 'getActiveEvent']);
            Route::post('/event', [\App\Http\Controllers\Admin\AdminProductPromotionController::class, 'updateEvent']);
        });
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
            Route::get('/order-stats', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'orderStats']);
        });

        // Chat Broadcast
        Route::get('/chat/broadcast', [\App\Http\Controllers\Admin\AdminChatController::class, 'index']);
        Route::post('/chat/broadcast', [\App\Http\Controllers\Admin\AdminChatController::class, 'broadcast']);

        // Blog Management
        Route::prefix('blog')->group(function () {
            Route::get('/posts', [BlogController::class, 'adminIndex']);
            Route::post('/posts', [BlogController::class, 'store']);
            Route::post('/posts/{id}', [BlogController::class, 'update']);
            Route::delete('/posts/{id}', [BlogController::class, 'destroy']);
            Route::patch('/posts/{id}/toggle-publish', [BlogController::class, 'togglePublish']);
            Route::delete('/comments/{id}', [BlogController::class, 'deleteComment']);
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
