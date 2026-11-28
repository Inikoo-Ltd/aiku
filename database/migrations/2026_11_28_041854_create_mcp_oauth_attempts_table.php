<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:18:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mcp_oauth_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->nullable()->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('client_id')->nullable()->index();
            $table->string('client_name')->nullable();
            $table->string('outcome')->index();
            $table->unsignedSmallInteger('status_code');
            $table->text('error')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_attempts');
    }
};
