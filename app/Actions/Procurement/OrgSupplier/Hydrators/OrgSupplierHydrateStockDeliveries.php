<?php

namespace App\Actions\Procurement\OrgSupplier\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgSupplierHydrateStockDeliveries implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OrgSupplier $orgSupplier): string
    {
        return $orgSupplier->id;
    }

    public function handle(OrgSupplier $orgSupplier): void
    {
        $stats = [
            'number_stock_deliveries'         => $orgSupplier->stockDeliveries()->count(),
            'number_current_stock_deliveries' => $orgSupplier->stockDeliveries()
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
            where: function ($q) use ($orgSupplier) {
                $q->where('parent_id', $orgSupplier->id)->where('parent_type', 'OrgSupplier');
            }
        ));

        $orgSupplier->stats()->update($stats);
    }
}
