<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('payments', function (Blueprint $table) {
      $table->id();
      $table->string('order_id')->unique();
      $table->string('provider')->default('midtrans');
      $table->string('provider_ref')->nullable();
      $table->unsignedBigInteger('amount')->nullable();
      $table->string('currency', 8)->default('IDR');
      $table->string('status', 20)->default('PENDING');
      $table->text('qr_string')->nullable();
      $table->json('raw')->nullable();
      $table->timestamp('paid_at')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('payments');
  }
};
