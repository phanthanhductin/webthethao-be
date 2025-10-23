<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ Thêm dòng này

class Category extends Model
{
    use HasFactory, SoftDeletes; // ✅ Bật SoftDeletes

    protected $table = 'ptdt_category';

    // Cho phép gán hàng loạt
    protected $fillable = [
        'name',
        'slug',
        'image',
        'parent_id',
        'sort_order',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'parent_id'  => 'integer',
        'sort_order' => 'integer',
        'status'     => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'status'     => 1,
    ];

    // ✅ Tự sinh thuộc tính image_url
    protected $appends = ['image_url'];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // ✅ Accessor URL ảnh
    public function getImageUrlAttribute()
    {
        $img = $this->image;

        if (!$img) {
            return asset('assets/images/no-image.png');
        }

        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }

        $img = ltrim($img, '/');

        if (
            str_starts_with($img, 'assets/') ||
            str_starts_with($img, 'storage/') ||
            str_starts_with($img, 'uploads/')
        ) {
            return asset($img);
        }

        return asset('assets/images/' . $img);
    }
}
