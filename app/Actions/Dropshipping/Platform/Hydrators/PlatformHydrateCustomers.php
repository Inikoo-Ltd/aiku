<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:34:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Platform $platform): string
    {
        return $platform->id;
    }

    public function handle(Platform $platform): void
    {
        $stats = [
            'number_customers' => $platform->customers()->count(),

        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'state',
                enum: CustomerStateEnum::class,
                models: Customer::class
            )
        );


        $platform->stats()->update($stats);
    }
}
