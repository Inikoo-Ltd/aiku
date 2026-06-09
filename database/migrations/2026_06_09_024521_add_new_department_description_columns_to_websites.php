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
            $table->unsignedInteger('unpublished_department_description_snapshot_id')->nullable()->index();
            $table->foreign('unpublished_department_description_snapshot_id')->references('id')->on('snapshots');
            $table->unsignedInteger('live_department_description_snapshot_id')->nullable()->index();
            $table->foreign('live_department_description_snapshot_id')->references('id')->on('snapshots');
            $table->string('published_department_description_checksum')->nullable()->index();
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
            $table->dropForeign('unpublished_department_description_snapshot_id');
            $table->dropForeign('live_department_description_snapshot_id');
            $table->dropColumn([
                'unpublished_department_description_snapshot_id',
                'live_department_description_snapshot_id',
                'published_department_description_checksum'
            ]);
        });
    }
};
