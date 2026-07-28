<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StartStockDeliveryCosting extends OrgAction
{
    use WithActionUpdate;

    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::BOOKED_IN) {
            $validator->errors()->add('state', __('You can only start the costing of a booked in stock delivery'));
        }

        if ($this->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('The costing of this stock delivery has already started'));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery->items()
            ->whereNull('cost_items')
            ->update([
                'cost_items' => DB::raw('net_amount'),
                'cost_total' => DB::raw('net_amount + coalesce(cost_extra, 0) + coalesce(cost_shipping, 0) + coalesce(cost_duties, 0) + coalesce(cost_tax, 0)'),
            ]);

        $stockDelivery = $this->update($stockDelivery, ['is_costed' => true]);

        StockDeliveriesHydrateCosts::run($stockDelivery);

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery);
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
