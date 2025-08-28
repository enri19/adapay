<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleAndClientToUsersTable extends Migration
{
  public function up()
  {
    // Tambah kolom "role" kalau belum ada
    Schema::table('users', function (Blueprint $table) {
      if (!Schema::hasColumn('users', 'role')) {
        $table->string('role', 20)->default('user')->index();
      }
    });

    // Tambah kolom "client_id" string(12) + FK ke clients.client_id (UNIQUE)
    if (Schema::hasTable('clients')) {
      // pisah 2 tahap biar aman di MySQL
      Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'client_id')) {
          $table->string('client_id', 12)->nullable()->index();
        }
      });

      Schema::table('users', function (Blueprint $table) {
        // FK ke kolom non-PK diperbolehkan asalkan indexed/unique (clients.client_id sudah unique)
        $table->foreign('client_id')
          ->references('client_id')
          ->on('clients')
          ->onDelete('set null');
      });
    }
  }

  public function down()
  {
    Schema::table('users', function (Blueprint $table) {
      if (Schema::hasColumn('users', 'client_id')) {
        // drop FK kalau ada
        try { $table->dropForeign(['client_id']); } catch (\Throwable $e) {}
        // index boleh dibiarkan; kalau mau bersih:
        // try { $table->dropIndex(['client_id']); } catch (\Throwable $e) {}
        $table->dropColumn('client_id');
      }
    });

    Schema::table('users', function (Blueprint $table) {
      if (Schema::hasColumn('users', 'role')) {
        // try { $table->dropIndex(['role']); } catch (\Throwable $e) {}
        $table->dropColumn('role');
      }
    });
  }
}
