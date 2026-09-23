<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mcp_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('type')->index();
            $table->string('tool');
            $table->string('label');
            $table->text('request_text')->nullable();
            $table->jsonb('before');
            $table->jsonb('after');
            $table->jsonb('data')->default('{}');
            $table->timestampTz('reverted_at')->nullable()->index();
            $table->unsignedInteger('reverted_by_id')->nullable();
            $table->foreign('reverted_by_id')->references('id')->on('users');
            $table->timestampsTz();
            $table->index(['group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_changes');
    }
};
