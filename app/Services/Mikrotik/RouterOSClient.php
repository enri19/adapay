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
        $this->cfg = array_merge([
            'host'    => env('MIKROTIK_HOST'),
            'user'    => env('MIKROTIK_USER'),
            'pass'    => env('MIKROTIK_PASS'),
            'port'    => (int) env('MIKROTIK_PORT', 8728),
            'timeout' => 5,
            'ssl'     => false,
        ], $config);
    }

    /** biar kompatibel dgn HotspotProvisioner */
    public function withConfig(array $config): self
    {
        // bawa serta timeout kalau ada; ssl auto true kalau port 8729 (kalau tidak dipaksa)
        $next = [
            'host'    => $config['host'] ?? $this->cfg['host'],
            'port'    => isset($config['port']) ? (int)$config['port'] : $this->cfg['port'],
            'user'    => $config['user'] ?? $this->cfg['user'],
            'pass'    => $config['pass'] ?? $this->cfg['pass'],
            'timeout' => $config['timeout'] ?? $this->cfg['timeout'],
            'ssl'     => array_key_exists('ssl',$config)
                         ? (bool)$config['ssl']
                         : ((isset($config['port']) && (int)$config['port']===8729) ? true : (bool)$this->cfg['ssl']),
        ];
        return new self($next);
    }

    /** ====== UTIL KONEKSI ====== */
    private function ros(): Ros
    {
        return new Ros([
            'host'    => $this->cfg['host'],
            'user'    => $this->cfg['user'],
            'pass'    => $this->cfg['pass'],
            'port'    => (int) $this->cfg['port'],
            'timeout' => (int) ($this->cfg['timeout'] ?? 10),
            'ssl'     => (bool) ($this->cfg['ssl'] ?? false),
        ]);
    }

    /** ping ringan: cukup /system/identity/print */
    public function ping(): void
    {
        $cli = $this->ros();
        $cli->query(new Query('/system/identity/print'))->read();
    }

    /** info ringkas sistem untuk tampilan Tools */
    public function getSystemInfo(): array
    {
        $cli = $this->ros();
        $id  = $cli->query(new Query('/system/identity/print'))->read();
        $rs  = $cli->query(new Query('/system/resource/print'))->read();

        return [
            'identity' => $id[0]['name'] ?? null,
            'board'    => $rs[0]['board-name'] ?? null,
            'version'  => $rs[0]['version'] ?? null,
            'uptime'   => $rs[0]['uptime'] ?? null,
        ];
    }

    /** eksekusi bebas */
    public function raw(string $path, array $params = []): array
    {
        $cli = $this->ros();
        $q = new Query($path);
        foreach ($params as $k => $v) {
            $q->equal($k, $v);
        }
        return $cli->query($q)->read();
    }

    /** daftar profile hotspot */
    public function listHotspotProfiles(): array
    {
        $rows = $this->raw('/ip/hotspot/user/profile/print');
        return array_values(array_filter(array_map(fn($r)=>$r['name'] ?? null, $rows)));
    }

    /** daftar server hotspot */
    public function listHotspotServers(): array
    {
        $rows = $this->raw('/ip/hotspot/print');
        return array_values(array_filter(array_map(fn($r)=>$r['name'] ?? null, $rows)));
    }

    /** ====== FITUR YANG SUDAH ADA ====== */
    public function createHotspotUser(string $username, string $password, ?string $profile, ?string $comment, ?string $limitUptime): void
    {
        $cli = $this->ros();

        // (opsional) deteksi server hotspot (mis. "hotspot1")
        $serverName = null;
        try {
            $sv = $cli->query(new Query('/ip/hotspot/print'))->read();
            $serverName = $sv[0]['name'] ?? null;
        } catch (Throwable $ignored) {}

        // Idempotent
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
            $resp = $cli->query($set)->read();
            if (!empty($resp['after']['message'] ?? null)) {
                throw new \RuntimeException($resp['after']['message']);
            }
        } else {
            $add = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $username)
                ->equal('password', $password);
            if ($profile)     $add->equal('profile', $profile);
            if ($comment)     $add->equal('comment', $comment);
            if ($limitUptime) $add->equal('limit-uptime', $limitUptime);
            if ($serverName)  $add->equal('server', $serverName); // opsional

            $resp = $cli->query($add)->read();
            if (!empty($resp['after']['message'] ?? null)) {
                throw new \RuntimeException($resp['after']['message']);
            }
        }

        // verifikasi
        $ok = $cli->query((new Query('/ip/hotspot/user/print'))->where('name', $username))->read();
        if (empty($ok)) throw new \RuntimeException('Hotspot user not found after create');
    }

    /** ====== BARU: LOGIN HOTSPOT VIA API ====== */
    public function hotspotActiveLogin(string $ip, string $username, string $password, ?string $mac = null): void
    {
        // RouterOS 6.34+: /ip/hotspot/active/login (butuh ip,user,password; opsional mac-address)
        $cli = $this->ros();
        $q = (new Query('/ip/hotspot/active/login'))
            ->equal('ip', $ip)
            ->equal('user', $username)
            ->equal('password', $password);
        if ($mac) $q->equal('mac-address', $mac);

        $resp = $cli->query($q)->read();
        // Mikrotik biasanya kosong saat sukses; kalau ada 'message' → error
        if (is_array($resp) && isset($resp['!trap'][0]['message'])) {
            throw new \RuntimeException($resp['!trap'][0]['message']);
        }
    }
}
