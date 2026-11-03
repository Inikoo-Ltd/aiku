<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('artefact_step_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('artefact_id');
            $table->foreign('artefact_id')->references('id')->on('artefacts')->cascadeOnDelete();
            $table->unsignedSmallInteger('manufacture_task_id');
            $table->foreign('manufacture_task_id')->references('id')->on('manufacture_tasks')->cascadeOnDelete();
            $table->decimal('standard_rate', 10, 4)->nullable();
            $table->timestampTz('effective_from')->nullable()->comment('null: since before targets had dates');
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->jsonb('data')->default('{}');
            $table->timestampsTz();
            $table->index(['artefact_id', 'manufacture_task_id', 'effective_from']);
        });

        DB::statement("
            INSERT INTO artefact_step_targets (artefact_id, manufacture_task_id, standard_rate, effective_from, data, created_at, updated_at)
            SELECT artefact_id, manufacture_task_id, standard_rate, NULL, '{}', COALESCE(updated_at, NOW()), NOW()
            FROM artefacts_manufacture_tasks
            WHERE standard_rate IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('artefact_step_targets');
    }
};
