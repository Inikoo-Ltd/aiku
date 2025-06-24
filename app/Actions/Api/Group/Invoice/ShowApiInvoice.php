<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-13h-42m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Invoice;

use App\Actions\OrgAction;
use App\Http\Resources\Api\InvoiceApiResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class ShowApiInvoice extends OrgAction
{
    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }

    public function jsonResponse(Invoice $invoice): \Illuminate\Http\Resources\Json\JsonResource|InvoiceApiResource
    {
        return InvoiceApiResource::make($invoice);
    }

    public function asController(Shop $shop, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($invoice);
    }
}
