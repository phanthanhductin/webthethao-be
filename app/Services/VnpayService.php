<?php

namespace App\Services;

class VnpayService
{
    public function config(): array
    {
        return [
            'vnp_TmnCode'   => env('VNPAY_TMN_CODE'),
            'vnp_HashSecret'=> env('VNPAY_HASH_SECRET'),
            'vnp_Url'       => rtrim(env('VNPAY_PAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'), '/'),
            'vnp_ReturnUrl' => env('VNPAY_RETURN_URL'),
            'vnp_IpnUrl'    => env('VNPAY_IPN_URL'),
        ];
    }

    protected function clientIp(): string
    {
        $ips = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($ips as $ip) {
            if ($ip) {
                $p = explode(',', $ip);
                return trim($p[0]);
            }
        }
        return '127.0.0.1';
    }

    /**
     * amount: số tiền VND (VD 150000). VNPAY yêu cầu truyền amount * 100.
     */
    public function createPaymentUrl(string $orderId, int $amount, string $orderInfo, ?string $bankCode = null): string
    {
        $cfg = $this->config();

        $vnp_TxnRef = 'ORD'.$orderId.'_'.time();
        $vnp_Amount = $amount * 100; // VNPAY yêu cầu nhân 100
        $vnp_Locale = 'vn';
        $vnp_OrderInfo = $orderInfo;
        $vnp_OrderType = 'other';
        $vnp_IpAddr = $this->clientIp();
        $vnp_ExpireDate = date('YmdHis', time() + 15 * 60); // hết hạn sau 15 phút

        $inputData = [
            'vnp_Version'   => '2.1.0',
            'vnp_Command'   => 'pay',
            'vnp_TmnCode'   => $cfg['vnp_TmnCode'],
            'vnp_Amount'    => $vnp_Amount,
            'vnp_CurrCode'  => 'VND',
            'vnp_TxnRef'    => $vnp_TxnRef,
            'vnp_OrderInfo' => $vnp_OrderInfo,
            'vnp_OrderType' => $vnp_OrderType,
            'vnp_Locale'    => $vnp_Locale,
            'vnp_ReturnUrl' => $cfg['vnp_ReturnUrl'],
            'vnp_IpAddr'    => $vnp_IpAddr,
            'vnp_CreateDate'=> date('YmdHis'),
            'vnp_ExpireDate'=> $vnp_ExpireDate,
        ];

        if (!empty($cfg['vnp_IpnUrl'])) {
            $inputData['vnp_NotifyUrl'] = $cfg['vnp_IpnUrl']; // IPN
        }
        if ($bankCode) {
            $inputData['vnp_BankCode'] = $bankCode;
        }

        ksort($inputData);
        $query = [];
        $hashdata = [];
        foreach ($inputData as $key => $value) {
            $hashdata[] = urlencode($key) . '=' . urlencode((string)$value);
            $query[]    = urlencode($key) . '=' . urlencode((string)$value);
        }
        $hashString = implode('&', $hashdata);
        $vnpSecureHash = hash_hmac('sha512', $hashString, $cfg['vnp_HashSecret']);

        return $cfg['vnp_Url'] . '?' . implode('&', $query) . '&vnp_SecureHash=' . $vnpSecureHash;
    }

    public function verify(array $params): bool
    {
        $cfg = $this->config();
        $vnp_SecureHash = $params['vnp_SecureHash'] ?? '';
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);
        $hashData = [];
        foreach ($params as $k => $v) {
            $hashData[] = urlencode($k) . '=' . urlencode((string)$v);
        }
        $secureHash = hash_hmac('sha512', implode('&', $hashData), $cfg['vnp_HashSecret']);
        return hash_equals($secureHash, $vnp_SecureHash);
    }
}
