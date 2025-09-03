<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserClientTable extends Migration
{
  public function up()
  {
    Schema::create('user_client', function (Blueprint $table) {
      $table->unsignedBigInteger('user_id');
      $table->string('client_id', 12); // match clients.client_id (varchar 12)

      // Composite PK agar tidak ada duplikasi pasangan
      $table->primary(['user_id', 'client_id']);

      // Index untuk performa query
      $table->index('client_id');

      // FK ke users (aman)
      $table->foreign('user_id')
        ->references('id')->on('users')
        ->onDelete('cascade');

      // *Sengaja* tidak pasang FK ke clients.client_id supaya simpel & bebas collation/engine
      // Kalau mau strict, pastikan clients.client_id UNIQUE lalu tambah FK di migrasi terpisah.
    });
  }

  public function down()
  {
    Schema::dropIfExists('user_client');
  }
}
