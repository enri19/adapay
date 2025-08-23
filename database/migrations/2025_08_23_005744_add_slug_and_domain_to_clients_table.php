<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('clients', function (Blueprint $t) {
      if (!Schema::hasColumn('clients','slug'))          $t->string('slug', 32)->unique()->after('client_id');
      if (!Schema::hasColumn('clients','portal_domain')) $t->string('portal_domain')->nullable()->after('slug');
    });
  }
  public function down(): void {
    Schema::table('clients', function (Blueprint $t) {
      if (Schema::hasColumn('clients','portal_domain')) $t->dropColumn('portal_domain');
      if (Schema::hasColumn('clients','slug'))          $t->dropColumn('slug');
    });
  }
};
