<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->jsonb('edit_drafts_in_my_language_for')->nullable();
        });

        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->jsonb('staff_translations')->nullable();
            $table->unsignedInteger('written_in_language_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->dropColumn('edit_drafts_in_my_language_for');
        });

        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropColumn(['staff_translations', 'written_in_language_id']);
        });
    }
};
