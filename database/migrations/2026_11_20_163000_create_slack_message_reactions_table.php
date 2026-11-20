<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('slack_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_message_id')->constrained('slack_messages')->cascadeOnDelete();
            $table->string('slack_user_id');
            $table->string('name');
            $table->timestampTz('reacted_at')->nullable()->comment('null when it came from the history, which gives no time');
            $table->timestampsTz();
            $table->unique(['slack_message_id', 'slack_user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_message_reactions');
    }
};
