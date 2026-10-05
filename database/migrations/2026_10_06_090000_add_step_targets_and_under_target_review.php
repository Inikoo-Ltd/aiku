<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_tasks', function (Blueprint $table) {
            $table->text('description')->nullable();
        });

        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->decimal('standard_rate', 10, 4)->nullable();
        });

        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->decimal('standard_rate', 10, 4)->nullable();
            $table->boolean('is_under_target')->default(false)->index();
            $table->string('under_target_reason')->nullable();
            $table->text('under_target_note')->nullable();
            $table->unsignedInteger('under_target_reviewed_by')->nullable();
            $table->timestampTz('under_target_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropColumn(['standard_rate', 'is_under_target', 'under_target_reason', 'under_target_note', 'under_target_reviewed_by', 'under_target_reviewed_at']);
        });

        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn('standard_rate');
        });

        Schema::table('manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
