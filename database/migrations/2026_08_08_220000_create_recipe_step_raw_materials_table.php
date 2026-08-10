<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recipe_step_raw_materials', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedBigInteger('artefact_manufacture_task_id')->index();
            $table->foreign('artefact_manufacture_task_id', 'rec_step_raw_mat_amt_id_fk')->references('id')->on('artefacts_manufacture_tasks')->cascadeOnDelete();
            $table->unsignedInteger('raw_material_id')->index();
            $table->foreign('raw_material_id', 'rec_step_raw_mat_raw_mat_id_fk')->references('id')->on('raw_materials')->cascadeOnDelete();
            $table->decimal('quantity_per_unit', 16, 4);
            $table->timestampsTz();
            $table->unique(['artefact_manufacture_task_id', 'raw_material_id'], 'rec_step_raw_mat_amt_raw_mat_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_step_raw_materials');
    }
};
