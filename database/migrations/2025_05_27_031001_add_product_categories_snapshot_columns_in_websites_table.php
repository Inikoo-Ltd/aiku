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
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_department_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_department_snapshot_id')->nullable()->index();
            $table->string('published_department_checksum')->nullable()->index();

            $table->foreign('unpublished_department_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_department_snapshot_id')->references('id')->on('snapshots');


            $table->unsignedInteger('unpublished_sub_department_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_sub_department_snapshot_id')->nullable()->index();
            $table->string('published_sub_department_checksum')->nullable()->index();

            $table->foreign('unpublished_sub_department_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_sub_department_snapshot_id')->references('id')->on('snapshots');


            $table->unsignedInteger('unpublished_family_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_family_snapshot_id')->nullable()->index();
            $table->string('published_family_checksum')->nullable()->index();

            $table->foreign('unpublished_family_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_family_snapshot_id')->references('id')->on('snapshots');


            $table->unsignedInteger('unpublished_product_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_product_snapshot_id')->nullable()->index();
            $table->string('published_product_checksum')->nullable()->index();

            $table->foreign('unpublished_product_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_product_snapshot_id')->references('id')->on('snapshots');
        });
    }


    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['unpublished_department_snapshot_id']);
            $table->dropForeign(['live_department_snapshot_id']);

            $table->dropForeign(['unpublished_sub_department_snapshot_id']);
            $table->dropForeign(['live_sub_department_snapshot_id']);

            $table->dropForeign(['unpublished_family_snapshot_id']);
            $table->dropForeign(['live_family_snapshot_id']);

            $table->dropForeign(['unpublished_product_snapshot_id']);
            $table->dropForeign(['live_product_snapshot_id']);


            $table->dropColumn('unpublished_department_snapshot_id');
            $table->dropColumn('live_department_snapshot_id');
            $table->dropColumn('published_department_checksum');

            $table->dropColumn('unpublished_sub_department_snapshot_id');
            $table->dropColumn('live_sub_department_snapshot_id');
            $table->dropColumn('published_sub_department_checksum');

            $table->dropColumn('unpublished_family_snapshot_id');
            $table->dropColumn('live_family_snapshot_id');
            $table->dropColumn('published_family_checksum');

            $table->dropColumn('unpublished_product_snapshot_id');
            $table->dropColumn('live_product_snapshot_id');
            $table->dropColumn('published_product_checksum');
        });
    }
};
