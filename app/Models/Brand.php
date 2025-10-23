<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'ptdt_brand';
    protected $fillable = ['name','slug','logo','status'];

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
