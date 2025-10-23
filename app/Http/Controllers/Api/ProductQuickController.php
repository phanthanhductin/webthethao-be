<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductQuickController extends Controller
{
    public function quick($id)
    {
        $p = Product::with(['variants:id,product_id,size,color,stock', 'images:id,product_id,url'])
            ->select('id','name','slug','thumbnail','price','price_root','status')
            ->findOrFail($id);

        return response()->json([
            'id'   => $p->id,
            'name' => $p->name,
            'thumbnail_url' => $p->thumbnail_url ?? $p->thumbnail,
            'images'  => $p->images?->pluck('url'),
            'price'   => (float)($p->price ?? 0),
            'price_root' => (float)($p->price_root ?? 0),
            'in_stock' => ($p->variants?->sum('stock') ?? 0) > 0,
            'variants' => $p->variants->map(fn($v)=>[
                'id'=>$v->id,'size'=>$v->size,'color'=>$v->color,'stock'=>(int)$v->stock
            ])->values(),
        ]);
    }
}
