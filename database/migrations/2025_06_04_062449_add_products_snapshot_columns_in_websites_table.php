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
            $table->unsignedInteger('unpublished_products_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_products_snapshot_id')->nullable()->index();
            $table->string('published_products_checksum')->nullable()->index();

            $table->foreign('unpublished_products_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_products_snapshot_id')->references('id')->on('snapshots');
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
            $table->dropForeign(['unpublished_products_snapshot_id']);
            $table->dropForeign(['live_products_snapshot_id']);

            $table->dropColumn('unpublished_products_snapshot_id');
            $table->dropColumn('live_products_snapshot_id');
            $table->dropColumn('published_products_checksum');
        });
    }
};
