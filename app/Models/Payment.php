<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'ptdt_payment';

    protected $fillable = [
        'order_id','provider','method','status','amount',
        'trans_id','request_id','order_code','pay_url',
        'extra','ipn_payload','message','result_code','paid_at',
    ];

    protected $casts = [
        'amount'      => 'integer',
        'extra'       => 'array',
        'ipn_payload' => 'array',
        'paid_at'     => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
