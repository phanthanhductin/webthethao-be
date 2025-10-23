<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /* =====================================================
     *  Helpers
     * ===================================================== */
    private function ensureUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug);
        $try  = $base ?: Str::random(8);
        $i = 1;

        while (
            Product::when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $try)
                ->exists()
        ) {
            $i++;
            $try = $base . '-' . $i;
        }
        return $try;
    }

    /** Lưu file ảnh vào public/assets/images và TRẢ VỀ tên file ngắn (vd: balo.webp) */
    private function saveThumbToPublic(\Illuminate\Http\UploadedFile $file): string
    {
        $orig = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $base = Str::slug($orig) ?: 'image';
        // thêm hậu tố ngắn để tránh trùng, nhưng vẫn “đẹp”
        $name = $base . '-' . Str::random(6) . '.' . $ext;

        $dir = public_path('assets/images');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        // move vào public/assets/images
        $file->move($dir, $name);

        // DB CHỈ LƯU tên file ngắn
        return $name;
    }

    /** Xoá ảnh cũ ở cả 2 nơi có thể tồn tại (storage/public và public/assets/images) */
    private function deleteThumbIfExists(?string $thumb): void
    {
        if (!$thumb) return;

        // 1) nếu là đường dẫn trong storage/public (dữ liệu cũ kiểu products/xxx.jpg)
        if (Storage::disk('public')->exists($thumb)) {
            Storage::disk('public')->delete($thumb);
        }

        // 2) nếu là file đặt trong public/assets/images với tên ngắn
        $p1 = public_path('assets/images/' . ltrim($thumb, '/'));
        if (is_file($p1)) @unlink($p1);

        // 3) phòng trường hợp họ lưu cả đường dẫn assets/images/...
        if (str_starts_with($thumb, 'assets/')) {
            $p2 = public_path($thumb);
            if (is_file($p2)) @unlink($p2);
        }
    }

    /* =====================================================
     *  PUBLIC APIs
     * ===================================================== */
 public function index(Request $request)
{
    $q = Product::with('brand:id,name')
        ->select([
            'id','name','slug','brand_id','category_id',
            'price_root','price_sale', DB::raw('price_sale as price'),
            'qty','thumbnail','status'
        ]);

    /* ---- ⭐ Filter theo danh sách ids (ưu tiên cho wishlist) ---- */
    $ids = null;
    if ($request->filled('ids')) {
        // chấp nhận "1,2,3" hoặc mảng ids[]
        $ids = $request->query('ids');
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        } elseif (is_array($ids)) {
            $ids = array_filter(array_map('intval', $ids));
        }

        if (!empty($ids)) {
            $q->whereIn('id', $ids);
            // giữ thứ tự theo ids (MySQL/MariaDB)
            $q->orderByRaw('FIELD(id,'.implode(',', $ids).')');
        }
    }

    // --- Lọc keyword / category / price / flags ---
    $kw = trim($request->query('keyword', $request->query('q', '')));
    if ($kw !== '') {
        $kwSlug = Str::slug($kw);
        $catIds = Category::query()
            ->where('name', 'like', "%{$kw}%")
            ->orWhere('slug', 'like', "%{$kwSlug}%")
            ->pluck('id');

        if ($catIds->count() > 0) {
            $q->whereIn('category_id', $catIds->all());
        } else {
            $q->where(function ($x) use ($kw, $kwSlug) {
                $x->where('name', 'like', "%{$kw}%")
                  ->orWhere('slug', 'like', "%{$kwSlug}%");
            });
        }
    }

    if ($request->filled('category_id')) {
        $q->where('category_id', (int)$request->query('category_id'));
    }

    $priceExpr = DB::raw('COALESCE(price_sale, price_root)');
    if ($request->filled('min_price')) $q->where($priceExpr, '>=', (float)$request->query('min_price'));
    if ($request->filled('max_price')) $q->where($priceExpr, '<=', (float)$request->query('max_price'));

    if ($request->boolean('only_sale')) {
        $q->whereNotNull('price_root')
          ->whereNotNull('price_sale')
          ->whereColumn('price_sale', '<', 'price_root');
    }

    if ($request->boolean('in_stock')) {
        $q->where(fn($x) => $x->where('qty', '>', 0)
                              ->orWhere('status', 'active')
                              ->orWhere('status', 1));
    }

    // --- Sort (chỉ áp dụng khi KHÔNG truyền ids) ---
    if (empty($ids)) {
        [$field, $dir] = array_pad(
            explode(':', (string)$request->query('sort', 'created_at:desc'), 2),
            2,
            'asc'
        );
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        if ($field === 'price')       $q->orderByRaw('COALESCE(price_sale, price_root) ' . $dir);
        elseif ($field === 'name')    $q->orderBy('name', $dir);
        elseif ($field === 'created_at') $q->orderBy('created_at', $dir);
        else                          $q->orderBy('id', 'desc');
    }

    // Nếu có ids mà chưa truyền per_page → mặc định = số lượng ids (để trả về đủ)
    $defaultPerPage = (!empty($ids) ? count($ids) : 12);
    $perPage = max(1, min(100, (int)$request->query('per_page', $defaultPerPage)));

    $products = $q->paginate($perPage);

    // Accessor trong Model sẽ tự sinh thumbnail_url đúng
    return $products->makeHidden(['brand','brand_id']);
}


    // public function show($id)
    // {
    //     $p = Product::with('brand:id,name')
    //         ->select([
    //             'id','name','slug','brand_id','category_id',
    //             'price_root','price_sale', DB::raw('price_sale as price'),
    //             'qty','status','thumbnail','description','detail'
    //         ])
    //         ->find($id);

    //     if (!$p) return response()->json(['message' => 'Not found'], 404);
    //     return $p->makeHidden(['brand','brand_id']);
    // }

    public function byCategory($id)
    {
        $items = Product::with('brand:id,name')
            ->where('category_id', $id)
            ->select(['id','name','slug','brand_id','price_sale as price','thumbnail'])
            ->latest('id')
            ->paginate(12);

        return $items->makeHidden(['brand','brand_id']);
    }

    /* =====================================================
     *  ADMIN APIs
     * ===================================================== */
    public function adminIndex(Request $request)
    {
        $perPage = max(1, min(100, (int)$request->query('per_page', 10)));
        $scope   = $request->query('scope', 'active');

        $q = Product::with('brand:id,name')
            ->select([
                'id','name','slug','brand_id','price_root','price_sale',
                DB::raw('COALESCE(qty,0) as qty'),'thumbnail'
            ])
            ->latest('id');

        if ($scope === 'with_trash') $q->withTrashed();
        elseif ($scope === 'only_trash') $q->onlyTrashed();

        $products = $q->paginate($perPage)->appends($request->query());
        return $products->makeHidden(['brand','brand_id']);
    }

    // public function adminShow($id)
    // {
    //     $p = Product::with('brand:id,name')
    //         ->select([
    //             'id','name','slug','brand_id','category_id',
    //             'price_root','price_sale', DB::raw('COALESCE(price_sale, price_root) as price'),
    //             'qty','status','thumbnail','description','detail',
    //             'created_at','updated_at'
    //         ])
    //         ->find($id);

    //     if (!$p) return response()->json(['message' => 'Product not found'], 404);
    //     return response()->json(['message' => 'OK', 'data' => $p]);
    // }





public function show($id)
{
    $builder = Product::with('brand:id,name')
        ->select([
            'id','name','slug','brand_id','category_id',
            'price_root','price_sale', DB::raw('price_sale as price'),
            'qty','status','thumbnail','description','detail'
        ]);

    $p = is_numeric($id)
        ? $builder->find($id)
        : $builder->where('slug', $id)->first();

    if (!$p) return response()->json(['message' => 'Not found'], 404);

    $data = $p->makeHidden(['brand_id'])
              ->append(['thumbnail_url','brand_name'])
              ->toArray();

    $data['description'] = (string)($data['description'] ?? '');
    $data['detail']      = (string)($data['detail'] ?? '');

    return response()->json($data);
}


public function adminShow($id)
{
    $p = Product::with('brand:id,name')
        ->select([
            'id','name','slug','brand_id','category_id',
            'price_root','price_sale', DB::raw('COALESCE(price_sale, price_root) as price'),
            'qty','status','thumbnail','description','detail',
            'created_at','updated_at'
        ])
        ->find($id);

    if (!$p) return response()->json(['message' => 'Product not found'], 404);

    // đảm bảo có accessor & field mô tả/chi tiết dạng string
    $data = $p->makeHidden(['brand_id'])
              ->append(['thumbnail_url','brand_name'])
              ->toArray();

    $data['description'] = (string)($data['description'] ?? '');
    $data['detail']      = (string)($data['detail'] ?? '');

    return response()->json(['message' => 'OK', 'data' => $data]);
}

    public function store(Request $request)
    {
        $brandTable = (new Brand)->getTable();
        $catTable   = (new Category)->getTable();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255',
            'brand_id'    => ["nullable","integer","exists:{$brandTable},id"],
            'category_id' => ["nullable","integer","exists:{$catTable},id"],
            'price_root'  => 'required|numeric|min:0',
            'price_sale'  => 'nullable|numeric|min:0',
            'qty'         => 'required|integer|min:0',
            'detail'      => 'nullable|string',
            'description' => 'nullable|string',
            'thumbnail'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $slug = $this->ensureUniqueSlug($validated['slug'] ?? $validated['name']);
        $data = array_merge($validated, ['slug' => $slug]);

        if ($request->hasFile('thumbnail')) {
            // LƯU VÀO public/assets/images, DB chỉ là tên ngắn
            $data['thumbnail'] = $this->saveThumbToPublic($request->file('thumbnail'));
        }

        $product = Product::create($data);
        return response()->json([
            'message' => 'Tạo sản phẩm thành công',
            'data'    => $product
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Not found'], 404);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'nullable|string|max:255',
            'brand_id'    => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'price_root'  => 'nullable|numeric',
            'price_sale'  => 'nullable|numeric',
            'qty'         => 'nullable|integer',
            'detail'      => 'nullable|string',
            'description' => 'nullable|string',
            'thumbnail'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->ensureUniqueSlug(
                $data['slug'] !== '' ? $data['slug'] : ($data['name'] ?? $product->name),
                $product->id
            );
        }

        if ($request->hasFile('thumbnail')) {
            // xoá ảnh cũ ở cả 2 nơi
            $this->deleteThumbIfExists($product->thumbnail);

            // lưu ảnh mới vào public/assets/images và chỉ lưu tên
            $data['thumbnail'] = $this->saveThumbToPublic($request->file('thumbnail'));
        }

        $product->update($data);
        return response()->json([
            'message' => 'Cập nhật thành công',
            'data'    => $product
        ]);
    }

    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Not found'], 404);

        $used = DB::table('ptdt_orderdetail')->where('product_id', $id)->exists();
        if ($used) {
            return response()->json(['message' => '❌ Không thể xóa sản phẩm này vì đang có trong đơn hàng!'], 400);
        }

        $product->delete();
        return response()->json(['message' => 'Đã chuyển sản phẩm vào thùng rác']);
    }

    public function trash()
    {
        $trash = Product::onlyTrashed()->orderByDesc('deleted_at')->get();
        return response()->json(['data' => $trash]);
    }

    public function restore($id)
    {
        $p = Product::onlyTrashed()->find($id);
        if (!$p) return response()->json(['message' => 'Không tìm thấy sản phẩm trong thùng rác'], 404);
        $p->restore();
        return response()->json(['message' => 'Đã khôi phục sản phẩm!']);
    }

    public function forceDelete($id)
    {
        $p = Product::onlyTrashed()->find($id);
        if (!$p) return response()->json(['message' => 'Không tìm thấy sản phẩm trong thùng rác'], 404);

        // xoá file vật lý nếu có
        $this->deleteThumbIfExists($p->thumbnail);

        $p->forceDelete();
        return response()->json(['message' => 'Đã xoá vĩnh viễn sản phẩm!']);
    }
}
