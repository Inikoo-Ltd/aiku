<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Reviewed: Thu, 06 Jun 2024 14:36:49 Central European Summer Time, Plane Malaga - Abu Dhabi
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Task\TaskStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('task_type_id')->index()->nullable();
            $table->unsignedSmallInteger('production_id')->index()->nullable();
            $table->boolean('is_manufacturing')->default(false);
            $table->unsignedBigInteger('assigner_id')->nullable();
            $table->string('assigner_type')->nullable();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code', 64)->index()->collation('und_ns');
            $table->string('name');
            $table->string('status')->default(TaskStatusEnum::PENDING->value);
            $table->text('description')->nullable()->fulltext();
            $table->dateTimeTz('start_date')->nullable();
            $table->dateTimeTz('complete_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
