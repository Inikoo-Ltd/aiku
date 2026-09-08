<?php

namespace App\Actions\Procurement\OrgAgent\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgAgentHydrateStockDeliveries implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OrgAgent $orgAgent): string
    {
        return $orgAgent->id;
    }

    public function handle(OrgAgent $orgAgent): void
    {
        $stats = [
            'number_stock_deliveries'         => $orgAgent->stockDeliveries()->count(),
            'number_current_stock_deliveries' => $orgAgent->stockDeliveries()
                ->whereNotIn('state', [
                    StockDeliveryStateEnum::CANCELLED->value,
                    StockDeliveryStateEnum::NOT_RECEIVED->value,
                ])->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'stock_deliveries',
            field: 'state',
            enum: StockDeliveryStateEnum::class,
            models: StockDelivery::class,
            where: function ($q) use ($orgAgent) {
                $q->where('parent_id', $orgAgent->id)->where('parent_type', 'OrgAgent');
            }
        ));

        $orgAgent->stats()->update($stats);
    }
}
