<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
  public function run(): void
  {
    User::updateOrCreate(
      ['email' => 'admin@example.com'],
      ['name' => 'Admin', 'password' => bcrypt('admin123'), 'is_admin' => true]
    );
  }
}
