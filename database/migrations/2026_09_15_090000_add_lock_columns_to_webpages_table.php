<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dateTimeTz('locked_at')->nullable()->index();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->foreign('locked_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->jsonb('lock_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['locked_by_user_id']);
            $table->dropColumn(['locked_at', 'locked_by_user_id', 'lock_data']);
        });
    }
};
