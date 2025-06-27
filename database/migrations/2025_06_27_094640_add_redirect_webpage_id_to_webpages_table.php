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
        Schema::table('webpages', function (Blueprint $table) {
            $table->unsignedInteger('redirect_webpage_id')->index()->nullable();
            $table->foreign('redirect_webpage_id')->references('id')->on('webpages');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['redirect_webpage_id']);
            $table->dropIndex(['redirect_webpage_id']);
            $table->dropColumn('redirect_webpage_id');
        });
    }
};
