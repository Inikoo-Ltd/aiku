<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('manufacture_step_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('artefact_id')->nullable();
            $table->foreign('artefact_id')->references('id')->on('artefacts')->cascadeOnDelete();
            $table->unsignedInteger('artefact_family_id')->nullable();
            $table->foreign('artefact_family_id')->references('id')->on('artefact_families')->cascadeOnDelete();
            $table->unsignedSmallInteger('manufacture_task_id');
            $table->foreign('manufacture_task_id')->references('id')->on('manufacture_tasks')->cascadeOnDelete();
            $table->jsonb('thresholds')->nullable()->comment('units per hour by band code; null: back to calculated');
            $table->jsonb('previous_thresholds')->nullable();
            $table->timestampTz('effective_from')->comment('start of the day it was saved, in the organisation time zone');
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['artefact_id', 'manufacture_task_id', 'effective_from']);
            $table->index(['artefact_family_id', 'manufacture_task_id', 'effective_from']);
        });

        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->jsonb('tier_thresholds')->nullable()->comment('edited band thresholds the session was paid on: {source, thresholds}');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropColumn('tier_thresholds');
        });

        Schema::dropIfExists('manufacture_step_tiers');
    }
};
