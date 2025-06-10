<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 19:53:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_collection_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_collection_snapshot_id')->nullable()->index();
            $table->string('published_collection_checksum')->nullable()->index();

            $table->foreign('unpublished_collection_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_collection_snapshot_id')->references('id')->on('snapshots');
        });
    }


    public function down(): void
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
