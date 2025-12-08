<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Mar 2024 21:52:23 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydratePaymentServiceProviders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_payment_service_providers' => $group->paymentServiceProviders()->count(),
            'number_org_payment_service_providers' => $group->orgPaymentServiceProviders()->count(),

        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payment_service_providers',
                field: 'type',
                enum: PaymentServiceProviderTypeEnum::class,
                models: PaymentServiceProvider::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'org_payment_service_providers',
                field: 'type',
                enum: PaymentServiceProviderTypeEnum::class,
                models: OrgPaymentServiceProvider::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->accountingStats()->update($stats);
    }
}
