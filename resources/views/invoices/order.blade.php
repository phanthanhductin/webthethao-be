<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    .row{display:flex;justify-content:space-between;margin-bottom:8px}
    h1{font-size:20px;margin:0 0 10px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #ddd;padding:6px;text-align:left}
    th{background:#f3f4f6}
    .right{text-align:right}
  </style>
</head>
<body>
  <h1>HÓA ĐƠN BÁN HÀNG</h1>

  <div class="row">
    <div>
      <strong>{{ $shop['name'] }}</strong><br>
      {{ $shop['addr'] }}<br>
      {{ $shop['phone'] }} - {{ $shop['email'] }}
    </div>
    <div>
      <strong>Mã đơn:</strong> {{ $order->code ?? $order->id }}<br>
      <strong>Ngày:</strong> {{ optional($order->created_at)->format('d/m/Y H:i') }}<br>
      <strong>PTTT:</strong> {{ strtoupper($order->payment_method ?? '—') }}<br>
      <strong>TT thanh toán:</strong> {{ $order->payment_status ?? 'pending' }}
    </div>
  </div>

  <div class="row">
    <div>
      <strong>Khách hàng</strong><br>
      {{ $order->name ?? $user->name }}<br>
      {{ $order->phone ?? $user->phone }}<br>
      {{ $order->address }}
    </div>
    <div></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Sản phẩm</th>
        <th class="right">SL</th>
        <th class="right">Đơn giá</th>
        <th class="right">Thành tiền</th>
      </tr>
    </thead>
    <tbody>
    @php $running = 0; @endphp
    @foreach($order->details as $i => $d)
      @php
        // Dùng đúng field của bạn
        $qty   = (int)($d->qty ?? $d->quantity ?? 1);
        $price = (int)($d->price_buy ?? $d->price ?? $d->unit_price ?? 0);
        // amount đã lưu sẵn thì ưu tiên, không thì tự tính
        $line  = (int)($d->amount ?? ($price * $qty));
        $running += $line;
      @endphp
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $d->product->name ?? $d->product_name ?? 'Sản phẩm' }}</td>
        <td class="right">{{ $qty }}</td>
        <td class="right">{{ number_format($price,0,',','.') }}</td>
        <td class="right">{{ number_format($line,0,',','.') }}</td>
      </tr>
    @endforeach
    </tbody>

    @php
      // Tạm tính: ưu tiên tổng cộng dồn trong vòng lặp
      $subtotal = (int)(
        $running
        ?? $order->computed_total
        ?? $order->total_price
        ?? $order->subtotal
        ?? 0
      );

      // Phí ship + giảm giá: gom hết các tên cột thường gặp
      $shipping = (int)($order->shipping_fee ?? $order->ship_fee ?? $order->shipping ?? 0);
      $discount = (int)($order->discount ?? $order->coupon_discount ?? $order->promotion_discount ?? 0);

      // Tổng cộng
      $grand = max(0, $subtotal + $shipping - $discount);
    @endphp

    <tfoot>
      <tr>
        <td colspan="4" class="right">Tạm tính</td>
        <td class="right">{{ number_format($subtotal,0,',','.') }}</td>
      </tr>
      <tr>
        <td colspan="4" class="right">Phí vận chuyển</td>
        <td class="right">{{ number_format($shipping,0,',','.') }}</td>
      </tr>
      <tr>
        <td colspan="4" class="right">Giảm giá</td>
        <td class="right">-{{ number_format($discount,0,',','.') }}</td>
      </tr>
      <tr>
        <td colspan="4" class="right"><strong>Tổng cộng</strong></td>
        <td class="right"><strong>{{ number_format($grand,0,',','.') }}</strong></td>
      </tr>
    </tfoot>
  </table>

  <p style="margin-top:16px;">Ghi chú: {{ $order->note ?? '' }}</p>
  <p><em>Cảm ơn bạn đã mua sắm tại {{ $shop['name'] }}!</em></p>
</body>
</html>
