<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id','sku','color','size','price_root','price_sale','qty','images'];
    protected $casts = ['images'=>'array','price_root'=>'float','price_sale'=>'float'];
    public function product(){ return $this->belongsTo(Product::class,'product_id'); }
}
