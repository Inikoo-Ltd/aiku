<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->timestampTz('flagged_wrong_at')->nullable()->index();
            $table->unsignedSmallInteger('flagged_by_user_id')->nullable();
            $table->foreign('flagged_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropForeign(['flagged_by_user_id']);
            $table->dropColumn(['flagged_wrong_at', 'flagged_by_user_id']);
        });
    }
};
