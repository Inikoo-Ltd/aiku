<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Rubbish is not spam. Marking an email as spam blocks the sender for good, which is the
     * wrong answer for an out of office or a supplier's newsletter arriving from a real
     * customer's address: they have nothing to say today and everything to say next week.
     */
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->boolean('is_rubbish')->default(false)->index();
            $table->timestampTz('rubbish_at')->nullable();
            $table->unsignedInteger('rubbished_by_agent_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_rubbish', 'rubbish_at', 'rubbished_by_agent_id']);
        });
    }
};
