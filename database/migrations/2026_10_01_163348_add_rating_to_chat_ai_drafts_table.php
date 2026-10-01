<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->unsignedSmallInteger('rating')->nullable();
            $table->string('rating_reason')->nullable();
            $table->unsignedSmallInteger('rated_by_user_id')->nullable();
            $table->timestampTz('rated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_reason', 'rated_by_user_id', 'rated_at']);
        });
    }
};
