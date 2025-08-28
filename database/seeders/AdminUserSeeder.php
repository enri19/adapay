<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
  public function run()
  {
    // Admin
    User::firstOrCreate(
      ['email' => 'admin@example.com'],
      [
        'name' => 'Super Admin',
        'password' => Hash::make('admin123'),
        'role' => 'admin',
        'client_id' => null,
      ]
    );

    // User biasa (opsional set client_id kalau ada)
    User::firstOrCreate(
      ['email' => 'user@example.com'],
      [
        'name' => 'Regular User',
        'password' => Hash::make('user12345'),
        'role' => 'user',
        'client_id' => 'DEFAULT', // set ke ID client kalau perlu
      ]
    );
  }
}
