<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeleteStockDeliveryCost extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private StockDeliveryCost $stockDeliveryCost;

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDeliveryCost->type !== StockDeliveryCostTypeEnum::EXTRA) {
            $validator->errors()->add('type', __('Only extra expenses can be deleted'));
        }
    }

    public function handle(StockDeliveryCost $stockDeliveryCost): void
    {
        $stockDelivery = $stockDeliveryCost->stockDelivery;
        $stockDeliveryCost->delete();

        EvaluateStockDeliveryCosting::run($stockDelivery);
    }

    public function asController(StockDeliveryCost $stockDeliveryCost, ActionRequest $request): void
    {
        $this->stockDeliveryCost = $stockDeliveryCost;
        $this->initialisation($stockDeliveryCost->organisation, $request);

        $this->handle($stockDeliveryCost);
    }

    public function action(StockDeliveryCost $stockDeliveryCost): void
    {
        $this->asAction          = true;
        $this->stockDeliveryCost = $stockDeliveryCost;
        $this->initialisation($stockDeliveryCost->organisation, []);

        $this->handle($stockDeliveryCost);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
