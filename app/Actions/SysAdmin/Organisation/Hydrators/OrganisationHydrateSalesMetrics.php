<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 14:50:52 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\OrganisationSalesMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:organisation-sales-metrics {organisation}';

    public function getJobUniqueId(Organisation $organisation, Carbon $date): string
    {
        return $organisation->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        if (!$organisation) {
            return;
        }

        $today = Carbon::today();

        $this->handle($organisation, $today);
    }

    public function handle(Organisation $organisation, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => ['organisation_id' => $organisation->id],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'refunds',
                'orders',
                'registrations',
                'baskets_created_grp_currency',
                'baskets_created_org_currency',
                'sales_grp_currency',
                'sales_org_currency',
                'revenue_grp_currency',
                'revenue_org_currency',
                'lost_revenue_grp_currency',
                'lost_revenue_org_currency'
            ]
        ]);

        dump($metrics);

//        OrganisationSalesMetrics::updateOrCreate(
//            [
//                'group_id'        => $organisation->group_id,
//                'organization_id' => $organisation->id,
//                'date'            => $dayStart
//            ],
//            $metrics
//        );
    }
}
