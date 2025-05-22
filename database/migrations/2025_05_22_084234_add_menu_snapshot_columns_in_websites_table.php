<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 22 May 2025 16:48:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->unsignedInteger('unpublished_menu_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_menu_snapshot_id')->nullable()->index();
            $table->string('published_menu_checksum')->nullable()->index();

            $table->foreign('unpublished_menu_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_menu_snapshot_id')->references('id')->on('snapshots');
        });
    }


    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['unpublished_menu_snapshot_id']);
            $table->dropForeign(['live_menu_snapshot_id']);

            $table->dropColumn('unpublished_menu_snapshot_id');
            $table->dropColumn('live_menu_snapshot_id');
            $table->dropColumn('published_menu_checksum');
        });
    }
};
