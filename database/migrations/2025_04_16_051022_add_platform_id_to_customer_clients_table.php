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
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->unsignedSmallInteger('platform_id')->index()->nullable();
            $table->foreign('platform_id')->references('id')->on('platforms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropColumn(['platform_id']);
        });
    }
};
