<?php
return [
  'server_key' => env('MIDTRANS_SERVER_KEY', ''),
  'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
  'verify_signature' => env('MIDTRANS_VERIFY_SIGNATURE', app()->environment('production')),
];