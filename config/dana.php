<?php

return [
  'env'            => env('DANA_ENV', 'sandbox'),
  'partner_id'     => env('DANA_PARTNER_ID'),
  'origin'         => env('DANA_ORIGIN'),
  'private_key'    => base_path(env('DANA_PRIVATE_KEY_PATH', 'storage/keys/dana_private_pkcs8.pem')),
  'public_key'     => base_path(env('DANA_PUBLIC_KEY_PATH', 'storage/keys/dana_gateway_public.pem')),
  'notify_path'    => env('DANA_NOTIFY_PATH', '/v1.0/debit/notify'),
  'channel_id'     => env('DANA_CHANNEL_ID'),
  'merchant_id'    => env('DANA_MERCHANT_ID'),
  'base_url'       => env('DANA_ENV') === 'production'
                        ? 'https://api.dana.id'
                        : 'https://api-sandbox.dana.id',
];
