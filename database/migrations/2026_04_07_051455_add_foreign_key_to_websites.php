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
            $table->foreign('live_families_overview_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('unpublished_families_overview_snapshot_id')->references('id')->on('snapshots');
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
            $table->dropForeign(['live_families_overview_snapshot_id', 'unpublished_families_overview_snapshot_id']);
        });
    }
};
