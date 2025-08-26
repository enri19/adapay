<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enable_push'    => 'boolean',
        'is_active'      => 'boolean',
        'router_port'    => 'integer',
        'hotspot_portal' => 'string', // cast baru
    ];

    // Mutator Laravel 8: trim & "" -> null
    public function setHotspotPortalAttribute($value)
    {
        if ($value === null) {
            $this->attributes['hotspot_portal'] = null;
            return;
        }
        $v = trim((string) $value);
        $this->attributes['hotspot_portal'] = ($v === '') ? null : $v;
    }

    // Scopes optional
    public function scopeHasHotspotPortal($q)
    {
        return $q->whereNotNull('hotspot_portal')->where('hotspot_portal', '!=', '');
    }

    public function scopePortal($q, $portal)
    {
        return $q->where('hotspot_portal', $portal);
    }

    public function getHotspotPortalEffectiveAttribute()
    {
        // fallback ke config kalau kolom null/kosong
        return $this->hotspot_portal ?: config('hotspot.portal_default');
    }

    public function getHotspotPortalHostAttribute()
    {
        $url = $this->hotspot_portal ?: config('hotspot.portal_default');
        return $url ? parse_url($url, PHP_URL_HOST) : null;
    }

}
