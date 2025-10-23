<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $items = Wishlist::with('product')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $items->pluck('product')
        ]);
    }

    public function toggle($product_id)
    {
        $user = Auth::user();
        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product_id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['liked' => false]);
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $product_id,
            ]);
            return response()->json(['liked' => true]);
        }
    }

    public function clear()
    {
        $user = Auth::user();
        Wishlist::where('user_id', $user->id)->delete();
        return response()->json(['message' => 'Wishlist cleared']);
    }
}
