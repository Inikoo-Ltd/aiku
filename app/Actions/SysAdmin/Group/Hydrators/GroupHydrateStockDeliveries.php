<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateStockDeliveries
{
    use AsAction;
    use WithEnumStats;

    private Group $group;

    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }

    public function handle(Group $group): void
    {
        $queryBase = DB::table('stock_deliveries')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at');

        $stats = [
            'number_stock_deliveries' => $queryBase->clone()->count(),
            'number_current_stock_deliveries' => $queryBase->clone()->whereNotIn('state', [
                StockDeliveryStateEnum::CANCELLED->value,
                StockDeliveryStateEnum::NOT_RECEIVED->value
            ])->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'stock_deliveries',
                field: 'state',
                enum: StockDeliveryStateEnum::class,
                models: StockDelivery::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );


        $group->procurementStats->update($stats);
    }

    public string $commandSignature = 'hydrate:group_stock_deliveries';

    public function asCommand($command): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $this->handle($group);
        }
    }

}
