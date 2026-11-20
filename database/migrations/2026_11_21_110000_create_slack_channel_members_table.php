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
        Schema::create('slack_channel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_channel_id')->constrained('slack_channels')->cascadeOnDelete();
            $table->string('slack_user_id')->index();
            $table->timestampTz('joined_at')->nullable()->comment('null when it came from the member list, which gives no time');
            $table->timestampsTz();
            $table->unique(['slack_channel_id', 'slack_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channel_members');
    }
};
