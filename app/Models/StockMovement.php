<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    // Khớp đúng với controller
    protected $fillable = [
        'product_id',
        'type',        // import|export|return|adjust
        'qty_change',  // số thay đổi (+/-)
        'note',
        'ref_type',    // manual, order, ...
        'ref_id',
        'created_by',
    ];

    // Bảng có timestamps
    public $timestamps = true;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // created_by -> user
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
