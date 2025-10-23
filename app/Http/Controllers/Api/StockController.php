<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StockController extends Controller
{
    /**
     * GET /api/admin/stock-movements
     */
    public function index(Request $request)
    {
        $kw      = trim((string) $request->query('q', ''));
        $type    = $request->query('type');
        $from    = $request->query('from');
        $to      = $request->query('to');

        // 👇 mặc định 10/trang (có thể override bằng ?per_page=...)
        $perPage = max(1, min(200, (int) $request->query('per_page', 10)));

        $q = Product::with(['category:id,name', 'brand:id,name'])
            ->orderByDesc('id');

        if ($kw !== '') {
            $q->where(function ($w) use ($kw) {
                if (ctype_digit($kw)) {
                    $w->orWhere('id', (int) $kw);
                }
                $w->orWhere('name', 'like', "%{$kw}%");
            });
        }

        $page = $q->paginate($perPage);

        $items = $page->getCollection()->transform(function (Product $p) use ($type, $from, $to) {
            $movement = StockMovement::query()
                ->where('product_id', $p->id)
                ->when($type, fn($q) => $q->where('type', $type))
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->orderByDesc('created_at')
                ->first();

            return [
                'product_id'          => $p->id,
                'product_name'        => $p->name,
                'thumbnail_url'       => $p->thumbnail_url,
                'category_name'       => optional($p->category)->name,
                'brand_name'          => optional($p->brand)->name,
                'qty'                 => (int) $p->qty,
                'price_root'          => (float) $p->price_root,
                'price_sale'          => (float) $p->price_sale,
                'status'              => $p->status,
                'description'         => $p->description,
                'detail'              => $p->detail,
                'created_at'          => $p->created_at,
                'updated_at'          => $p->updated_at,
                // Last movement (nếu có)
                'last_movement_type'  => $movement?->type,
                'last_movement_qty'   => $movement ? abs($movement->qty_change) : null,
                'last_movement_note'  => $movement?->note,
                'last_movement_date'  => $movement?->created_at,
            ];
        });

        $page->setCollection($items);

        return response()->json($page);
    }

    public function adminIndex(Request $request)
    {
        return $this->index($request);
    }

    /**
     * POST /api/admin/stock-movements
     */
    public function store(Request $request)
    {
        $productTable = (new Product)->getTable();

        $base = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists($productTable, 'id')],
            'type'       => ['required', Rule::in(['import','export','return','adjust'])],
            'note'       => ['nullable', 'string', 'max:255'],
        ]);

        $type = $base['type'];

        if ($type === 'adjust') {
            $more = $request->validate([
                'qty_change' => ['required', 'integer', 'not_in:0'],
            ]);
            $qtyChange = (int) $more['qty_change'];
        } else {
            $more = $request->validate([
                'qty' => ['required', 'integer', 'min:1'],
            ]);
            $qtyChange = (int) $more['qty'];
            if ($type === 'export') $qtyChange = -$qtyChange;
        }

        $note = $request->input('note');

        $movement = DB::transaction(function () use ($base, $qtyChange, $note, $type) {
            $product = Product::lockForUpdate()->find($base['product_id']);
            if (!$product) abort(404, 'Sản phẩm không tồn tại');

            $current = (int) ($product->qty ?? 0);
            $after   = $current + $qtyChange;
            if ($after < 0) abort(422, "Không đủ tồn kho. Hiện còn {$current}.");

            $product->qty = $after;
            $product->save();

            return StockMovement::create([
                'product_id' => $product->id,
                'type'       => $type,
                'qty_change' => $qtyChange,
                'ref_type'   => 'manual',
                'ref_id'     => null,
                'note'       => $note,
                'created_by' => Auth::id(),
            ]);
        });

        $movement->load('product:id,name,thumbnail,qty');
        $p = $movement->product;

        $thumb = null;
        if (!empty($p?->thumbnail)) {
            $thumb = Str::startsWith($p->thumbnail, ['http://', 'https://'])
                ? $p->thumbnail
                : url('storage/' . $p->thumbnail);
        }

        $data = [
            'id'            => (int) $movement->id,
            'product_id'    => (int) $movement->product_id,
            'product_name'  => $p->name ?? null,
            'thumbnail_url' => $thumb,
            'type'          => $movement->type,
            'qty'           => abs((int) $movement->qty_change),
            'qty_change'    => (int) $movement->qty_change,
            'note'          => $movement->note ?? '',
            'created_at'    => $movement->created_at,
        ];

        return response()->json([
            'message' => 'Thao tác kho thành công',
            'data'    => $data,
        ], 201);
    }

    /**
     * GET /api/admin/stock/summary?product_ids=1,2,3
     */
    public function summary(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('product_ids', '')))
            ->filter(fn($x) => is_numeric($x))
            ->map(fn($x) => (int) $x)
            ->unique()
            ->values();

        if ($ids->isEmpty()) return response()->json(['data' => []]);

        $pairs = Product::whereIn('id', $ids)->pluck('qty', 'id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = (int) ($pairs[$id] ?? 0);
        }

        return response()->json(['data' => $out]);
    }

    public function allProducts()
    {
        $products = Product::with(['category:id,name', 'brand:id,name'])
            ->orderBy('id', 'desc')
            ->get();

        $data = $products->map(function ($p) {
            return [
                'id'            => $p->id,
                'name'          => $p->name,
                'slug'          => $p->slug,
                'thumbnail_url' => $p->thumbnail_url,
                'category_name' => optional($p->category)->name,
                'brand_name'    => optional($p->brand)->name,
                'qty'           => (int) $p->qty,
                'price_root'    => (float) $p->price_root,
                'price_sale'    => (float) $p->price_sale,
                'status'        => $p->status,
                'description'   => $p->description,
                'detail'        => $p->detail,
                'created_at'    => $p->created_at,
                'updated_at'    => $p->updated_at,
            ];
        });

        return response()->json([
            'data'  => $data,
            'total' => $data->count(),
        ]);
    }
}
