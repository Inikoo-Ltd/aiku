<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->string('intent')->nullable();
            $table->string('risk')->nullable();
            $table->boolean('answers_all')->nullable();
            $table->boolean('would_send')->nullable();
            $table->string('change_class')->nullable();
            $table->decimal('words_kept', 4, 3)->nullable();
            $table->unsignedInteger('open_to_reply_seconds')->nullable();
            $table->unsignedInteger('lag_seconds')->nullable();
            $table->string('customer_reaction')->nullable();
            $table->jsonb('jev_check')->nullable();
            $table->index(['shop_id', 'intent', 'risk']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'intent', 'risk']);
            $table->dropColumn(['intent', 'risk', 'answers_all', 'would_send', 'change_class', 'words_kept', 'open_to_reply_seconds', 'lag_seconds', 'customer_reaction', 'jev_check']);
        });
    }
};
