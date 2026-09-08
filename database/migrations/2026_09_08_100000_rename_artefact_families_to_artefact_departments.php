<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::rename('artefact_families', 'artefact_departments');

        Schema::table('artefacts', function (Blueprint $table) {
            $table->renameColumn('artefact_family_id', 'artefact_department_id');
        });

        DB::table('artisan_assignments')->where('artisanable_type', 'ArtefactFamily')->update(['artisanable_type' => 'ArtefactDepartment']);
        DB::table('audits')->where('auditable_type', 'ArtefactFamily')->update(['auditable_type' => 'ArtefactDepartment']);
    }

    public function down(): void
    {
        DB::table('artisan_assignments')->where('artisanable_type', 'ArtefactDepartment')->update(['artisanable_type' => 'ArtefactFamily']);
        DB::table('audits')->where('auditable_type', 'ArtefactDepartment')->update(['auditable_type' => 'ArtefactFamily']);

        Schema::table('artefacts', function (Blueprint $table) {
            $table->renameColumn('artefact_department_id', 'artefact_family_id');
        });

        Schema::rename('artefact_departments', 'artefact_families');
    }
};
