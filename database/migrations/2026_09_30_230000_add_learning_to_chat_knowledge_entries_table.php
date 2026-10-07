<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_knowledge_entries', function (Blueprint $table) {
            $table->string('status')->default('active')->index();
            $table->jsonb('evidence')->nullable();
            $table->unsignedInteger('customers_count')->default(0);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->text('conflict')->nullable();
        });

        Schema::table('email_archive_messages', function (Blueprint $table) {
            $table->timestampTz('learned_at')->nullable()->index();
        });

        Schema::table('chat_turn_readings', function (Blueprint $table) {
            $table->timestampTz('learned_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('chat_knowledge_entries', function (Blueprint $table) {
            $table->dropColumn(['status', 'evidence', 'customers_count', 'last_seen_at', 'expires_at', 'conflict']);
        });
        Schema::table('email_archive_messages', fn (Blueprint $table) => $table->dropColumn('learned_at'));
        Schema::table('chat_turn_readings', fn (Blueprint $table) => $table->dropColumn('learned_at'));
    }
};
