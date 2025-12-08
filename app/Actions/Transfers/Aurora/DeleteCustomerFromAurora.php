<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 18:25:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\Customer\DeleteCustomer;
use App\Models\CRM\Customer;
use App\Transfers\SourceOrganisationService;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteCustomerFromAurora
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Customer
    {
        if ($customer = Customer::withTrashed()->where('source_id', $organisationSourceId)->first()) {
            if (! $customer->trashed()) {
                DeleteCustomer::run(
                    customer: $customer
                );
            }
        } else {
            return FetchAuroraDeletedCustomers::run($organisationSource, $organisationSourceId);
        }

        return null;
    }
}
