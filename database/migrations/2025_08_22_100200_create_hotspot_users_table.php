<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('hotspot_users', function (Blueprint $table) {
      $table->id();
      $table->string('order_id')->unique();
      $table->foreignId('hotspot_voucher_id')->constrained('hotspot_vouchers');
      $table->string('username');
      $table->string('password');
      $table->string('profile')->nullable();
      $table->unsignedInteger('duration_minutes');
      $table->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('hotspot_users'); }
};
