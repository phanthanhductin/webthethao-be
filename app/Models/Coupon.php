<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Coupon extends Model
{
    protected $table = 'ptdt_coupon';

    protected $fillable = [
        'code','type','value','max_discount','min_order_total',
        'usage_limit','per_user_limit','used_count',
        'start_at','end_at','is_active'
    ];

    protected $casts = [
        'start_at'        => 'datetime',
        'end_at'          => 'datetime',
        'is_active'       => 'boolean',
        'value'           => 'decimal:2',
        'max_discount'    => 'decimal:2',
        'min_order_total' => 'decimal:2',
    ];

    public function scopeByCode($q, $code)
    {
        return $q->where('code', strtoupper(trim($code)));
    }

    public function canUse(?int $userId, float $orderSubtotal): array
    {
        $now = Carbon::now();

        if (!$this->is_active) return [false, 'Mã đã bị khóa'];
        if ($this->start_at && $now->lt($this->start_at)) return [false, 'Mã chưa bắt đầu'];
        if ($this->end_at && $now->gt($this->end_at))   return [false, 'Mã đã hết hạn'];
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit)
            return [false, 'Mã đã hết lượt sử dụng'];
        if ($orderSubtotal < (float) $this->min_order_total)
            return [false, 'Chưa đạt giá trị tối thiểu'];

        if ($userId) {
            $userCount = DB::table('ptdt_coupon_usage')
                ->where('coupon_id', $this->id)
                ->where('user_id', $userId)
                ->count();
            if ($userCount >= $this->per_user_limit)
                return [false, 'Bạn đã dùng tối đa số lần cho mã này'];
        }

        return [true, null];
    }

    public function calcDiscount(float $subtotal): float
    {
        if ($this->type === 'fixed') {
            return max(0, min((float) $this->value, $subtotal));
        }
        // percent
        $d = $subtotal * ((float) $this->value / 100);
        if (!is_null($this->max_discount)) {
            $d = min($d, (float) $this->max_discount);
        }
        return max(0, min($d, $subtotal));
    }
}
