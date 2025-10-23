<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToModel, WithHeadingRow
{
    public int $inserted = 0;
    public int $updated  = 0;
    public int $skipped  = 0;
    public array $errors = [];
    private string $mode;

    public function __construct(string $mode = 'upsert')
    {
        $this->mode = $mode; // upsert | create-only | update-only
    }

    // public function model(array $row)
    // {
    //     try {
    //         // 0️⃣ Bỏ qua dòng trống
    //         $name = trim((string)($row['name'] ?? ''));
    //         if ($name === '') {
    //             $this->skipped++;
    //             return null;
    //         }

    //         // 1️⃣ Slug duy nhất
    //         $slug = trim((string)($row['slug'] ?? ''));
    //         if ($slug === '') $slug = Str::slug($name);
    //         $slug = $this->uniqueSlug($slug);

    //         // 2️⃣ Map category -> category_id (nhận ID hoặc tên)
    //         $categoryId = 1; // mặc định
    //         if (!empty($row['category_id'])) {
    //             $categoryId = (int)$row['category_id'];
    //         } elseif (!empty($row['category'])) {
    //             $cat = Category::query()
    //                 ->where('id', (int)$row['category'])
    //                 ->orWhere('name', $row['category'])
    //                 ->first();
    //             if ($cat) $categoryId = $cat->id;
    //         }

    //         // 3️⃣ Chuẩn hoá status
    //         $status = $this->normalizeStatus($row['status'] ?? null);

    //         // 4️⃣ Ép kiểu số
    //         $priceRoot = max(0, (float)($row['price_root'] ?? 0));
    //         $priceSale = max(0, (float)($row['price_sale'] ?? 0));
    //         $qty       = max(0, (int)($row['qty'] ?? 0));

    //         // 5️⃣ Tìm sản phẩm theo slug
    //         $product = Product::where('slug', $slug)->first();

    //         // 6️⃣ Build dữ liệu an toàn
    //         $data = [
    //             'name'        => $name,
    //             'slug'        => $slug,
    //             'category_id' => $categoryId,
    //             'brand_id'    => !empty($row['brand_id']) ? (int)$row['brand_id'] : null,
    //             'price_root'  => $priceRoot,
    //             'price_sale'  => $priceSale,
    //             'qty'         => $qty,
    //             'status'      => $status,
    //             'thumbnail'   => $row['thumbnail'] ?? null,
    //             'description' => $row['description'] ?? null,
    //             'detail'      => $row['detail'] ?? null,
    //         ];

    //         // Loại bỏ key null để tránh lỗi NOT NULL
    //         $data = array_filter($data, fn($v) => $v !== null);

    //         // 7️⃣ Ghi DB theo mode
    //         if (!$product && in_array($this->mode, ['upsert', 'create-only'], true)) {
    //             $this->inserted++;
    //             return new Product($data); // Thêm mới
    //         } elseif ($product && in_array($this->mode, ['upsert', 'update-only'], true)) {
    //             $product->fill($data)->save(); // Cập nhật
    //             $this->updated++;
    //         } else {
    //             $this->skipped++;
    //         }
    //     } catch (\Throwable $e) {
    //         $this->errors[] = $e->getMessage();
    //         $this->skipped++;
    //     }

    //     return null;
    // }
public function model(array $row)
{
    try {
        // 0️⃣ Bỏ qua dòng trống
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            $this->skipped++;
            $this->errors[] = "Dòng không có tên sản phẩm.";
            return null;
        }

        // ✅ Kiểm tra dữ liệu cơ bản (frontend cũng có)
        if (!isset($row['price_root']) || !is_numeric($row['price_root'])) {
            $this->errors[] = "Giá gốc không hợp lệ cho sản phẩm: {$name}";
            $this->skipped++;
            return null;
        }
        if (!isset($row['price_sale']) || !is_numeric($row['price_sale'])) {
            $this->errors[] = "Giá sale không hợp lệ cho sản phẩm: {$name}";
            $this->skipped++;
            return null;
        }

        // 1️⃣ Slug duy nhất
        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug === '') $slug = Str::slug($name);
        $slug = $this->uniqueSlug($slug);

        // 2️⃣ Map category -> category_id (nhận ID hoặc tên)
        $categoryId = 1;
        if (!empty($row['category_id'])) {
            $categoryId = (int)$row['category_id'];
        } elseif (!empty($row['category'])) {
            $cat = Category::query()
                ->where('id', (int)$row['category'])
                ->orWhere('name', $row['category'])
                ->first();
            if ($cat) $categoryId = $cat->id;
        }

        // 3️⃣ Chuẩn hoá status
        $status = $this->normalizeStatus($row['status'] ?? null);

        // 4️⃣ Ép kiểu số
        $priceRoot = max(0, (float)($row['price_root'] ?? 0));
        $priceSale = max(0, (float)($row['price_sale'] ?? 0));
        $qty       = max(0, (int)($row['qty'] ?? 0));

        // 5️⃣ Tìm sản phẩm theo slug
        $product = Product::where('slug', $slug)->first();

        // 6️⃣ Dữ liệu chuẩn
        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'category_id' => $categoryId,
            'brand_id'    => !empty($row['brand_id']) ? (int)$row['brand_id'] : null,
            'price_root'  => $priceRoot,
            'price_sale'  => $priceSale,
            'qty'         => $qty,
            'status'      => $status,
            'thumbnail'   => $row['thumbnail'] ?? null,
            'description' => $row['description'] ?? null,
            'detail'      => $row['detail'] ?? null,
        ];

        $data = array_filter($data, fn($v) => $v !== null);

        // 7️⃣ Ghi DB
        if (!$product && in_array($this->mode, ['upsert', 'create-only'], true)) {
            $this->inserted++;
            return new Product($data);
        } elseif ($product && in_array($this->mode, ['upsert', 'update-only'], true)) {
            $product->fill($data)->save();
            $this->updated++;
        } else {
            $this->skipped++;
        }
    } catch (\Throwable $e) {
        $this->errors[] = "Lỗi dòng {$name}: " . $e->getMessage();
        $this->skipped++;
    }

    return null;
}

    private function normalizeStatus($val): int
    {
        if (is_numeric($val)) return (int)$val ? 1 : 0;
        $v = strtolower((string)$val);
        return in_array($v, ['1','true','active','enabled','publish','published','yes'], true) ? 1 : 0;
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: Str::random(8);
        $try  = $base;
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
}
