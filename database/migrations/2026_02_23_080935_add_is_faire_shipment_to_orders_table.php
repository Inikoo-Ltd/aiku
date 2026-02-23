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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_shipping_by_external')->default(false);
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('is_shipping_by_external')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_shipping_by_external');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('is_shipping_by_external');
        });
    }
};
