<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $awa = "select id from productions where slug = 'awa'";

        DB::update(
            "update artefact_families set data = jsonb_set(coalesce(data, '{}'::jsonb), '{needs_machine_maintenance}', 'true'::jsonb)
             where production_id in ($awa) and deleted_at is null
             and upper(code) in ('ACSR', 'ACSS', 'GMSOAP', 'GMSOAPUL', 'AWGSOAP', 'KSOAP', 'KSOAPUL', 'WHSS', 'UWHSS')"
        );

        DB::update(
            "update artefacts set settings = jsonb_set(coalesce(settings, '{}'::jsonb), '{needs_machine_maintenance}', 'true'::jsonb)
             where production_id in ($awa) and deleted_at is null
             and upper(code) in ('AVENA-01', 'VITC-05')"
        );
    }

    public function down(): void
    {
        DB::update("update artefact_families set data = data - 'needs_machine_maintenance' where jsonb_exists(data, 'needs_machine_maintenance')");
        DB::update("update artefacts set settings = settings - 'needs_machine_maintenance' where jsonb_exists(settings, 'needs_machine_maintenance')");
    }
};
