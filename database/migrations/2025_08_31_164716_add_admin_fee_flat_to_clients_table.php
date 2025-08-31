<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('clients', function (Blueprint $table) {
      // kolom fee flat untuk admin per transaksi
      $table->integer('admin_fee_flat')
        ->default(0)
        ->after('is_active'); // sesuaikan letaknya dengan kolom yang ada
    });
  }

  public function down(): void
  {
    Schema::table('clients', function (Blueprint $table) {
      $table->dropColumn('admin_fee_flat');
    });
  }
};
