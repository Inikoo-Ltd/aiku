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
            $table->unsignedInteger('unpublished_collection_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_collection_snapshot_id')->nullable()->index();
            $table->string('published_collection_checksum')->nullable()->index();

            $table->foreign('unpublished_collection_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_collection_snapshot_id')->references('id')->on('snapshots');
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
            $table->dropForeign(['unpublished_collection_snapshot_id']);
            $table->dropForeign(['live_collection_snapshot_id']);

            $table->dropColumn('unpublished_collection_snapshot_id');
            $table->dropColumn('live_collection_snapshot_id');
            $table->dropColumn('published_collection_checksum');
        });
    }
};
