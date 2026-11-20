<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->timestampTz('pinned_at')->nullable()->index();
            $table->string('pinned_by_slack_user_id')->nullable();
        });

        Schema::create('slack_channel_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_channel_id')->index()->constrained('slack_channels')->cascadeOnDelete();
            $table->string('slack_id')->unique();
            $table->string('title')->nullable();
            $table->text('link')->nullable();
            $table->string('emoji')->nullable();
            $table->string('position')->nullable()->comment('Slack rank, sorts as text');
            $table->jsonb('data')->nullable();
            $table->timestampTz('removed_in_slack_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channel_bookmarks');
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropColumn(['pinned_at', 'pinned_by_slack_user_id']);
        });
    }
};
