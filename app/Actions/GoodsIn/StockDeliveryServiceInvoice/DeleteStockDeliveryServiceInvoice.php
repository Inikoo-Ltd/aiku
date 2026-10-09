<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeleteStockDeliveryServiceInvoice extends OrgAction
{
    use WithStockDeliveryServiceInvoiceAllocations;

    private StockDeliveryServiceInvoice $serviceInvoice;

    public function handle(StockDeliveryServiceInvoice $serviceInvoice): void
    {
        DB::transaction(function () use ($serviceInvoice) {
            $stockDeliveryIds = $serviceInvoice->stockDeliveries()->pluck('stock_deliveries.id')->all();
            $serviceInvoice->delete();
            $this->syncStockDeliveries($stockDeliveryIds);
        });
    }

    public function afterValidator(Validator $validator): void
    {
        $this->validateAllocations($validator, $this->serviceInvoice->stockDeliveries()->pluck('stock_deliveries.id')->all());
    }

    public function asController(StockDeliveryServiceInvoice $serviceInvoice, ActionRequest $request): void
    {
        $this->serviceInvoice = $serviceInvoice;
        $this->initialisation($serviceInvoice->organisation, $request);

        $this->handle($serviceInvoice);
    }

    public function action(StockDeliveryServiceInvoice $serviceInvoice): void
    {
        $this->asAction       = true;
        $this->serviceInvoice = $serviceInvoice;
        $this->initialisation($serviceInvoice->organisation, []);

        $this->handle($serviceInvoice);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
