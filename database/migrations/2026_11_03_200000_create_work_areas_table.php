<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('work_areas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions')->cascadeOnDelete();
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::table('artefact_departments', function (Blueprint $table) {
            $table->unsignedInteger('work_area_id')->nullable()->index()->comment('the work area where its products are made');
            $table->foreign('work_area_id')->references('id')->on('work_areas')->nullOnDelete();
        });

        $areaNames = ['bath-bombs' => 'Bath Bombs Area', 'soap' => 'Soaps Area'];
        DB::table('artisan_assignments')
            ->join('artefact_departments', 'artefact_departments.id', 'artisan_assignments.artisanable_id')
            ->where('artisan_assignments.artisanable_type', 'ArtefactDepartment')
            ->get(['artisan_assignments.id', 'artefact_departments.id as department_id', 'artefact_departments.code', 'artefact_departments.name', 'artefact_departments.group_id', 'artefact_departments.organisation_id', 'artefact_departments.production_id'])
            ->groupBy('department_id')
            ->each(function ($assignments) use ($areaNames) {
                $department = $assignments->first();
                $areaId     = DB::table('work_areas')->insertGetId([
                    'group_id'        => $department->group_id,
                    'organisation_id' => $department->organisation_id,
                    'production_id'   => $department->production_id,
                    'name'            => $areaNames[$department->code] ?? $department->name.' Area',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                DB::table('artefact_departments')->where('id', $department->department_id)->update(['work_area_id' => $areaId]);
                DB::table('artisan_assignments')->whereIn('id', $assignments->pluck('id'))->update(['artisanable_type' => 'WorkArea', 'artisanable_id' => $areaId]);
            });
    }

    public function down(): void
    {
        DB::table('artisan_assignments')->where('artisanable_type', 'WorkArea')->delete();
        Schema::table('artefact_departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_area_id');
        });
        Schema::dropIfExists('work_areas');
    }
};
