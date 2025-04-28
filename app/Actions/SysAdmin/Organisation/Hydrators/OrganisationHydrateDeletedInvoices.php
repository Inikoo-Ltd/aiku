<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Apr 2025 22:25:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateDeletedInvoices implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {


        $organisation->orderingStats()->update(
            [
                'number_deleted_invoices' => Invoice::onlyTrashed()
                    ->where('organisation_id', $organisation->id)
                    ->count(),
            ]
        );
    }


}
