<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        \App\Models\HotspotVoucher::firstOrCreate(
            ['code' => 'HV-1'],
            ['name' => 'Hotspot 1 Jam','duration_minutes' => 60,'price' => 3000,'profile' => '1h']
        );
        
        \App\Models\HotspotVoucher::firstOrCreate(
            ['code' => 'HV-3'],
            ['name' => 'Hotspot 3 Jam','duration_minutes' => 180,'price' => 7000,'profile' => '3h']
        );

    }
}
