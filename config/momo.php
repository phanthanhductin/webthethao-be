<?php
return [
  'partnerCode' => env('MOMO_PARTNER_CODE'),
  'accessKey'   => env('MOMO_ACCESS_KEY'),
  'secretKey'   => env('MOMO_SECRET_KEY'),
  'endpoint'    => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn'),
  'returnUrl'   => env('MOMO_RETURN_URL'),
  'ipnUrl'      => env('MOMO_IPN_URL'),
];
