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
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->unsignedInteger('picking_session_id')->nullable()->index();
            $table->foreign('picking_session_id')->references('id')->on('picking_sessions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropForeign(['picking_session_id']);
            $table->dropColumn('picking_session_id');
        });
    }
};
