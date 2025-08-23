<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClientIdToHotspotOrdersTable extends Migration
{
  public function up()
  {
    if (Schema::hasTable('hotspot_orders') && !Schema::hasColumn('hotspot_orders','client_id')) {
      Schema::table('hotspot_orders', function (Blueprint $t) {
        $t->string('client_id', 12)->nullable()->after('order_id');
        $t->index('client_id');
      });
    }
  }

  public function down()
  {
    if (Schema::hasTable('hotspot_orders') && Schema::hasColumn('hotspot_orders','client_id')) {
      Schema::table('hotspot_orders', function (Blueprint $t) {
        $t->dropIndex(['client_id']);
        $t->dropColumn('client_id');
      });
    }
  }
}
