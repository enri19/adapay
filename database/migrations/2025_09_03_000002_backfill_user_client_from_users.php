<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillUserClientFromUsers extends Migration
{
  public function up()
  {
    // Salin semua users.client_id yang tidak null ke pivot user_client
    // Hindari duplikat dengan INSERT IGNORE (MySQL) via DB::statement atau cek manual.
    // Cara portable: cek dulu apakah pasangan sudah ada.
    $users = DB::table('users')
      ->select('id', 'client_id')
      ->whereNotNull('client_id')
      ->where('client_id', '!=', '')
      ->get();

    foreach ($users as $u) {
      $exists = DB::table('user_client')
        ->where('user_id', $u->id)
        ->where('client_id', $u->client_id)
        ->exists();

      if (!$exists) {
        DB::table('user_client')->insert([
          'user_id'   => $u->id,
          'client_id' => strtoupper($u->client_id), // konsistensi uppercase
        ]);
      }
    }
  }

  public function down()
  {
    // Rollback: hapus baris hasil backfill (hanya yang berasal dari users.client_id saat itu)
    // Tidak bisa 100% akurat jika sejak itu ada penambahan manual.
    // Minimal: hapus pasangan yang cocok dengan nilai users.client_id saat ini.
    $users = DB::table('users')
      ->select('id', 'client_id')
      ->whereNotNull('client_id')
      ->where('client_id', '!=', '')
      ->get();

    foreach ($users as $u) {
      DB::table('user_client')
        ->where('user_id', $u->id)
        ->where('client_id', $u->client_id)
        ->delete();
    }
  }
}
