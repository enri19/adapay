<?php
return [
  'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
  'server_key' => env('MIDTRANS_SERVER_KEY', ''),
  'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
  'verify_signature' => (bool) (env('MIDTRANS_VERIFY_SIGNATURE', null) ?? (env('APP_ENV') === 'production')),
];