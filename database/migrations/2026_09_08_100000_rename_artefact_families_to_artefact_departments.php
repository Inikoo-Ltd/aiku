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

        /* Postgres keeps the old index and constraint names after a table rename, and the
           ArtefactFamily that replaces this one needs them back. */
        foreach ($this->renamedIndexes() as $from => $to) {
            DB::statement("ALTER INDEX IF EXISTS {$from} RENAME TO {$to}");
        }
        foreach ($this->renamedConstraints() as $table => $constraints) {
            foreach ($constraints as $from => $to) {
                DB::statement("ALTER TABLE {$table} RENAME CONSTRAINT {$from} TO {$to}");
            }
        }

        DB::table('artisan_assignments')->where('artisanable_type', 'ArtefactFamily')->update(['artisanable_type' => 'ArtefactDepartment']);
        DB::table('audits')->where('auditable_type', 'ArtefactFamily')->update(['auditable_type' => 'ArtefactDepartment']);
    }

    public function down(): void
    {
        foreach ($this->renamedConstraints() as $table => $constraints) {
            foreach ($constraints as $from => $to) {
                DB::statement("ALTER TABLE {$table} RENAME CONSTRAINT {$to} TO {$from}");
            }
        }
        foreach ($this->renamedIndexes() as $from => $to) {
            DB::statement("ALTER INDEX IF EXISTS {$to} RENAME TO {$from}");
        }

        DB::table('artisan_assignments')->where('artisanable_type', 'ArtefactDepartment')->update(['artisanable_type' => 'ArtefactFamily']);
        DB::table('audits')->where('auditable_type', 'ArtefactDepartment')->update(['auditable_type' => 'ArtefactFamily']);

        Schema::table('artefacts', function (Blueprint $table) {
            $table->renameColumn('artefact_department_id', 'artefact_family_id');
        });

        Schema::rename('artefact_departments', 'artefact_families');
    }

    private function renamedIndexes(): array
    {
        return [
            'artefact_families_pkey'                  => 'artefact_departments_pkey',
            'artefact_families_group_id_index'        => 'artefact_departments_group_id_index',
            'artefact_families_organisation_id_index' => 'artefact_departments_organisation_id_index',
            'artefact_families_production_id_index'   => 'artefact_departments_production_id_index',
            'artefact_families_slug_unique'           => 'artefact_departments_slug_unique',
            'artefact_families_code_index'            => 'artefact_departments_code_index',
            'artefacts_artefact_family_id_index'      => 'artefacts_artefact_department_id_index',
        ];
    }

    private function renamedConstraints(): array
    {
        return [
            'artefact_departments' => [
                'artefact_families_group_id_foreign'        => 'artefact_departments_group_id_foreign',
                'artefact_families_organisation_id_foreign' => 'artefact_departments_organisation_id_foreign',
                'artefact_families_production_id_foreign'   => 'artefact_departments_production_id_foreign',
            ],
            'artefacts'            => [
                'artefacts_artefact_family_id_foreign' => 'artefacts_artefact_department_id_foreign',
            ],
        ];
    }
};
