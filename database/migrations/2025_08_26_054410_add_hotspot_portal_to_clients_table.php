<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'hotspot_portal')) {
                // simpan identifier/URL/tema portal hotspot per client
                $table->string('hotspot_portal', 255)
                      ->nullable()
                      ->after('portal_domain'); // taruh setelah portal_domain biar rapi
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'hotspot_portal')) {
                $table->dropColumn('hotspot_portal');
            }
        });
    }
};
