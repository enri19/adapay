<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropClientIdFromUsers extends Migration
{
  public function up()
  {
    Schema::table('users', function (Blueprint $table) {
      // Drop foreign key dulu kalau ada
      $table->dropForeign(['client_id']);

      // Baru drop kolom
      $table->dropColumn('client_id');
    });
  }

  public function down()
  {
    Schema::table('users', function (Blueprint $table) {
      $table->string('client_id', 12)->nullable()->after('role');

      // Optional: kembalikan foreign key
      $table->foreign('client_id')
        ->references('client_id')->on('clients')
        ->onDelete('cascade');
    });
  }
}
