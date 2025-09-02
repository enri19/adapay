<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contact_messages', function (Blueprint $t) {
      $t->id();
      $t->string('name',100);
      $t->string('email',120);
      $t->string('hp',30)->nullable();
      $t->string('subject',150);
      $t->text('message');
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('contact_messages'); }
};
