<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\StockDelivery;

use App\Actions\Procurement\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Procurement\StockDelivery;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStateToCreatingStockDelivery
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
            'state' => StockDeliveryStateEnum::IN_PROCESS,
        ];

        if ($stockDelivery->state == StockDeliveryStateEnum::DISPATCHED) {
            $data[$stockDelivery->state->value . '_at']    = null;
            $data['creating_at']                           = now();

            $stockDelivery = $this->update($stockDelivery, $data);

            $this->runStockDeliveryHydrators($stockDelivery);

            return $stockDelivery;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to creating']);
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
