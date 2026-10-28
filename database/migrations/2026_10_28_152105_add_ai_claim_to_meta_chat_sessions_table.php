<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The AI customer service run working a WhatsApp chat, so a run that starts while the previous
     * one is still going leaves that chat alone instead of drafting it a second time.
     */
    public function up(): void
    {
        Schema::table('meta_chat_sessions', function (Blueprint $table) {
            $table->unsignedInteger('ai_claim_run_id')->nullable()->index();
            $table->timestampTz('ai_claimed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meta_chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['ai_claim_run_id', 'ai_claimed_at']);
        });
    }
};
