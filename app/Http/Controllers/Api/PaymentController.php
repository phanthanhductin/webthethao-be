<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Models\Order;
use App\Models\Payment; // nếu chưa có bảng ptdt_payment thì vẫn giữ; bên dưới đã có guard
use App\Models\OrderDetail;
use App\Models\Coupon; // ✅ thêm

class PaymentController extends Controller
{
    // POST /api/payments/momo/create
    public function createMoMo(Request $req)
    {
        try {
            // 1) Validate thông tin KH + items
            $req->validate([
                'name'         => 'required|string|max:100',
                'phone'        => 'required|string|max:20',
                'email'        => 'required|email|max:255',
                'address'      => 'required|string|max:255',
                'items'        => 'required|array|min:1',
                'items.*.id'   => 'required|integer',    // product_id
                'items.*.name' => 'required|string',
                'items.*.price'=> 'required|numeric|min:0',
                'items.*.qty'  => 'required|integer|min:1',
                'note'         => 'nullable|string|max:500',
                'momo_type'    => 'nullable|in:qr,card',
                'amount'       => 'nullable|integer|min:1000', // FE có thể không gửi; BE sẽ tự tính
                'coupon_code'  => 'nullable|string|max:50',     // ✅ thêm
            ]);

            // 2) Đọc config MoMo
            $partnerCode = config('momo.partnerCode');
            $accessKey   = config('momo.accessKey');
            $secretKey   = config('momo.secretKey');
            $endpoint    = rtrim(config('momo.endpoint', 'https://test-payment.momo.vn'), '/');
            $redirectUrl = config('momo.returnUrl'); // FE /momo-return
            $ipnUrl      = config('momo.ipnUrl');    // BE /api/payments/momo/ipn

            if (!$partnerCode || !$accessKey || !$secretKey || !$redirectUrl || !$ipnUrl) {
                Log::error('MoMo config missing', compact('partnerCode','accessKey','secretKey','redirectUrl','ipnUrl'));
                return response()->json(['message' => 'Thiếu cấu hình MoMo (.env)'], 500);
            }

            $typeFromFE  = $req->input('momo_type', 'qr');
            $requestType = $typeFromFE === 'card' ? 'payWithMethod' : 'captureWallet';
            $userId      = optional($req->user())->id ?? 0;

            // 3) Tạo Order (pending) — TẠM tạo trước, lát cập nhật các trường tổng
            $order = Order::create([
                'name'           => $req->name,
                'phone'          => $req->phone,
                'email'          => $req->email,
                'address'        => $req->address,
                'user_id'        => $userId,
                'status'         => 0,               // pending
                'note'           => $req->note,
                'payment_method' => 'momo',
                'payment_status' => 'pending',
            ]);

            // 4) Ghi OrderDetail từ items[] và tính subtotal
            $subtotal = 0;
            foreach ($req->items as $it) {
                $pid   = (int) $it['id'];
                $price = (float) $it['price'];
                $qty   = (int) $it['qty'];

                OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $pid,
                    'price_buy'  => $price,
                    'qty'        => $qty,
                    'amount'     => $price * $qty,
                ]);

                $subtotal += $price * $qty;
            }
            $subtotal = (float) round($subtotal); // làm tròn cho thống nhất

            // 4.1) Áp mã giảm giá (nếu có) — BE tự tính lại để chống gian lận
            $discount   = 0;
            $couponCode = null;

            if ($code = $req->input('coupon_code')) {
                $coupon = Coupon::byCode($code)->first();
                if ($coupon) {
                    [$ok, $msg] = $coupon->canUse($userId ?: null, $subtotal);
                    if ($ok) {
                        $discount   = (float) round($coupon->calcDiscount($subtotal));
                        $couponCode = $coupon->code;
                    } else {
                        // Không chặn thanh toán; chỉ đơn giản là bỏ qua mã
                        Log::info('Coupon not applicable', ['code' => $code, 'reason' => $msg, 'order_id' => $order->id]);
                    }
                }
            }

            $total = max(0, (float) $subtotal - (float) $discount);
            $total = (int) round($total); // MoMo expects integer amount (VND)

            // Nếu FE có gửi amount thì đối chiếu với **total sau giảm**
            if ($req->filled('amount') && (int) $req->amount !== $total) {
                return response()->json([
                    'message'       => 'Số tiền không khớp với giỏ hàng (đã áp mã)',
                    'server_total'  => $total,
                    'client_amount' => (int) $req->amount,
                ], 422);
            }

            // 4.2) Lưu tổng vào đơn
            if (Schema::hasColumn('ptdt_order', 'subtotal')) $order->subtotal = $subtotal;
            if (Schema::hasColumn('ptdt_order', 'discount_amount')) $order->discount_amount = $discount;
            if (Schema::hasColumn('ptdt_order', 'coupon_code')) $order->coupon_code = $couponCode;
            if (Schema::hasColumn('ptdt_order', 'total')) $order->total = $total;

            $order->payment_amount = $total;
            $order->save();

            // 5) Gọi MoMo tạo phiên
            $orderCode  = 'ORD' . $order->id . '-' . time();
            $requestId  = (string) Str::uuid();
            $orderInfo  = 'Thanh toan don hang #' . $order->id;
            $extraData  = base64_encode(json_encode([
                'order_id'    => $order->id,
                'coupon_code' => $couponCode,
                'discount'    => $discount,
            ]));

            $raw = "accessKey={$accessKey}&amount={$total}&extraData={$extraData}&ipnUrl={$ipnUrl}"
                 . "&orderId={$orderCode}&orderInfo={$orderInfo}&partnerCode={$partnerCode}"
                 . "&redirectUrl={$redirectUrl}&requestId={$requestId}&requestType={$requestType}";
            $signature = hash_hmac('sha256', $raw, $secretKey);

            $payload = [
                'partnerCode' => $partnerCode,
                'partnerName' => 'TheThao Sports',
                'storeId'     => 'TheThao_FE',
                'requestId'   => $requestId,
                'amount'      => $total,       // ✅ gửi số tiền sau giảm
                'orderId'     => $orderCode,
                'orderInfo'   => $orderInfo,
                'redirectUrl' => $redirectUrl,
                'ipnUrl'      => $ipnUrl,
                'lang'        => 'vi',
                'extraData'   => $extraData,
                'requestType' => $requestType,
                'signature'   => $signature,
            ];

            $client = Http::timeout(20);
            if (env('MOMO_SSL_VERIFY', 'true') === 'false') $client = $client->withoutVerifying();

            $res = $client->post("{$endpoint}/v2/gateway/api/create", $payload);
            if (!$res->ok()) {
                Log::error('MoMo create failed', ['status' => $res->status(), 'body' => $res->body()]);
                return response()->json(['message' => 'MoMo create failed', 'detail' => $res->json() ?? $res->body()], 500);
            }
            $json = $res->json();

            // 6) Lưu payment pending (nếu có bảng)
            if (class_exists(Payment::class) && Schema::hasTable('ptdt_payment')) {
                Payment::create([
                    'order_id'   => $order->id,
                    'provider'   => 'momo',
                    'method'     => $requestType,
                    'status'     => 'pending',
                    'amount'     => $total,
                    'request_id' => $requestId,
                    'order_code' => $orderCode,
                    'pay_url'    => $json['payUrl'] ?? null,
                    'extra'      => ['order_id' => $order->id, 'momo_type' => $typeFromFE, 'coupon_code' => $couponCode],
                    'message'    => $json['message'] ?? null,
                    'result_code'=> $json['resultCode'] ?? null,
                ]);
            }

            return response()->json([
                'ok'        => true,
                'momo'      => $json,
                'order_id'  => $order->id,
                'orderCode' => $orderCode,
            ]);
        } catch (\Throwable $e) {
            Log::error('MoMo create exception', ['err' => $e->getMessage()]);
            return response()->json(['message' => 'MoMo exception: '.$e->getMessage()], 500);
        }
    }

    // (ipn/check/return giữ nguyên như bạn đã gửi)
    public function ipn(Request $req)
    {
        Log::info('MoMo IPN HIT', $req->all());

        $partnerCode  = $req->input('partnerCode');
        $orderId      = $req->input('orderId');
        $requestId    = $req->input('requestId');
        $amount       = $req->input('amount');
        $orderInfo    = $req->input('orderInfo');
        $orderType    = $req->input('orderType');
        $transId      = $req->input('transId');
        $resultCode   = (int) $req->input('resultCode');
        $message      = $req->input('message');
        $payType      = $req->input('payType');
        $responseTime = $req->input('responseTime');
        $extraData    = $req->input('extraData');
        $signature    = $req->input('signature');

        $accessKey = config('momo.accessKey');
        $secretKey = config('momo.secretKey');

        $raw = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&message={$message}"
             . "&orderId={$orderId}&orderInfo={$orderInfo}&orderType={$orderType}&partnerCode={$partnerCode}"
             . "&payType={$payType}&requestId={$requestId}&responseTime={$responseTime}"
             . "&resultCode={$resultCode}&transId={$transId}";
        $mySig = hash_hmac('sha256', $raw, $secretKey);

        if ($mySig !== $signature) {
            Log::warning('MoMo IPN invalid signature', ['orderId' => $orderId]);
            return response('', 204);
        }

        $data  = json_decode(base64_decode($extraData), true);
        $oid   = $data['order_id'] ?? 0;
        $order = Order::find($oid);
        if (!$order) {
            Log::warning('MoMo IPN order not found', ['oid' => $oid]);
            return response('', 204);
        }

        if ($resultCode === 0) {
            $order->payment_status = 'paid';
            $order->payment_ref    = (string) $transId;
            $order->payment_at     = now();
            $order->status         = 1; // tuỳ hệ thống

            // ✅ Ghi nhận usage cho mã giảm giá (nếu có) — chống ghi trùng
            try {
                if (
                    $order->discount_amount > 0 &&
                    $order->coupon_code &&
                    Schema::hasTable('ptdt_coupon_usage')
                ) {
                    $coupon = Coupon::byCode($order->coupon_code)->first();
                    if ($coupon) {
                        DB::transaction(function () use ($coupon, $order) {
                            $exists = DB::table('ptdt_coupon_usage')
                                ->where('order_id', $order->id)
                                ->exists();
                            if (!$exists) {
                                DB::table('ptdt_coupon_usage')->insert([
                                    'coupon_id' => $coupon->id,
                                    'user_id'   => $order->user_id ?: null,
                                    'order_id'  => $order->id,
                                    'code'      => $coupon->code,
                                    'discount_amount' => $order->discount_amount,
                                    'created_at'=> now(),
                                    'updated_at'=> now(),
                                ]);
                                $coupon->increment('used_count');
                            }
                        });
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Coupon usage log failed', ['order_id'=>$order->id, 'err'=>$e->getMessage()]);
            }
        } else {
            $order->payment_status = 'failed';
        }
        $order->save();

        if (class_exists(Payment::class) && Schema::hasTable('ptdt_payment')) {
            $payment = Payment::where('request_id', $requestId)
                        ->orWhere('order_code', $orderId)
                        ->orWhere('order_id', $order->id)
                        ->latest()->first();

            if (!$payment) {
                $payment = new Payment();
                $payment->order_id   = $order->id;
                $payment->provider   = 'momo';
                $payment->request_id = $requestId;
                $payment->order_code = $orderId;
                $payment->amount     = (int) $amount;
            }
            $payment->trans_id    = $transId;
            $payment->result_code = $resultCode;
            $payment->message     = $message;
            $payment->ipn_payload = $req->all();
            $payment->status      = $resultCode === 0 ? 'paid' : 'failed';
            if ($resultCode === 0 && !$payment->paid_at) $payment->paid_at = now();
            $payment->save();
        }

        return response('', 204);
    }

    public function check(Request $req)
    {
        $orderCode = $req->query('order_code');
        if (!$orderCode) {
            return response()->json(['ok' => false, 'message' => 'Missing order_code'], 422);
        }

        $payment = (class_exists(Payment::class) && Schema::hasTable('ptdt_payment'))
            ? Payment::where('order_code', $orderCode)->latest()->first()
            : null;

        $order = $payment && $payment->order_id ? Order::find($payment->order_id) : null;

        return response()->json([
            'ok'             => true,
            'payment_status' => $payment->status ?? null,
            'result_code'    => $payment->result_code ?? null,
            'order_status'   => $order->payment_status ?? null,
            'order_id'       => $order->id ?? null,
        ]);
    }

    public function return(Request $req)
    {
        return response()->json([
            'resultCode' => (int) $req->input('resultCode'),
            'orderId'    => $req->input('orderId'),
            'message'    => $req->input('message'),
        ]);
    }
}
