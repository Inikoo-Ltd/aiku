<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('slack_channel_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('slack_channel_id')->constrained('slack_channels')->cascadeOnDelete();
            $table->string('last_read_ts')->comment('Slack ts of the newest message this person has read in aiku; independent of Slack');
            $table->timestampsTz();
            $table->unique(['user_id', 'slack_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channel_reads');
    }
};
