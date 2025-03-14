<?php
return [
    'serverKey' => env('MIDTRANS_SERVER_KEY'),
    'isProduction' => env('MIDTRANS_IS_PRODUCTION', false),
    'isSanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is3ds' => env('MIDTRANS_IS_3DS', false),
    'merchantId' => env('MIDTRANS_MERCHANT_ID'),
    'clientKey' => env('MIDTRANS_CLIENT_KEY'),
];
