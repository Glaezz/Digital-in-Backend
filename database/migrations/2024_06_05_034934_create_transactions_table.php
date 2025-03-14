<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('transaction_id')->primary();
            $table->string('product_code');
            $table->unsignedBigInteger('user_id');
            $table->string('ok_id')->nullable();
            $table->string('destination', 256);
            $table->string('sn_id')->nullable();
            $table->integer('price');
            $table->integer('supply_price');
            $table->enum("status", ["process", "success", "refund"])->default("process");
            $table->string("time");
            $table->timestamps();

            $table->foreign('product_code')->references('product_code')->on('products');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
