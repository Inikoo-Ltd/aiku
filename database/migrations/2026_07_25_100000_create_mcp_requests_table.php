<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mcp_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('tool')->index();
            $table->jsonb('arguments');
            $table->boolean('is_error')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_requests');
    }
};
