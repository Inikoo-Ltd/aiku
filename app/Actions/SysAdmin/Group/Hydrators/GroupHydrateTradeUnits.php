<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jan 2024 10:20:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $commandSignature = 'hydrate:group-trade-units {group : Group slug}';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function asCommand(Command $command): void
    {
        $group = Group::where('slug', $command->argument('group'))->first();

        if (!$group) {
            $command->error('Group not found');
            return;
        }

        $this->handle($group);

        $command->info('Group trade units hydrated successfully');
    }

    public function handle(Group $group): void
    {
        $tradeUnits = $group->tradeUnits();

        $stats = [
            'number_trade_units'                              => $tradeUnits->count(),
            'number_orphan_trade_units'                       => $group->tradeUnits()->whereNull('trade_unit_family_id')->count(),
            'number_trade_units_without_marketing_weight'     => $group->tradeUnits()->whereNull('marketing_weight')->where('status', '!=', \App\Enums\Goods\TradeUnit\TradeUnitStatusEnum::DISCONTINUED)->count(),
            'number_trade_units_without_marketing_dimensions' => $group->tradeUnits()->where(function ($q) {
                $q->whereNull('marketing_dimensions')->orWhere('marketing_dimensions', '{}');
            })->where('status', '!=', \App\Enums\Goods\TradeUnit\TradeUnitStatusEnum::DISCONTINUED)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'trade_units',
                field: 'status',
                enum: TradeUnitStatusEnum::class,
                models: TradeUnit::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->goodsStats()->update(
            $stats
        );
    }
}
