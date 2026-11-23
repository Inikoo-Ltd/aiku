<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->string('mood')->nullable()->comment('how the customer sounds in the message the draft answers (ChatAiMoodEnum), set by the routine in save_draft');
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropColumn('mood');
        });
    }
};
