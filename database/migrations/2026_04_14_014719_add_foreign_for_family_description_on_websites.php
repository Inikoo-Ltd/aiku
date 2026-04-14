<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->foreign('unpublished_family_description_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_family_description_snapshot_id')->references('id')->on('snapshots');
        });
    }


    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['unpublished_family_description_snapshot_id', 'live_family_description_snapshot_id']);
        });
    }
};
