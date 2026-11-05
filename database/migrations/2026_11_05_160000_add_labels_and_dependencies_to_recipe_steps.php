<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->string('label')->nullable()->comment('tells apart the same task used more than once in a recipe, e.g. Red in Mixing – Red');
            $table->jsonb('waits_for')->nullable()->comment('ids of the earlier recipe steps this one waits for; null: the earlier steps that hold up later ones; []: none');
        });

        Schema::table('job_order_item_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('artefact_manufacture_task_id')->nullable()->index()->comment('the recipe step; null once the step is taken off the recipe');
            $table->foreign('artefact_manufacture_task_id')->references('id')->on('artefacts_manufacture_tasks')->nullOnDelete();
            $table->string('label')->nullable()->comment('the recipe step label it was made under, kept once the step is taken off the recipe');
        });

        Schema::table('artefact_step_targets', function (Blueprint $table) {
            $table->string('label')->nullable()->comment('the step label: the same task used more than once in a recipe has a target per label');
        });

        DB::statement('
            UPDATE job_order_item_tasks t
            SET artefact_manufacture_task_id = s.id
            FROM job_order_items i, artefacts_manufacture_tasks s
            WHERE i.id = t.job_order_item_id
              AND s.artefact_id = i.artefact_id
              AND s.manufacture_task_id = t.manufacture_task_id
        ');

        Schema::table('job_order_item_tasks', function (Blueprint $table) {
            $table->dropUnique('job_order_item_tasks_job_order_item_id_manufacture_task_id_uniq');
            $table->unique(['job_order_item_id', 'artefact_manufacture_task_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_order_item_tasks', function (Blueprint $table) {
            $table->dropUnique(['job_order_item_id', 'artefact_manufacture_task_id']);
            $table->unique(['job_order_item_id', 'manufacture_task_id'], 'job_order_item_tasks_job_order_item_id_manufacture_task_id_uniq');
            $table->dropForeign(['artefact_manufacture_task_id']);
            $table->dropColumn(['artefact_manufacture_task_id', 'label']);
        });

        Schema::table('artefact_step_targets', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn(['label', 'waits_for']);
        });
    }
};
