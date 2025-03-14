<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('name'); // Nama metode pembayaran
            $table->enum("payment_type", ["virtual_account", "e_wallet", "over_counter"]);
            $table->enum('fee_type', ['fixed', 'percentage']); // Tipe biaya
            $table->decimal('fee_value', 10, 2); // Nilai biaya (fixed price atau percentage)
            $table->timestamps(); // Kolom created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
};
