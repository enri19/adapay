<?php
namespace App\Support;

use App\Models\Client;
use App\Models\HotspotOrder;
use App\Support\OrderId;

class PublicUrl
{
    /**
     * Dapatkan base URL publik untuk client tertentu.
     * Prioritas: APP_WILDCARD_BASE → clients.public_base_url → APP_URL
     */
    public static function baseForClient($clientId)
    {
        $clientId = $clientId ? strtoupper((string) $clientId) : null;

        // env / config wildcard, contoh: https://%s.adanih.info atau https://{client}.adanih.info
        $wild = config('app.wildcard_base', env('APP_WILDCARD_BASE'));
        if ($wild && $clientId) {
            if (strpos($wild, '%s') !== false) {
                return rtrim(sprintf($wild, strtolower($clientId)), '/');
            }
            if (strpos($wild, '{client}') !== false) {
                return rtrim(str_replace('{client}', strtolower($clientId), $wild), '/');
            }
        }

        // kolom DB opsional
        if ($clientId) {
            $dbBase = Client::where('client_id', $clientId)->value('public_base_url');
            if ($dbBase) return rtrim($dbBase, '/');
        }

        // fallback
        return rtrim((string) config('app.url', env('APP_URL', 'http://localhost')), '/');
    }

    /**
     * URL halaman order publik berbasis wildcard.
     */
    public static function order($orderId)
    {
        $order = HotspotOrder::where('order_id', $orderId)->first();
        $clientId = ($order && $order->client_id) ? $order->client_id : OrderId::client($orderId);
        $base = self::baseForClient($clientId);
        return $base . '/hotspot/order/' . rawurlencode($orderId);
    }
}
