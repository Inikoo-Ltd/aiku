<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 22:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->timestampTz('development_started_at')->nullable();
            $table->timestampTz('recipe_changed_at')->nullable();
        });

        DB::statement("
            update artefacts set development_started_at = coalesce(
                (select max(audits.created_at) from audits
                 where audits.auditable_type = 'Artefact' and audits.auditable_id = artefacts.id
                   and audits.new_values::jsonb ->> 'is_in_development' in ('1', 'true')),
                artefacts.created_at)
            where artefacts.is_in_development
        ");

        DB::statement('
            update artefacts set recipe_changed_at = (
                select max(artefacts_manufacture_tasks.created_at) from artefacts_manufacture_tasks
                where artefacts_manufacture_tasks.artefact_id = artefacts.id)
        ');
    }

    public function down(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropColumn(['development_started_at', 'recipe_changed_at']);
        });
    }
};
