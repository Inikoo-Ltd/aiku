<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jun 2023 03:30:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('clockings', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('workplace_id')->nullable()->index();
            $table->foreign('workplace_id')->references('id')->on('workplaces');
            $table->unsignedInteger('timesheet_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedInteger('subject_id')->nullable();
            $table->unsignedInteger('time_tracker_id')->index()->nullable();
            $table->unsignedInteger('clocking_machine_id')->nullable()->index();
            $table->foreign('clocking_machine_id')->references('id')->on('clocking_machines');
            $table->dateTimeTz('clocked_at');
            $table->string('generator_type')->nullable();
            $table->unsignedInteger('generator_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->onDelete('cascade');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletes();
            $table->nullableMorphs('deleted_by');
            $table->string('source_id')->nullable()->unique();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['generator_type', 'generator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clockings');
    }
};
