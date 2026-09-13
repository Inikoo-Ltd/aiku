<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('qa_status')->nullable()->index()->after('is_confidential');
            $table->timestampTz('qa_requested_at')->nullable();
            $table->timestampTz('qa_checked_at')->nullable();
            $table->unsignedInteger('qa_user_id')->nullable();
            $table->foreign('qa_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['qa_user_id']);
            $table->dropColumn(['qa_status', 'qa_requested_at', 'qa_checked_at', 'qa_user_id']);
        });
    }
};
