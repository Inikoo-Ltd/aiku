<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->string('presence_status')->default(ChatAgentPresenceStatusEnum::OFFLINE->value)->index();
            $table->timestampTz('last_heartbeat_at')->nullable()->index();
            $table->timestampTz('last_activity_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->dropColumn(['presence_status', 'last_heartbeat_at', 'last_activity_at']);
        });
    }
};
