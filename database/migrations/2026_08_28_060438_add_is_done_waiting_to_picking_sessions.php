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
            $table->boolean('is_done_waiting')->default(false)->index();
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
            $table->dropColumn([
                'is_done_waiting'
            ]);
        });
    }
};
