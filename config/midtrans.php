<?php
return [
  'server_key' => env('MIDTRANS_SERVER_KEY', ''),
  'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
];