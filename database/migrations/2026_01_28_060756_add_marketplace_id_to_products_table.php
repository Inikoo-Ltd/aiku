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
            $table->string("marketplace_id")->index()->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string("marketplace_id")->index()->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string("marketplace_id")->index()->nullable();
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
            $table->dropColumn("marketplace_id");
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("marketplace_id");
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn("marketplace_id");
        });
    }
};
