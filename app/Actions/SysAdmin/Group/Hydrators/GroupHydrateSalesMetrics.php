<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 14:13:17 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\GroupSalesMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:group-sales-metrics {group}';

    public function getJobUniqueId(Group $group, Carbon $date): string
    {
        return $group->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $group = Group::where('slug', $command->argument('group'))->first();

        if (!$group) {
            return;
        }

        $today = Carbon::today();

        $this->handle($group, $today);
    }

    public function handle(Group $group, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => ['group_id' => $group->id],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'refunds',
                'orders',
                'registrations',
                'baskets_created_grp_currency',
                'sales_grp_currency',
                'revenue_grp_currency',
                'lost_revenue_grp_currency'
            ]
        ]);

        GroupSalesMetrics::updateOrCreate(
            [
                'group_id' => $group->id,
                'date'     => $dayStart
            ],
            $metrics
        );
    }
}
