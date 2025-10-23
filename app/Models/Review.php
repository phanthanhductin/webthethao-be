<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'content', // 👈 đổi từ comment → content
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function images()
{
    return $this->hasMany(ReviewImage::class);
}

}
