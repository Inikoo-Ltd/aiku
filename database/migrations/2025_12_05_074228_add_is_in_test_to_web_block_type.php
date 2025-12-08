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
        Schema::table('web_block_types', function (Blueprint $table) {
            //
            $table->boolean('is_in_test')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            //
            $table->dropColumn(['is_in_test']);
        });
    }
};
