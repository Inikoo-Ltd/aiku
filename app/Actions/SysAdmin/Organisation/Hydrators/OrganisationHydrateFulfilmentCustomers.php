<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateFulfilmentCustomers implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_customers_interest_pallets_storage' => $organisation->fulfilmentCustomers()->where('pallets_storage', true)->count(),
            'number_customers_interest_items_storage' => $organisation->fulfilmentCustomers()->where('items_storage', true)->count(),
            'number_customers_interest_dropshipping' => $organisation->fulfilmentCustomers()->where('dropshipping', true)->count(),
            'number_customers_with_stored_items' => $organisation->fulfilmentCustomers()->where('number_stored_items', '>', 0)->count(),
            'number_customers_with_pallets' => $organisation->fulfilmentCustomers()->where('number_pallets', '>', 0)->count(),
        ];

        $organisation->fulfilmentStats()->update($stats);
    }
}
