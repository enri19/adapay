<?php

return [
  'uri'     => env('WA_GATEWAY_API_URI', ''),
  'token'   => env('WA_GATEWAY_API_TOKEN', ''),
  'user_id' => env('WA_GATEWAY_API_USERID', ''),
  'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 15),
];
