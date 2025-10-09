<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 15:23:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_sidebar_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_sidebar_snapshot_id')->nullable()->index();
            $table->string('published_sidebar_checksum')->nullable()->index();

            $table->foreign('unpublished_sidebar_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_sidebar_snapshot_id')->references('id')->on('snapshots');
        });
    }


    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['unpublished_sidebar_snapshot_id']);
            $table->dropForeign(['live_sidebar_snapshot_id']);

            $table->dropColumn('unpublished_sidebar_snapshot_id');
            $table->dropColumn('live_sidebar_snapshot_id');
            $table->dropColumn('published_sidebar_checksum');
        });
    }
};
