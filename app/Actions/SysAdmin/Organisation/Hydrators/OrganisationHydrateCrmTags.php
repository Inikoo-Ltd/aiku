<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 15:02:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateCrmTags
{
    use AsAction;

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_tags' => DB::table('tags')->where('type', 'crm')->count(),
        ];

        $organisation->crmStats()->update($stats);
    }
}
