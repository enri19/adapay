<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('payments', function (Blueprint $table) {
      if (!Schema::hasColumn('payments', 'actions')) {
        $table->json('actions')->nullable()->after('raw');
      }
    });
  }

  public function down(): void
  {
    Schema::table('payments', function (Blueprint $table) {
      if (Schema::hasColumn('payments', 'actions')) {
        $table->dropColumn('actions');
      }
    });
  }
};
