<?php

// use Illuminate\Support\Facades\Route;

// /* ==== Controllers ==== */
// use App\Http\Controllers\Api\ProductController;
// use App\Http\Controllers\Api\CategoryController;
// use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\OrderController;
// use App\Http\Controllers\Api\UserController;
// use App\Http\Controllers\Api\WishlistController;
// use App\Http\Controllers\Api\ReviewController;
// use App\Http\Controllers\Api\PostController;
// use App\Http\Controllers\Api\ContactController;
// use App\Http\Controllers\Api\BrandController;
// use App\Http\Controllers\Api\PaymentController;
// use App\Http\Controllers\Api\StockController;
// use App\Http\Controllers\Api\CouponController; // thêm dòng use ở đầu file
// use App\Http\Controllers\Api\AIChatController; // thêm dòng use
// use App\Http\Controllers\Api\ProductImportController;

// use App\Http\Controllers\Api\AdminDashboardController;

// Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
// Route::get('dashboard/overview', [AdminDashboardController::class, 'overview']);
// });



// /* =======================
//    ===== WISHLIST =====
//    ======================= */

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/wishlist', [WishlistController::class, 'index']);
//     Route::post('/wishlist/toggle/{id}', [WishlistController::class, 'toggle']);
//     Route::delete('/wishlist/clear', [WishlistController::class, 'clear']);
// });














// /* =======================
//    ===== IMPORT ADMIN =====
//    ======================= */
// Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
//   Route::post('/products/import', [ProductImportController::class, 'import']);
//   Route::get('/products/export', [ProductImportController::class, 'export']);
// });

// /* =======================
//    ===== PUBLIC API =====
//    ======================= */

// // ===== STOCK (public xem) =====
// Route::get('/stock-movements', [StockController::class, 'index']);

// // ===== AUTH =====
// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::post('/admin/login', [AuthController::class, 'adminLogin']);
// Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);

// // ===== PRODUCTS & CATEGORIES =====
// Route::get('/products', [ProductController::class, 'index']);
// Route::get('/products/{id}', [ProductController::class, 'show']);

// Route::get('/categories', [CategoryController::class, 'index']);
// Route::get('/categories/{id}', [CategoryController::class, 'show']);
// Route::get('/categories/{id}/products', [ProductController::class, 'byCategory']);

// // ===== REVIEWS (public xem) =====
// Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// // ===== ORDERS (tracking + cancel + chi tiết) =====
// Route::get('/orders', [OrderController::class, 'index']);
// Route::get('/orders/track', [OrderController::class, 'track']);
// Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
// Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

// // ===== CONTACT / BRAND / POSTS =====
// Route::post('/contacts', [ContactController::class, 'store']);
// Route::get('/brands', [BrandController::class, 'index']);
// Route::get('/posts', [PostController::class, 'index']);
// Route::get('/posts/{idOrSlug}', [PostController::class, 'show']);

// // ===== PAYMENTS =====
// Route::post('/payments/momo/create', [PaymentController::class, 'createMoMo']);
// Route::post('/payments/momo/ipn', [PaymentController::class, 'ipn']);
// Route::get('/payments/momo/return', [PaymentController::class, 'return']);
// Route::get('/payments/momo/check', [PaymentController::class, 'check']);















// // ===== AI (PUBLIC) =====

// Route::post('/ai/chat', [AIChatController::class, 'chat']);



// // === COUPONS (PUBLIC) ===
// Route::get('/coupons', [CouponController::class, 'index']);
// Route::post('/coupons/validate', [CouponController::class, 'validateCode']);

// /* ===========================
//    ===== AUTH CUSTOMER =====
//    =========================== */
// Route::middleware('auth:sanctum')->group(function () {
//     // Auth
//     Route::post('/logout', [AuthController::class, 'logout']);

//     // Orders
//     Route::post('/checkout', [OrderController::class, 'checkout']);
//     Route::get('/orders/mine', [OrderController::class, 'mine']);
//     Route::get('/my-orders', [OrderController::class, 'mine']); // alias

//     // Wishlist
//     Route::get('/wishlist', [WishlistController::class, 'index']);
//     Route::post('/wishlist/toggle/{productId}', [WishlistController::class, 'toggle']);

//     // Reviews (private)
//     Route::get('/products/{id}/can-review', [ReviewController::class, 'canReview']);
//     Route::post('/products/{id}/reviews', [ReviewController::class, 'store']);
//     Route::put('/reviews/{rid}', [ReviewController::class, 'update']);
//     Route::delete('/reviews/{rid}', [ReviewController::class, 'destroy']);
    
// });

// /* ===========================
//    ===== ADMIN PANEL =====
//    =========================== */
// Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
//     // ===== Categories =====
//     Route::apiResource('categories', CategoryController::class)->except(['show']);
//     Route::get('categories/trash', [CategoryController::class, 'trash']);
//     Route::post('categories/restore/{id}', [CategoryController::class, 'restore']);
//     Route::delete('categories/force/{id}', [CategoryController::class, 'forceDelete']);

//     // ===== Products =====
//     Route::get('products', [ProductController::class, 'adminIndex']);
//     // 👇 THÊM ROUTE SHOW CHO ADMIN (không xung đột với apiResource)
//     Route::get('products/{id}', [ProductController::class, 'adminShow'])->whereNumber('id');
//     Route::apiResource('products', ProductController::class)->except(['show', 'index']);
//     Route::get('products/trash', [ProductController::class, 'trash']);
//     Route::post('products/{id}/restore', [ProductController::class, 'restore']);
//     Route::delete('products/{id}/force', [ProductController::class, 'forceDelete']);

//     // ===== Orders =====
//     Route::get('orders', [OrderController::class, 'adminIndex']);
//     Route::get('orders/{id}', [OrderController::class, 'show']);
//     Route::put('orders/{id}', [OrderController::class, 'update']);
//     Route::get('orders/user/{id}', [OrderController::class, 'byUser']);

//     // ===== Users =====
//     Route::apiResource('users', UserController::class);
//     Route::post('users/{id}/lock', [UserController::class, 'lock']);
//     Route::post('users/{id}/unlock', [UserController::class, 'unlock']);

//     // ===== Posts =====
//     Route::apiResource('posts', PostController::class);

//     // ===== Contacts =====
//     Route::apiResource('contacts', ContactController::class)->except(['create', 'edit']);
//     Route::any('contacts/{id}', [ContactController::class, 'adminUpdate']);

//     // ===== Brands =====
//     Route::post('brands', [BrandController::class, 'store']);

//     // ===== Stock Movements =====
//     Route::get('stock-movements', [StockController::class, 'adminIndex']);
//     Route::post('stock-movements', [StockController::class, 'store']);
//     Route::get('stock/summary', [StockController::class, 'summary']);
//     Route::get('/admin/stock/products', [\App\Http\Controllers\Api\StockController::class, 'allProducts']);

// });


use Illuminate\Support\Facades\Route;

/* ==== Controllers ==== */
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\AIChatController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\AdminDashboardController;

// Public: validate mã
Route::post('/coupons/validate', [CouponController::class, 'validateCode']);

// Admin (yêu cầu auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/coupons',        [CouponController::class, 'adminIndex']);
    Route::post('/admin/coupons',       [CouponController::class, 'adminStore']);
    Route::put('/admin/coupons/{id}',   [CouponController::class, 'adminUpdate']);
    Route::delete('/admin/coupons/{id}',[CouponController::class, 'adminDestroy']);
});

use App\Http\Controllers\Api\ProductQuickController;
Route::get('/products/{id}/quick', [ProductQuickController::class, 'quick']);


/* ===========================
   ===== ADMIN (DASHBOARD) ===
   =========================== */
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('dashboard/overview', [AdminDashboardController::class, 'overview']);
});

/* =======================
   ===== WISHLIST (KH) ===
   ======================= */
// (giữ nhóm wishlist đăng nhập riêng như ban đầu – không ảnh hưởng gì)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle/{id}', [WishlistController::class, 'toggle']);
    Route::delete('/wishlist/clear', [WishlistController::class, 'clear']);
});

/* =======================
   ===== IMPORT ADMIN ====
   ======================= */
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('products/import', [ProductImportController::class, 'import']); // 🔧 bỏ / đầu để tránh admin/admin
    Route::get('products/export', [ProductImportController::class, 'export']);
});

/* =======================
   ===== PUBLIC API ======
   ======================= */

// ===== STOCK (public xem) =====
Route::get('/stock-movements', [StockController::class, 'index']);

// ===== AUTH =====
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);

// ===== PRODUCTS & CATEGORIES =====
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/categories/{id}/products', [ProductController::class, 'byCategory']);

// ===== REVIEWS (public xem) =====
Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// ===== ORDERS (public xem/track/cancel/show) =====
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/track', [OrderController::class, 'track']);
Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

// ===== CONTACT / BRAND / POSTS =====
Route::post('/contacts', [ContactController::class, 'store']);
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{idOrSlug}', [PostController::class, 'show']);

// ===== PAYMENTS (MoMo) =====  🔧 giữ nguyên, KHÔNG ĐỤNG
Route::post('/payments/momo/create', [PaymentController::class, 'createMoMo']);
Route::post('/payments/momo/ipn',    [PaymentController::class, 'ipn']);
Route::get('/payments/momo/return',  [PaymentController::class, 'return']);
Route::get('/payments/momo/check',   [PaymentController::class, 'check']);

// ===== AI (PUBLIC) =====
Route::post('/ai/chat', [AIChatController::class, 'chat']);

// ===== COUPONS (PUBLIC) =====
Route::get('/coupons', [CouponController::class, 'index']);
Route::post('/coupons/validate', [CouponController::class, 'validateCode']);

/* ===========================
   ===== AUTH CUSTOMER =======
   =========================== */
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Orders (của KH)
    Route::post('/checkout',   [OrderController::class, 'checkout']);
    Route::get('/orders/mine', [OrderController::class, 'mine']);
    Route::get('/my-orders',   [OrderController::class, 'mine']); // alias

    // 🔧 THÊM: KH tải hóa đơn PDF (chỉ thấy đơn của chính mình)
    Route::get('/orders/{id}/invoice.pdf', [OrderController::class, 'invoicePdf'])->whereNumber('id');

    // Wishlist (bản khác – nếu bạn muốn gộp thì có thể bỏ nhóm wishlist ở trên để tránh lặp)
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle/{productId}', [WishlistController::class, 'toggle']);

    // Reviews (private)
    Route::get('/products/{id}/can-review', [ReviewController::class, 'canReview']);
    Route::post('/products/{id}/reviews',   [ReviewController::class, 'store']);
    Route::put('/reviews/{rid}',            [ReviewController::class, 'update']);
    Route::delete('/reviews/{rid}',         [ReviewController::class, 'destroy']);
});

/* ===========================
   ===== ADMIN PANEL =========
   =========================== */
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // ===== Categories =====
    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::get('categories/trash',           [CategoryController::class, 'trash']);
    Route::post('categories/restore/{id}',   [CategoryController::class, 'restore']);
    Route::delete('categories/force/{id}',   [CategoryController::class, 'forceDelete']);

    // ===== Products =====
    Route::get('products',            [ProductController::class, 'adminIndex']);
    Route::get('products/{id}',       [ProductController::class, 'adminShow'])->whereNumber('id');
    Route::apiResource('products',    ProductController::class)->except(['show', 'index']);
    Route::get('products/trash',      [ProductController::class, 'trash']);
    Route::post('products/{id}/restore', [ProductController::class, 'restore']);
    Route::delete('products/{id}/force',  [ProductController::class, 'forceDelete']);

    // ===== Orders (Admin) =====
    Route::get('orders',           [OrderController::class, 'adminIndex']);
    Route::get('orders/{id}',      [OrderController::class, 'show']);
    Route::put('orders/{id}',      [OrderController::class, 'update']);
    Route::get('orders/user/{id}', [OrderController::class, 'byUser']);

    // 🔧 THÊM: Admin tải hóa đơn PDF cho bất kỳ đơn
    Route::get('orders/{id}/invoice.pdf', [OrderController::class, 'invoicePdf'])->whereNumber('id');

    // ===== Users =====
    Route::apiResource('users', UserController::class);
    Route::post('users/{id}/lock',   [UserController::class, 'lock']);
    Route::post('users/{id}/unlock', [UserController::class, 'unlock']);

    // ===== Posts =====
    Route::apiResource('posts', PostController::class);

    // ===== Contacts =====
    Route::apiResource('contacts', ContactController::class)->except(['create', 'edit']);
    Route::any('contacts/{id}', [ContactController::class, 'adminUpdate']);

    // ===== Brands =====
    Route::post('brands', [BrandController::class, 'store']);

    // ===== Stock Movements =====
    Route::get('stock-movements', [StockController::class, 'adminIndex']);
    Route::post('stock-movements', [StockController::class, 'store']);
    Route::get('stock/summary',    [StockController::class, 'summary']);

    // 🔧 Sửa path sai (trước đây là /admin/stock/products -> bị admin/admin/...)
    Route::get('stock/products',   [StockController::class, 'allProducts']);

    
});