<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Validation\Validator;

class UpdateStockDeliveryStateToReceived extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use HasStockDeliveryHydrators;


    private StockDelivery $stockDelivery;

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $data = [
            'state' => StockDeliveryStateEnum::RECEIVED,
        ];

        $data[$stockDelivery->state->value.'_at']    = null;
        $data['received_at']                         = now();

        $stockDelivery = $this->update($stockDelivery, $data);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDelivery->state, [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::DISPATCHED, StockDeliveryStateEnum::CHECKED])) {
            $validator->errors()->add('state', __('You can not change the status to received if state is'.' '.$this->stockDelivery->state->value));
        }
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        $this->asAction         = true;
        $this->stockDelivery    = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }


    public function asController(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }
}
