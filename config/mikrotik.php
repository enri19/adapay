<?php

return [
  // mapping client_id -> kredensial router
  'clients' => [
    'DEFAULT' => [
      'host' => env('MIKROTIK_DEFAULT_HOST'),
      'port' => (int) env('MIKROTIK_DEFAULT_PORT', 8728),
      'user' => env('MIKROTIK_DEFAULT_USER'),
      'pass' => env('MIKROTIK_DEFAULT_PASS'),
      'profile' => env('MIKROTIK_DEFAULT_PROFILE', 'default'),
    ],
    // contoh client lain:
    'C1' => [
      'host' => env('MIKROTIK_C1_HOST'),
      'port' => (int) env('MIKROTIK_C1_PORT', 8728),
      'user' => env('MIKROTIK_C1_USER'),
      'pass' => env('MIKROTIK_C1_PASS'),
      'profile' => env('MIKROTIK_C1_PROFILE', 'default'),
    ],
  ],
];
