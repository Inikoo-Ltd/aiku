<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Task\TaskStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('sub_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedBigInteger('task_id')->index();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();

            $table->string('type')->index();
            $table->string('status')->default(TaskStatusEnum::PENDING->value)->index();

            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('model_type')->nullable();
            $table->index(['model_type', 'model_id']);

            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('data')->nullable();

            $table->dateTimeTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("
            CREATE UNIQUE INDEX sub_tasks_open_model_unique
            ON sub_tasks (task_id, model_type, model_id)
            WHERE status = '".TaskStatusEnum::PENDING->value."' AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sub_tasks_open_model_unique');
        Schema::dropIfExists('sub_tasks');
    }
};
