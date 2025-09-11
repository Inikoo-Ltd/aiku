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
            $table->boolean('has_extra_packing')->nullable();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('has_extra_packing')->nullable();
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
            $table->dropColumn('has_extra_packing');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('has_extra_packing');
        });
    }
};
