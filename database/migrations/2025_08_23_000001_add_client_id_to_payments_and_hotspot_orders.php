<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('payments')) {
      Schema::table('payments', function (Blueprint $table) {
        if (!Schema::hasColumn('payments', 'client_id')) {
          $table->string('client_id', 12)->nullable()->after('order_id');
        }
      });
    }
    if (Schema::hasTable('hotspot_orders')) {
      Schema::table('hotspot_orders', function (Blueprint $table) {
        if (!Schema::hasColumn('hotspot_orders', 'client_id')) {
          $table->string('client_id', 12)->nullable()->after('order_id');
        }
      });
    }
  }
  public function down(): void {
    if (Schema::hasTable('payments')) {
      Schema::table('payments', function (Blueprint $table) {
        if (Schema::hasColumn('payments', 'client_id')) $table->dropColumn('client_id');
      });
    }
    if (Schema::hasTable('hotspot_orders')) {
      Schema::table('hotspot_orders', function (Blueprint $table) {
        if (Schema::hasColumn('hotspot_orders', 'client_id')) $table->dropColumn('client_id');
      });
    }
  }
};
