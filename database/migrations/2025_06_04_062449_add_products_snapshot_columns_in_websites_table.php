<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Jun 2025 20:58:12 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_products_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_products_snapshot_id')->nullable()->index();
            $table->string('published_products_checksum')->nullable()->index();

            $table->foreign('unpublished_products_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_products_snapshot_id')->references('id')->on('snapshots');
        });
    }


    public function down(): void
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
