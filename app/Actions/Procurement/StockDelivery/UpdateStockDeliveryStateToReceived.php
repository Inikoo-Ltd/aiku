<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Procurement\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Procurement\StockDelivery;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryStateToReceived extends OrgAction
{
    use HasStockDeliveryHydrators;
    use WithActionUpdate;

    private StockDelivery $stockDelivery;

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $data = [
            'state' => StockDeliveryStateEnum::RECEIVED,
        ];

        $data[$stockDelivery->state->value.'_at'] = null;
        $data['received_at'] = now();

        $stockDelivery = $this->update($stockDelivery, $data);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if (! in_array($this->stockDelivery->state, [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::DISPATCHED, StockDeliveryStateEnum::CHECKED])) {
            $validator->errors()->add('state', __('You can not change the status to received if state is'.' '.$this->stockDelivery->state->value));
        }
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        $this->asAction = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }

    public function asController(StockDelivery $stockDelivery): StockDelivery
    {
        return $this->handle($stockDelivery);
    }
}
