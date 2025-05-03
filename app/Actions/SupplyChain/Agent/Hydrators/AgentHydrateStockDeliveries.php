<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:14:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Procurement\StockDelivery;
use App\Models\SupplyChain\Agent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AgentHydrateStockDeliveries implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Agent $agent): string
    {
        return $agent->id;
    }

    public function handle(Agent $agent): void
    {
        $stats = [
            'number_stock_deliveries' => $agent->stockDeliveries->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'stock_deliveries',
            field: 'state',
            enum: StockDeliveryStateEnum::class,
            models: StockDelivery::class,
            where: function ($q) use ($agent) {
                $q->where('agent_id', $agent->id);
            }
        ));



        $agent->stats()->update($stats);
    }


}
