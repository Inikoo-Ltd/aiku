<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStateToDispatchStockDelivery
{
    use WithActionUpdate;
    use AsAction;
    use HasStockDeliveryHydrators;

    /**
     * @throws ValidationException
     */
    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $data = [
            'state' => StockDeliveryStateEnum::DISPATCHED,
        ];

        if (in_array($stockDelivery->state, [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::RECEIVED])) {
            if ($stockDelivery->state !== StockDeliveryStateEnum::IN_PROCESS) {
                $data[$stockDelivery->state->value . '_at'] = null;
            }
            $data['dispatched_at'] = now();

            $stockDelivery = $this->update($stockDelivery, $data);

            $this->runStockDeliveryHydrators($stockDelivery);

            return $stockDelivery;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to dispatched']);
    }

    /**
     * @throws ValidationException
     */
    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }

    /**
     * @throws ValidationException
     */
    public function asController(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }
}
