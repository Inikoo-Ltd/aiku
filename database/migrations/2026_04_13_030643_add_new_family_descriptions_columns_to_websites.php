<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_family_description_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_family_description_snapshot_id')->nullable()->index();
            $table->string('published_family_description_checksum')->nullable()->index();
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
            $table->dropColumn([
                'unpublished_family_description_snapshot_id',
                'live_family_description_snapshot_id',
                'published_family_description_checksum'
            ]);
        });
    }
};
