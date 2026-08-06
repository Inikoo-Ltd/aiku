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
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_golden_product')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_golden_product')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_golden_product')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_golden_product')->default(false);
        });
    }
};
