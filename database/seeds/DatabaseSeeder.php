<?php

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
        $this->call(PolyMasterSeeder::class);
          dev_fano

        $this->call(IconPolyMasterSeeder::class);
        $this->call(HealthAgencySeeder::class);
          master
        $this->call(UserSeeder::class);
        $this->call(HealthAgencySeeder::class);
    }
}
