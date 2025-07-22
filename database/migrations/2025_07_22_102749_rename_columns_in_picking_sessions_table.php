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
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->renameColumn('number_picking_session_items', 'number_items');
            $table->renameColumn('number_picking_session_items_picked', 'number_items_picked');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->renameColumn('number_items', 'number_picking_session_items');
            $table->renameColumn('number_items_picked', 'number_picking_session_items_picked');
        });
    }
};
