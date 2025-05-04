<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Apr 2025 14:29:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\CustomerClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHasPlatformsHydrateCustomerClients implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(CustomerHasPlatform $customerHasPlatform): string
    {
        return $customerHasPlatform->id;
    }

    public function handle(CustomerHasPlatform $customerHasPlatform): void
    {
        $stats = [];

        if ($customerHasPlatform->customer_id && $customerHasPlatform->platform_id) {
            $stats['number_customer_clients'] = CustomerClient::where('customer_id', $customerHasPlatform->customer_id)
                ->where('platform_id', $customerHasPlatform->platform_id)
                ->count();
        }

        $customerHasPlatform->update($stats);
    }

}
