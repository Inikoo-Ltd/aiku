<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\GoodsIn\StockDeliveryDepositApplication;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteStockDeliveryDepositApplication extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(StockDeliveryDepositApplication $stockDeliveryDepositApplication): StockDeliveryDepositApplication
    {
        $stockDelivery = $stockDeliveryDepositApplication->stockDelivery;

        $stockDeliveryDepositApplication->update(['deleted_by' => $this->asAction ? null : request()->user()?->id]);
        $stockDeliveryDepositApplication->delete();

        EvaluateStockDeliveryCosting::run($stockDelivery);

        return $stockDeliveryDepositApplication;
    }

    public function asController(StockDeliveryDepositApplication $stockDeliveryDepositApplication, ActionRequest $request): StockDeliveryDepositApplication
    {
        $this->initialisation($stockDeliveryDepositApplication->organisation, $request);

        return $this->handle($stockDeliveryDepositApplication);
    }

    public function action(StockDeliveryDepositApplication $stockDeliveryDepositApplication): StockDeliveryDepositApplication
    {
        $this->asAction = true;
        $this->initialisation($stockDeliveryDepositApplication->organisation, []);

        return $this->handle($stockDeliveryDepositApplication);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }

}
