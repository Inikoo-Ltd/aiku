<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 15:19:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\Hydrators;

use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomersHydrateStatus
{
    use AsAction;


    public function handle(): void
    {
        foreach (
            FulfilmentCustomer::where('status', '!=', FulfilmentCustomerStatusEnum::LOST)
                ->get() as $fulfilmentCustomer
        ) {
            FulfilmentCustomerHydrateStatus::run($fulfilmentCustomer);
        }
    }


    public string $commandSignature = 'hydrate:fulfilment_customers_status';

    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }

}
