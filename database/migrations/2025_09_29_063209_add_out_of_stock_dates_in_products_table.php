<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dateTimeTz('out_of_stock_since')->nullable();
            $table->dateTimeTz('back_in_stock_since')->nullable();
            $table->dateTimeTz('estimated_back_in_stock_at')->nullable();
            $table->unsignedInteger('estimated_to_be_delivered_quantity')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['out_of_stock_since', 'back_in_stock_since', 'estimated_back_in_stock_at', 'estimated_to_be_delivered_quantity']);
        });
    }
};
