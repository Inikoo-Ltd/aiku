<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('return_delivery_note_items', function (Blueprint $table) {
            $table->boolean('is_handled')->default('false');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('return_delivery_note_items', function (Blueprint $table) {
            $table->dropColumn(['is_handled']);
        });
    }
};
