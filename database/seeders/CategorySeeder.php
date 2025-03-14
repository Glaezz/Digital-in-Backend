<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->insert([
            [
                'name' => 'credit',
                'profit_type' => 'percentage',
                'profit_value' => 1.8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'e wallet',
                'profit_type' => 'fixed',
                'profit_value' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}
