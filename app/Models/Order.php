<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'ptdt_order';
    protected $primaryKey = 'id';
    public $timestamps = true;

// app/Models/Order.php

protected $fillable = [
    'user_id','name','phone','email','address','note','status',
    'created_by','updated_by',
    // tiền
    'coupon_code',          // 👈 thêm
    'discount_amount',      // 👈 thêm
    'subtotal',
    'total',
    // thanh toán
    'payment_method','payment_status','payment_ref','payment_amount','payment_at',
];

protected $casts = [
    'user_id'         => 'integer',
    'status'          => 'integer',
    'subtotal'        => 'decimal:2',   // 👈 thêm
    'discount_amount' => 'decimal:2',   // 👈 thêm
    'total'           => 'decimal:2',
    'payment_amount'  => 'decimal:2',
    'payment_at'      => 'datetime',
];


    public function details()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    // alias để FE dùng 'items'
    public function items()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }
    public function payments() { return $this->hasMany(\App\Models\Payment::class, 'order_id'); }
public function payment()  { return $this->hasOne(\App\Models\Payment::class, 'order_id')->latestOfMany(); }



}
