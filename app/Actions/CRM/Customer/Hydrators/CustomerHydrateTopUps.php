<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:58:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\TopUp;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateTopUps implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {
        $stats = [
            'number_top_ups' => $customer->topUps()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'top_ups',
                field: 'status',
                enum: TopUpStatusEnum::class,
                models: TopUp::class,
                where: function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                }
            )
        );

        $customer->stats()->update($stats);
    }

}
