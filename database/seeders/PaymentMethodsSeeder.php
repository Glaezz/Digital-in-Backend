<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('payment_methods')->insert([
            // E-Wallet
            [
                'name' => 'QRIS',
                'payment_type' => 'e_wallet',
                'fee_type' => 'percentage',
                'fee_value' => 0.7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // [
            //     'name' => 'Dana',
            //     'payment_type' => 'e_wallet',
            //     'fee_type' => 'percentage',
            //     'fee_value' => 1.67,
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ],
            // Virtual Account
            [
                'name' => 'BRI',
                'payment_type' => 'virtual_account',
                'fee_type' => 'fixed',
                'fee_value' => 4000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'BNI',
                'payment_type' => 'virtual_account',
                'fee_type' => 'fixed',
                'fee_value' => 4000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'BCA',
                'payment_type' => 'virtual_account',
                'fee_type' => 'fixed',
                'fee_value' => 4000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Permata',
                'payment_type' => 'virtual_account',
                'fee_type' => 'fixed',
                'fee_value' => 4000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Over-Counter
            [
                'name' => 'Alfamart',
                'payment_type' => 'over_counter',
                'fee_type' => 'fixed',
                'fee_value' => 5000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
