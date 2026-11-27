<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->boolean('is_listed')->default(false)->comment('A private channel whose members chose to show its name to everyone, so others can ask to join');
        });

        Schema::create('slack_channel_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_channel_id')->constrained('slack_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTimeTz('requested_at');
            $table->unique(['slack_channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channel_join_requests');
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->dropColumn('is_listed');
        });
    }
};
