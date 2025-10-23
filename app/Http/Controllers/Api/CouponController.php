<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Coupon;

class CouponController extends Controller
{
    // GET /api/admin/coupons?q=GIAM
    public function adminIndex(Request $req)
    {
        $q = trim($req->query('q', ''));
        $rows = Coupon::when($q !== '', function($qq) use ($q) {
                    $qq->where('code','like',"%{$q}%");
                })
                ->orderByDesc('id')
                ->get();

        // Trả về dạng { data: [...] } cho FE
        return response()->json(['data' => $rows]);
    }

    // POST /api/admin/coupons
    public function adminStore(Request $req)
    {
        $data = $this->validated($req, null);

        $data['code'] = strtoupper(trim($data['code']));
        if ($data['type'] === 'fixed') {
            $data['max_discount'] = null;
        }

        $row = Coupon::create($data);
        return response()->json(['message' => 'created', 'data' => $row], 201);
    }

    // PUT /api/admin/coupons/{id}
    public function adminUpdate(Request $req, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data   = $this->validated($req, $coupon->id);

        $data['code'] = strtoupper(trim($data['code']));
        if ($data['type'] === 'fixed') {
            $data['max_discount'] = null;
        }

        $coupon->fill($data)->save();
        return response()->json(['message' => 'updated', 'data' => $coupon]);
    }

    // DELETE /api/admin/coupons/{id}
    public function adminDestroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();
        return response()->json(['message' => 'deleted']);
    }

    // POST /api/coupons/validate  {code, subtotal}
    public function validateCode(Request $req)
    {
        $req->validate([
            'code'     => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $userId   = optional($req->user())->id;
        $subtotal = (float) $req->subtotal;

        $coupon = Coupon::byCode($req->code)->first();
        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Mã không tồn tại'], 404);
        }

        [$ok, $msg] = $coupon->canUse($userId ?: null, $subtotal);
        if (!$ok) {
            return response()->json(['valid' => false, 'message' => $msg], 422);
        }

        $discount = $coupon->calcDiscount($subtotal);

        return response()->json([
            'valid'    => true,
            'code'     => $coupon->code,
            'type'     => $coupon->type,
            'value'    => (float) $coupon->value,
            'discount' => (float) $discount,
        ]);
    }

    // ------------------------
    private function validated(Request $req, $ignoreId = null): array
    {
        $rules = [
            'code'            => [
                'required', 'string', 'max:50',
                Rule::unique('ptdt_coupon','code')->ignore($ignoreId),
            ],
            'type'            => ['required', Rule::in(['fixed','percent'])],
            'value'           => ['required','numeric','min:0.01'],
            'max_discount'    => ['nullable','numeric','min:0'],
            'min_order_total' => ['nullable','numeric','min:0'],
            'usage_limit'     => ['nullable','integer','min:1'],
            'per_user_limit'  => ['required','integer','min:1'],
            'start_at'        => ['nullable','date'],
            'end_at'          => ['nullable','date','after_or_equal:start_at'],
            'is_active'       => ['boolean'],
        ];

        $data = $req->validate($rules);

        // Chuẩn hóa null/number
        $data['min_order_total'] = isset($data['min_order_total']) ? (float) $data['min_order_total'] : 0;
        $data['usage_limit']     = $data['usage_limit'] ?? null;
        $data['max_discount']    = $data['max_discount'] ?? null;
        $data['is_active']       = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
