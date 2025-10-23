<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function overview()
    {
        // 1️⃣ Tổng sản phẩm
        $totalProducts = Product::whereNull('deleted_at')->count();

        // 2️⃣ Tổng đơn hàng
        $totalOrders = Order::count();

        // 3️⃣ Tổng doanh thu (đơn đã giao)
        $totalRevenue = OrderDetail::whereHas('order', fn($q) => $q->where('status', 4))
            ->select(DB::raw('SUM(COALESCE(amount, price_buy * qty)) as total'))
            ->value('total') ?? 0;

        // 4️⃣ Tổng người dùng
        $totalUsers = User::count();

        // 5️⃣ Danh sách sản phẩm tồn kho thấp (≤10)
        $lowStockProducts = Product::whereNull('deleted_at')
            ->where('qty', '<=', 10)
            ->orderBy('qty')
            ->limit(5)
            ->get(['id', 'name', 'qty']);

        return response()->json([
            'data' => [
                'totalProducts'    => $totalProducts,
                'totalOrders'      => $totalOrders,
                'totalRevenue'     => (float) $totalRevenue,
                'totalUsers'       => $totalUsers,
                'lowStockProducts' => $lowStockProducts,
            ]
        ]);
    }
}
