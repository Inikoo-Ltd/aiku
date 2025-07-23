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
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->decimal('quantity_picked', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_packed', 16, 3)->default(0)->nullable();
        });

        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->decimal('quantity_picked', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_packed', 16, 3)->default(0)->nullable();
            $table->decimal('picking_percentage', 5, 2)->default(0);
            $table->decimal('packing_percentage', 5, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('quantity_picked');
            $table->dropColumn('quantity_packed');
        });

        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->dropColumn('quantity_picked');
            $table->dropColumn('quantity_packed');
        });
    }
};
