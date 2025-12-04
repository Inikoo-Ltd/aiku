<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 19:14:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateExclusiveProducts implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $customerId): string
    {
        return (string) $customerId;
    }

    public function handle(int $customerId): void
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $stats = [
            'number_exclusive_products'         => $customer->exclusiveProducts()->count(),
        ];
        $customer->update($stats);
    }


}
