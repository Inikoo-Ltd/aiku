<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 31 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Which claude.ai account ran the sweep (both sign in to Aiku as the same user), and when the run
     * last did anything, so the chats of a run that went quiet are free for the other account.
     */
    public function up(): void
    {
        Schema::table('chat_ai_runs', function (Blueprint $table) {
            $table->string('account', 64)->nullable()->index();
            $table->timestampTz('last_activity_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_runs', function (Blueprint $table) {
            $table->dropColumn(['account', 'last_activity_at']);
        });
    }
};
