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
        Schema::table('packings', function (Blueprint $table) {
            $table->renameColumn('quantity_packed', 'quantity');

            $table->dropForeign(['picking_id']);

            $table->dropColumn('picking_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('packings', function (Blueprint $table) {
            $table->renameColumn('quantity', 'quantity_packed');
            $table->unsignedBigInteger('picking_id')->nullable();
            $table->foreign('picking_id')->references('id')->on('pickings');
        });
    }
};
