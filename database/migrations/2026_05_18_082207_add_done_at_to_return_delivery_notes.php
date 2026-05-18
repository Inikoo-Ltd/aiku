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
            $table->dateTimeTz('done_at')->nullable();
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
            $table->dropColumn([
                'done_at'
            ]);
        });
    }
};
