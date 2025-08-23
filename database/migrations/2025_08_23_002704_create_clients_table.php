<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (!Schema::hasTable('clients')) {
      Schema::create('clients', function (Blueprint $table) {
        $table->id();
        $table->string('client_id', 12)->unique();
        $table->string('slug', 32)->nullable()->unique();     // untuk subdomain / query
        $table->string('name');
        $table->string('router_host')->nullable();
        $table->unsignedInteger('router_port')->default(8728);
        $table->string('router_user')->nullable();
        $table->string('router_pass')->nullable();
        $table->string('default_profile')->default('default');
        $table->boolean('enable_push')->default(false);
        $table->boolean('is_active')->default(true);
        $table->string('portal_domain')->nullable();
        $table->timestamps();
      });
      return;
    }

    // --- Patch jika tabel sudah ada ---
    Schema::table('clients', function (Blueprint $table) {
      if (!Schema::hasColumn('clients','slug'))          $table->string('slug', 32)->nullable()->after('client_id');
      if (!Schema::hasColumn('clients','portal_domain')) $table->string('portal_domain')->nullable()->after('slug');
      if (!Schema::hasColumn('clients','router_port'))   $table->unsignedInteger('router_port')->default(8728)->after('router_host');
      if (!Schema::hasColumn('clients','router_user'))   $table->string('router_user')->nullable()->after('router_port');
      if (!Schema::hasColumn('clients','router_pass'))   $table->string('router_pass')->nullable()->after('router_user');
      if (!Schema::hasColumn('clients','default_profile')) $table->string('default_profile')->default('default')->after('router_pass');
      if (!Schema::hasColumn('clients','enable_push'))   $table->boolean('enable_push')->default(false)->after('default_profile');
      if (!Schema::hasColumn('clients','is_active'))     $table->boolean('is_active')->default(true)->after('enable_push');
    });

    // Unique index (abaikan kalau sudah ada)
    try { Schema::table('clients', fn(Blueprint $t) => $t->unique('client_id')); } catch (\Throwable $e) {}
    try { Schema::table('clients', fn(Blueprint $t) => $t->unique('slug')); } catch (\Throwable $e) {}
  }

  public function down(): void
  {
    Schema::dropIfExists('clients');
  }
};
