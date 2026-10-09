<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Paid or not changes no cost, so it stays open on costed deliveries.
 */
class MarkStockDeliveryServiceInvoicePaid extends OrgAction
{
    use WithStockDeliveryServiceInvoiceAllocations;
    use WithActionUpdate;

    public function handle(StockDeliveryServiceInvoice $serviceInvoice, array $modelData): StockDeliveryServiceInvoice
    {
        return $this->update($serviceInvoice, ['paid_at' => $modelData['paid_at'] ?? null]);
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['present', 'nullable', 'date'],
        ];
    }

    public function asController(StockDeliveryServiceInvoice $serviceInvoice, ActionRequest $request): StockDeliveryServiceInvoice
    {
        $this->initialisation($serviceInvoice->organisation, $request);

        return $this->handle($serviceInvoice, $this->validatedData);
    }

    public function action(StockDeliveryServiceInvoice $serviceInvoice, array $modelData): StockDeliveryServiceInvoice
    {
        $this->asAction = true;
        $this->initialisation($serviceInvoice->organisation, $modelData);

        return $this->handle($serviceInvoice, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
