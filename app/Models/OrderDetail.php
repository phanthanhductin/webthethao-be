<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected $table = 'ptdt_orderdetail';
    protected $fillable = ['order_id','product_id','price_buy','qty','amount'];
    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected $casts = [
        'price_buy' => 'float',
        'qty'       => 'int',
        'amount'    => 'float',
    ];

    
}
