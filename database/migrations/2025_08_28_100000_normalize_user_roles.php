<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class NormalizeUserRoles extends Migration
{
  public function up()
  {
    // Pastikan kolom role ada
    if (!Schema::hasColumn('users','role')) {
      Schema::table('users', function (Blueprint $table) {
        $table->string('role', 20)->default('user')->index();
      });
    }

    // Backfill role dari is_admin jika role kosong
    DB::statement("
      UPDATE users
      SET role = CASE WHEN COALESCE(is_admin,0)=1 THEN 'admin' ELSE 'user' END
      WHERE role IS NULL OR role = ''
    ");

    // Hapus kolom is_admin (setelah semua kode pindah pakai role)
    if (Schema::hasColumn('users','is_admin')) {
      Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('is_admin');
      });
    }
  }

  public function down()
  {
    // Kembalikan kolom is_admin (default 0), dan isi berdasarkan role
    if (!Schema::hasColumn('users','is_admin')) {
      Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_admin')->default(0)->index();
      });
    }

    DB::statement("
      UPDATE users
      SET is_admin = CASE WHEN role='admin' THEN 1 ELSE 0 END
    ");
  }
}
