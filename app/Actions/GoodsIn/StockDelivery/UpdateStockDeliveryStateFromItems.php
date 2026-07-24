<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStockDeliveryStateFromItems
{
    use AsAction;
    use HasStockDeliveryHydrators;

    public int $hydratorsDelay = 0;

    private const MANUAL_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        if (!in_array($stockDelivery->state, self::MANUAL_STATES, true)) {
            return $stockDelivery;
        }

        $counts = $stockDelivery->items()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $inProcess   = (int) $counts->get(StockDeliveryItemStateEnum::IN_PROCESS->value, 0);
        $confirmed   = (int) $counts->get(StockDeliveryItemStateEnum::CONFIRMED->value, 0);
        $readyToShip = (int) $counts->get(StockDeliveryItemStateEnum::READY_TO_SHIP->value, 0);
        $cancelled   = (int) $counts->get(StockDeliveryItemStateEnum::CANCELLED->value, 0);
        $active      = (int) $counts->sum() - $cancelled;

        $newState = match (true) {
            $active > 0 && $readyToShip === $active           => StockDeliveryStateEnum::READY_TO_SHIP,
            $active > 0 && $inProcess === 0 && $confirmed > 0 => StockDeliveryStateEnum::CONFIRMED,
            default                                           => null,
        };

        if ($newState === null || $newState === $stockDelivery->state) {
            return $stockDelivery;
        }

        $stockDelivery->update(['state' => $newState]);

        $timestampKey = $newState->value . '_at';
        if (!Arr::get($stockDelivery->data, $timestampKey)) {
            $stockDelivery->update(['data->' . $timestampKey => now()->toIso8601String()]);
        }

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }
}
