<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 23:23:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropColumn('organisation_id');

            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');

            $table->dropForeign(['post_room_id']);
            $table->dropColumn('post_room_id');

            $table->dropForeign(['org_post_room_id']);
            $table->dropColumn('org_post_room_id');
        });
    }


    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();

            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();

            $table->unsignedSmallInteger('post_room_id')->nullable()->index();
            $table->foreign('post_room_id')->references('id')->on('post_rooms')->nullOnDelete();

            $table->unsignedSmallInteger('org_post_room_id')->nullable()->index();
            $table->foreign('org_post_room_id')->references('id')->on('org_post_rooms')->nullOnDelete();
        });
    }
};
