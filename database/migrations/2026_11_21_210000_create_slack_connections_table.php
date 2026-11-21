<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('slack_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('slack_user_id')->index();
            $table->string('team_id');
            $table->text('token')->nullable()->comment('The person own Slack user token, encrypted; null once disconnected');
            $table->text('scopes')->nullable()->comment('Comma list of the user scopes Slack granted');
            $table->dateTimeTz('connected_at')->nullable();
            $table->dateTimeTz('revoked_at')->nullable();
            $table->boolean('needs_reconnect')->default(false)->comment('Slack refused the token; the person must connect again');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_connections');
    }
};
