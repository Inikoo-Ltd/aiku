<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStateToSettledStockDelivery
{
    use WithActionUpdate;
    use AsAction;
    use HasStockDeliveryHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $data = [
            'state' => StockDeliveryStateEnum::PLACED,
        ];

        if ($stockDelivery->state === StockDeliveryStateEnum::CHECKED) {
            $data[$stockDelivery->state->value.'_at'] = null;
            $data['placed_at']                        = now();

            $stockDelivery = $this->update($stockDelivery, $data);

            $this->runStockDeliveryHydrators($stockDelivery);

            return $stockDelivery;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to settled']);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }
}
