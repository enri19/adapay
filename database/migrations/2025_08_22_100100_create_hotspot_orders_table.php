<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('hotspot_orders', function (Blueprint $table) {
      $table->id();
      $table->string('order_id')->unique();
      $table->foreignId('hotspot_voucher_id')->constrained('hotspot_vouchers');
      $table->string('buyer_name')->nullable();
      $table->string('buyer_email')->nullable();
      $table->string('buyer_phone')->nullable();
      $table->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('hotspot_orders'); }
};
