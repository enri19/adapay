<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('clients', function (Blueprint $t) {
      if (!Schema::hasColumn('clients','auth_mode')) {
        $t->string('auth_mode', 16)->default('userpass')->after('default_profile'); // 'userpass' | 'code'
      }
    });
  }
  public function down(): void {
    Schema::table('clients', function (Blueprint $t) {
      if (Schema::hasColumn('clients','auth_mode')) $t->dropColumn('auth_mode');
    });
  }
};
