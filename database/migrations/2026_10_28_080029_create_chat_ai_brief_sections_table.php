<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_ai_brief_sections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('set_by_user_id')->nullable();
            $table->foreign('set_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['group_id', 'key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ai_brief_sections');
    }
};
