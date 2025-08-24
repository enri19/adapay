<?php
namespace App\Services\Mikrotik;

use RouterOS\Client as Ros;
use RouterOS\Query;
use Throwable;

class RouterOSClient implements MikrotikClient
{
    /** @var array */
    private $cfg;

    public function __construct(array $config = [])
    {
        // default dari env/config; bisa dioverride via withConfig()
        $this->cfg = array_merge([
            'host'    => env('MIKROTIK_HOST'),
            'user'    => env('MIKROTIK_USER'),
            'pass'    => env('MIKROTIK_PASS'),
            'port'    => (int) env('MIKROTIK_PORT', 8728),
            'timeout' => 5,
            'ssl'     => false, // pakai api-ssl (8729) set true
        ], $config);
    }

    /** Agar kompatibel dengan HotspotProvisioner::pushToMikrotik() */
    public function withConfig(array $config): self
    {
        return new self(array_filter([
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'user' => $config['user'] ?? null,
            'pass' => $config['pass'] ?? null,
            'ssl'  => isset($config['port']) && (int)$config['port'] === 8729 ? true : null,
        ], fn($v) => !is_null($v)));
    }

    public function createHotspotUser(string $username, string $password, ?string $profile, ?string $comment, ?string $limitUptime): void
    {
        $cli = new Ros([
            'host'    => $this->cfg['host'],
            'user'    => $this->cfg['user'],
            'pass'    => $this->cfg['pass'],
            'port'    => (int) $this->cfg['port'],
            'timeout' => (int) $this->cfg['timeout'],
            'ssl'     => (bool) $this->cfg['ssl'],
        ]);

        // idempotent: kalau sudah ada → update; kalau belum → add
        $print = (new Query('/ip/hotspot/user/print'))->where('name', $username);
        $rows  = $cli->query($print)->read();
        $id    = $rows[0]['.id'] ?? null;

        if ($id) {
            $set = (new Query('/ip/hotspot/user/set'))
                ->equal('.id', $id)
                ->equal('password', $password);
            if ($profile)     $set->equal('profile', $profile);
            if ($comment)     $set->equal('comment', $comment);
            if ($limitUptime) $set->equal('limit-uptime', $limitUptime);
            $cli->query($set);
        } else {
            $add = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $username)
                ->equal('password', $password);
            if ($profile)     $add->equal('profile', $profile);
            if ($comment)     $add->equal('comment', $comment);
            if ($limitUptime) $add->equal('limit-uptime', $limitUptime);
            $cli->query($add);
        }
    }
}
