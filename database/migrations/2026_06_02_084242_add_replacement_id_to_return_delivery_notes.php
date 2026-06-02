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
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_id')->nullable();
            $table->foreign('replacement_id')->references('id')->on('delivery_notes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropForeign('replacement_id');
            $table->dropColumn('replacement_id');
        });
    }
};
