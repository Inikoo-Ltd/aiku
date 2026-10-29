<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->timestampTz('copied_at')->nullable()->comment('First time staff copied the draft text instead of pressing Use (INI-157)');
            $table->jsonb('claim_check')->nullable()->comment('Damaged, missing or wrong item drafts (INI-157): order, what the draft proposed, what staff did on the order within 7 days, the label, the value band and Jev on the reply. Written by chat:measure-claims');
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropColumn(['copied_at', 'claim_check']);
        });
    }
};
