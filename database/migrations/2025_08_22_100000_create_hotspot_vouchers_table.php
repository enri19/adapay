<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('hotspot_vouchers', function (Blueprint $table) {
      $table->id();
      $table->string('code')->unique();
      $table->string('name');
      $table->unsignedInteger('duration_minutes');
      $table->unsignedInteger('price');
      $table->string('profile')->nullable();
      $table->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('hotspot_vouchers'); }
};
