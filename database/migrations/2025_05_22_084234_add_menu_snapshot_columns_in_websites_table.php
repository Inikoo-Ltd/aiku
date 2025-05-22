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
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_menu_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_menu_snapshot_id')->nullable()->index();
            $table->string('published_menu_checksum')->nullable()->index();

            $table->foreign('unpublished_menu_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_menu_snapshot_id')->references('id')->on('snapshots');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['unpublished_menu_snapshot_id']);
            $table->dropForeign(['live_menu_snapshot_id']);

            $table->dropColumn('unpublished_menu_snapshot_id');
            $table->dropColumn('live_menu_snapshot_id');
            $table->dropColumn('published_menu_checksum');
        });
    }
};
