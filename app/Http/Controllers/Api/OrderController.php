<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Coupon;

class OrderController extends Controller
{
    /* =====================================================
     *  Helper: build URL ảnh tuyệt đối
     * ===================================================== */
    private function makeThumbUrl(?string $path): ?string
    {
        if (!$path) return null;

        if (preg_match('~^https?://~i', $path)) return $path; // absolute
        if (str_starts_with($path, '/storage/')) return url($path);

        $clean = ltrim($path, '/');
        if (str_starts_with($clean, 'public/')) $clean = substr($clean, 7);

        return url(Storage::url($clean)); // yêu cầu đã storage:link
    }

    /* =====================================================
     *  ĐẶT HÀNG (COD/BANK) — có áp mã giảm giá
     *  POST /api/checkout
     * ===================================================== */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'customer_name'   => 'required|string|max:100',
            'phone'           => 'required|string|max:20',
            'address'         => 'required|string|max:255',
            'email'           => 'required|email|max:255',
            'items'           => 'required|array|min:1',
            'items.*.id'      => 'required|integer',
            'items.*.name'    => 'required|string',
            'items.*.price'   => 'required|numeric|min:0',
            'items.*.qty'     => 'required|integer|min:1',
            'coupon_code'     => 'nullable|string|max:50',   // 👈 mã giảm
            'payment_method'  => 'nullable|string|max:20',   // COD/Bank...
        ]);

        return DB::transaction(function () use ($data) {
            // 1) Tạo đơn
            $order = Order::create([
                'name'            => $data['customer_name'],
                'phone'           => $data['phone'],
                'email'           => $data['email'],
                'address'         => $data['address'],
                'user_id'         => Auth::id() ?? null,
                'status'          => 0,            // pending
                'note'            => null,
                'payment_method'  => $data['payment_method'] ?? 'cod',
                'payment_status'  => 'pending',
            ]);

            // gắn user nếu trùng email
            if (!$order->user_id && !empty($order->email)) {
                if ($u = \App\Models\User::where('email', $order->email)->first()) {
                    $order->user_id = $u->id;
                    $order->save();
                }
            }

            // 2) Ghi chi tiết + trừ kho + log kho
            $subtotal = 0;
            foreach ($data['items'] as $it) {
                $qty = (int) $it['qty'];
                $price = (float) $it['price'];

                $product = Product::lockForUpdate()->find($it['id']);
                if (!$product) throw new \Exception("Sản phẩm ID {$it['id']} không tồn tại");
                if ((int)($product->qty ?? 0) < $qty) {
                    throw new \Exception("Sản phẩm '{$product->name}' không đủ tồn");
                }

                // trừ kho
                $product->decrement('qty', $qty);

                // log xuất kho
                StockMovement::create([
                    'product_id' => $product->id,
                    'type'       => 'export',
                    'qty_change' => -$qty,
                    'ref_type'   => 'order',
                    'ref_id'     => $order->id,
                    'note'       => 'Trừ kho khi đặt hàng',
                    'created_by' => Auth::id() ?? null,
                ]);

                // chi tiết đơn
                OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'price_buy'  => $price,
                    'qty'        => $qty,
                    'amount'     => $price * $qty,
                ]);

                $subtotal += $price * $qty;
            }
            $subtotal = round($subtotal);

            // 3) Áp mã giảm
            $discount   = 0;
            $couponCode = null;
            $coupon     = null;

            if (!empty($data['coupon_code'])) {
                $coupon = Coupon::byCode($data['coupon_code'])->first();
                if ($coupon) {
                    [$ok, $msg] = $coupon->canUse($order->user_id ?: null, $subtotal);
                    if ($ok) {
                        $discount   = round($coupon->calcDiscount($subtotal));
                        $couponCode = $coupon->code;
                    }
                    // nếu ko ok → bỏ qua, vẫn cho đặt hàng
                }
            }

            // 4) Tính total & lưu vào đơn (có guard cột)
            $total = max(0, $subtotal - $discount);

            if (Schema::hasColumn('ptdt_order', 'coupon_code'))     $order->coupon_code     = $couponCode;
            if (Schema::hasColumn('ptdt_order', 'discount_amount')) $order->discount_amount = $discount;
            if (Schema::hasColumn('ptdt_order', 'subtotal'))        $order->subtotal        = $subtotal;
            if (Schema::hasColumn('ptdt_order', 'total'))           $order->total           = $total;

            $order->payment_amount = $total;
            $order->note = "Tổng đơn: {$subtotal} đ" . ($discount > 0 ? " | Giảm: -{$discount} đ" : "");
            $order->save();

            // 5) (Tuỳ chọn) Ghi usage ngay lúc tạo đơn
            // Nếu bạn chỉ muốn ghi khi đã thanh toán thành công, hãy xoá block này,
            // và để PaymentController@ipn ghi usage (đã có).
            if ($coupon && $couponCode && $discount > 0 && Schema::hasTable('ptdt_coupon_usage')) {
                DB::table('ptdt_coupon_usage')->insert([
                    'coupon_id'       => $coupon->id,
                    'user_id'         => $order->user_id ?: null,
                    'order_id'        => $order->id,
                    'code'            => $couponCode,
                    'discount_amount' => $discount,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                $coupon->increment('used_count');
            }

            return response()->json([
                'message'  => 'Đặt hàng thành công',
                'order_id' => $order->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total'    => $total,
            ], 201);
        });
    }

    /* =====================================================
     *  ĐƠN HÀNG CỦA TÔI (đăng nhập)
     * ===================================================== */
    public function mine(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $orders = Order::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (!empty($user->email)) $q->orWhere('email', $user->email);
                if (!empty($user->phone)) $q->orWhere('phone', $user->phone);
            })
            ->with(['details.product'])
            ->withSum('details as computed_total', 'amount')
            ->orderByDesc('created_at')
            ->get();

        $data = $orders->map(function ($order) {
            $items = $order->details->map(function ($d) {
                $p = $d->product;
                return [
                    'product_id' => $d->product_id,
                    'name'       => $p->name ?? null,
                    'thumbnail'  => $p?->thumbnail_url ?? $p?->thumbnail ?? null,
                    'price'      => (float) $d->price_buy,
                    'qty'        => (int) $d->qty,
                    'subtotal'   => isset($d->amount) ? (float) $d->amount : ((float) $d->price_buy * (int) $d->qty),
                ];
            })->values();

            $total = $order->computed_total ?? $items->sum(fn ($it) => $it['subtotal']);

            return [
                'id'         => $order->id,
                'code'       => (string) ($order->code ?? $order->id),
                'name'       => $order->name,
                'email'      => $order->email,
                'phone'      => $order->phone,
                'address'    => $order->address,
                'note'       => $order->note,
                'status'     => (int) ($order->status ?? 0),
                'subtotal'   => (float) ($order->subtotal ?? $total),
                'discount'   => (float) ($order->discount_amount ?? 0),
                'total'      => (float) ($order->total ?? $total),
                'created_at' => optional($order->created_at)->toDateTimeString(),
                'items'      => $items,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /* =====================================================
     *  DANH SÁCH ĐƠN (public + adminIndex reuse)
     * ===================================================== */
    public function index(Request $request)
    {
        $search  = trim((string) $request->query('search', ''));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $status  = $request->has('status') ? $request->integer('status') : null;

        $q = Order::query()
            ->withCount('details')
            ->withSum('details as computed_total', 'amount');

        if (!is_null($status)) $q->where('status', $status);

        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        $orders = $q->latest('id')->paginate($perPage);

        $orders->getCollection()->transform(function ($o) {
            $o->total = (float) ($o->total ?? $o->computed_total ?? 0);
            return $o;
        });

        return response()->json($orders);
    }

    public function adminIndex(Request $request)
    {
        return $this->index($request);
    }

    /* =====================================================
     *  CHI TIẾT ĐƠN
     * ===================================================== */
    public function show($id)
    {
        $order = Order::with(['details.product:id,name,thumbnail'])
            ->withSum('details as computed_total', 'amount')
            ->find($id);

        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $items = $order->details->map(function ($d) {
            $p = $d->product;
            $rawImg = $p?->thumbnail_url ?? $p?->thumbnail ?? $p?->image ?? null;
            $img = $this->makeThumbUrl($rawImg);

            return [
                'id'            => $d->id,
                'product_id'    => $d->product_id,
                'name'          => $p?->name ?? 'Sản phẩm',
                'price'         => (float) $d->price_buy,
                'qty'           => (int) $d->qty,
                'subtotal'      => (float) ($d->amount ?? $d->price_buy * $d->qty),
                'thumbnail_url' => $img,
            ];
        });

        $computed = (float) ($order->computed_total ?? $items->sum(fn ($it) => $it['subtotal']));
        $subtotal = (float) ($order->subtotal ?? $computed);
        $discount = (float) ($order->discount_amount ?? 0);
        $total    = (float) ($order->total ?? ($subtotal - $discount));

        return response()->json([
            'id'              => $order->id,
            'code'            => (string) ($order->code ?? $order->id),
            'name'            => $order->name,
            'email'           => $order->email,
            'phone'           => $order->phone,
            'address'         => $order->address,
            'note'            => $order->note,
            'status'          => (int) ($order->status ?? 0),
            'coupon_code'     => $order->coupon_code,
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'total'           => $total,
            'created_at'      => $order->created_at,
            'updated_at'      => $order->updated_at,
            'items'           => $items,
        ]);
    }

    /* =====================================================
     *  CẬP NHẬT ĐƠN (trạng thái / ghi chú)
     *  PUT /api/admin/orders/{id}
     * ===================================================== */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'note' => 'sometimes|nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($id, $data, $request) {
            /** @var Order|null $order */
            $order = Order::with('details')->lockForUpdate()->find($id);
            if (!$order) return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);

            $oldStatus = (int) ($order->status ?? 0);
            $newStatus = $oldStatus;

            if ($request->filled('status')) {
                $raw = trim((string) $request->input('status'));
                $map = [
                    'pending' => 0, 'chờ xác nhận' => 0, 'cho xac nhan' => 0,
                    'confirmed' => 1, 'đã xác nhận' => 1, 'da xac nhan' => 1,
                    'ready' => 2, 'chờ giao hàng' => 2, 'cho giao hang' => 2, 'đóng gói' => 2,
                    'shipping' => 3, 'đang giao' => 3, 'dang giao' => 3,
                    'delivered' => 4, 'đã giao' => 4, 'da giao' => 4, 'hoan tat' => 4,
                    'canceled' => 5, 'cancelled' => 5, 'hủy' => 5, 'huy' => 5,
                ];

                $key = mb_strtolower($raw, 'UTF-8');
                $key_noaccent = iconv('UTF-8', 'ASCII//TRANSLIT', $key);
                $key_noaccent = $key_noaccent ? strtolower($key_noaccent) : $key;

                if (array_key_exists($key, $map)) $newStatus = (int) $map[$key];
                elseif (array_key_exists($key_noaccent, $map)) $newStatus = (int) $map[$key_noaccent];
                else {
                    $num = filter_var($raw, FILTER_VALIDATE_INT);
                    if ($num === false) {
                        return response()->json([
                            'message' => 'Giá trị status không hợp lệ.',
                            'errors'  => ['status' => ['Status phải là 0..5 hoặc nhãn hợp lệ']],
                        ], 422);
                    }
                    $newStatus = (int) $num;
                }

                if ($newStatus < 0 || $newStatus > 5) {
                    return response()->json([
                        'message' => 'Giá trị status không hợp lệ. Chỉ nhận 0..5.',
                        'errors'  => ['status' => ['Status phải nằm trong khoảng 0..5']],
                    ], 422);
                }
            }

            if (in_array($oldStatus, [4, 5], true)) {
                return response()->json(['message' => 'Đơn đã hoàn tất hoặc hủy, không thể cập nhật.'], 400);
            }

            // hủy → hoàn kho
            if ($newStatus === 5 && $oldStatus !== 5) {
                return $this->cancel($id);
            }

            $order->status = $newStatus;
            if (array_key_exists('note', $data)) $order->note = $data['note'];
            $order->save();

            // Trả về giống show()
            $order->load(['details.product:id,name,thumbnail']);
            $items = $order->details->map(function ($d) {
                $p = $d->product;
                $rawImg = $p?->thumbnail_url ?? $p?->thumbnail ?? $p?->image ?? null;
                $img = $this->makeThumbUrl($rawImg);
                return [
                    'id'            => $d->id,
                    'product_id'    => $d->product_id,
                    'name'          => $p?->name ?? 'Sản phẩm',
                    'price'         => (float) $d->price_buy,
                    'qty'           => (int) $d->qty,
                    'subtotal'      => (float) ($d->amount ?? $d->price_buy * $d->qty),
                    'thumbnail_url' => $img,
                ];
            });

            $computed = (float) ($order->details->sum('amount') ?? 0);
            $subtotal = (float) ($order->subtotal ?? $computed);
            $discount = (float) ($order->discount_amount ?? 0);
            $total    = (float) ($order->total ?? ($subtotal - $discount));

            return response()->json([
                'message' => 'Cập nhật trạng thái thành công.',
                'data'    => [
                    'id'              => $order->id,
                    'code'            => (string) ($order->code ?? $order->id),
                    'name'            => $order->name,
                    'email'           => $order->email,
                    'phone'           => $order->phone,
                    'address'         => $order->address,
                    'note'            => $order->note,
                    'status'          => (int) ($order->status ?? 0),
                    'coupon_code'     => $order->coupon_code,
                    'subtotal'        => $subtotal,
                    'discount_amount' => $discount,
                    'total'           => $total,
                    'created_at'      => $order->created_at,
                    'updated_at'      => $order->updated_at,
                    'items'           => $items,
                ],
            ]);
        });
    }

    /* =====================================================
     *  THEO DÕI ĐƠN (code + phone)
     * ===================================================== */
    public function track(Request $request)
    {
        $code  = trim((string) $request->query('code', ''));
        $phone = trim((string) $request->query('phone', ''));

        if ($code === '' && $phone === '') {
            return response()->json(['message' => 'Thiếu code hoặc phone'], 422);
        }

        $q = Order::query()
            ->with(['details.product:id,thumbnail,name'])
            ->withSum('details as computed_total', 'amount');

        if ($phone !== '') $q->where('phone', $phone);

        if ($code !== '') {
            if (ctype_digit($code)) {
                $q->where('id', (int) $code);
            } else {
                $table = (new Order)->getTable();
                if (Schema::hasColumn($table, 'code')) $q->where('code', $code);
            }
        }

        $order = $q->latest('id')->first();
        if (!$order) return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);

        return $this->show($order->id);
    }

    /* =====================================================
     *  HỦY ĐƠN — hoàn tồn kho
     * ===================================================== */
    public function cancel($id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::with('details')->lockForUpdate()->find($id);
            if (!$order) return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);

            if (in_array($order->status, [4, 5])) {
                return response()->json(['message' => 'Đơn hàng này không thể hủy.'], 400);
            }

            foreach ($order->details as $d) {
                Product::where('id', $d->product_id)->increment('qty', $d->qty);

                StockMovement::create([
                    'product_id' => $d->product_id,
                    'type'       => 'return',
                    'qty_change' => (int) $d->qty,
                    'ref_type'   => 'order_cancel',
                    'ref_id'     => $order->id,
                    'note'       => 'Hoàn kho khi hủy đơn',
                    'created_by' => Auth::id() ?? null,
                ]);
            }

            $order->status = 5; // canceled
            $order->save();

            return response()->json([
                'message' => 'Đơn hàng đã được hủy và hoàn tồn kho!',
                'data'    => $order,
            ]);
        });
    }

    /* =====================================================
     *  INVOICE PDF (KH & Admin)
     * ===================================================== */
    public function invoicePdf($id)
    {
        $user = auth()->user();

        $order = Order::with(['details.product'])
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (!empty($user->email)) $q->orWhere('email', $user->email);
                if (!empty($user->phone)) $q->orWhere('phone', $user->phone);
            })
            ->firstOrFail();

        $data = [
            'order' => $order,
            'user'  => $user,
            'shop'  => [
                'name'  => 'THETHAO SPORTS',
                'email' => 'support@thethao.vn',
                'phone' => '0123 456 789',
                'addr'  => 'Địa chỉ cửa hàng…',
            ],
        ];

        $pdf = PDF::loadView('invoices.order', $data)->setPaper('a4');
        $filename = 'invoice-' . ($order->code ?? $order->id) . '.pdf';
        return $pdf->download($filename);
    }
}
