<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddClientIdToHotspotVouchersTable extends Migration
{
  public function up()
  {
    if (Schema::hasTable('hotspot_vouchers')) {
      Schema::table('hotspot_vouchers', function (Blueprint $t) {
        if (!Schema::hasColumn('hotspot_vouchers', 'client_id')) {
          $t->string('client_id', 12)->nullable()->after('id');
          $t->index('client_id');
        }
        if (!Schema::hasColumn('hotspot_vouchers', 'is_active')) {
          $t->boolean('is_active')->default(true)->after('profile');
        }
      });

      // seed nilai default agar tampil di semua lokasi jika belum diset
      DB::table('hotspot_vouchers')->whereNull('client_id')->update(['client_id' => 'DEFAULT']);
    }
  }

  public function down()
  {
    if (Schema::hasTable('hotspot_vouchers')) {
      Schema::table('hotspot_vouchers', function (Blueprint $t) {
        if (Schema::hasColumn('hotspot_vouchers', 'client_id')) {
          $t->dropIndex(['client_id']);
          $t->dropColumn('client_id');
        }
        if (Schema::hasColumn('hotspot_vouchers', 'is_active')) {
          $t->dropColumn('is_active');
        }
      });
    }
  }
}
