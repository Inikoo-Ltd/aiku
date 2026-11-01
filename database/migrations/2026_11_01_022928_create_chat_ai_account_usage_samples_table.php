<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Readings of each claude.ai account's weekly usage limit, sent by Raul's local pace mod, kept
     * only when the percentage or the reset time changes.
     */
    public function up(): void
    {
        Schema::create('chat_ai_account_usage_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->string('account', 64);
            $table->decimal('percent_used', 5, 2);
            $table->timestampTz('resets_at');
            $table->timestampTz('sampled_at');
            $table->timestampsTz();
            $table->index(['group_id', 'account', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ai_account_usage_samples');
    }
};
