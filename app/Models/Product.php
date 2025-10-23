<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'ptdt_product';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'price_root', 'price_sale',
        'thumbnail', 'qty', 'detail', 'description', 'status',
    ];

    protected $casts = [
        'price_root' => 'float',
        'price_sale' => 'float',
    ];

    protected $dates = ['deleted_at'];

    // ===== Quan hệ =====
    public function variants()
    {
        return $this->hasMany(\App\Models\ProductVariant::class, 'product_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    // ===== Thuộc tính ảo =====
    protected $appends = ['thumbnail_url', 'brand_name'];

    public function getBrandNameAttribute()
    {
        return optional($this->brand)->name;
    }

    // public function getThumbnailUrlAttribute()
    // {
    //     if (!$this->thumbnail) {
    //         return asset('assets/images/no-image.png');
    //     }

    //     $path = ltrim($this->thumbnail, '/');
    //     if (str_starts_with($path, 'http')) return $path;
    //     if (str_starts_with($path, 'assets/')) return asset($path);
    //     return asset('assets/images/' . $path);
    // }
 public function getThumbnailUrlAttribute()
{
    if (!$this->thumbnail) {
        return asset('assets/images/no-image.png'); // ảnh mặc định
    }

    $path = ltrim($this->thumbnail, '/');

    // 1) Nếu đã là full URL
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    // 2) Nếu tồn tại trong storage/app/public (ảnh upload mới)
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        return asset('storage/' . $path);
    }

    // 3) Nếu đường dẫn bắt đầu bằng assets/... (ảnh cũ nằm trong /public/assets/images)
    if (str_starts_with($path, 'assets/')) {
        return asset($path);
    }

    // 4) Nếu tồn tại trong thư mục public/images hoặc assets/images
    if (file_exists(public_path($path))) {
        return asset($path);
    }

    if (file_exists(public_path('assets/images/' . $path))) {
        return asset('assets/images/' . $path);
    }

    // 5) fallback cuối cùng
    return asset('assets/images/no-image.png');
}

public function reviews()
{
    return $this->hasMany(Review::class, 'product_id');
}





}
