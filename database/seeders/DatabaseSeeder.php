<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::create([
        //     'username' => 'Admin123',
        //     'email' => 'admin@admin.com',
        //     "phone" => "+62812345678",
        //     "authority" => "admin",
        //     "balance" => 1000000, 
        //     "password" => "admin123"
        // ]);
        $this->call([
            PaymentMethodsSeeder::class,
        ]);
    }
}
