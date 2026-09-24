<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfPurchaseOrder extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder): string
    {
        $purchaseOrder->loadMissing(['organisation.address', 'currency', 'parent', 'purchaseOrderTransactions.supplierProduct.currency']);

        $counterparty = match (true) {
            $purchaseOrder->parent instanceof OrgSupplier => $purchaseOrder->parent->supplier,
            $purchaseOrder->parent instanceof OrgAgent    => $purchaseOrder->parent->agent,
            $purchaseOrder->parent instanceof OrgPartner  => $purchaseOrder->parent->partner,
            default                                       => null,
        };

        $lines = $purchaseOrder->purchaseOrderTransactions
            ->reject(fn ($transaction) => (float)$transaction->quantity_ordered <= 0)
            ->sortBy(fn ($transaction) => $transaction->supplierProduct?->code)
            ->values();

        return PDF::loadView('procurement.templates.pdf.purchase-order', [
            'purchaseOrder'   => $purchaseOrder,
            'organisation'    => $purchaseOrder->organisation,
            'counterparty'    => $counterparty,
            'deliveryAddress' => ResolvePurchaseOrderDeliveryAddress::run($purchaseOrder->organisation, Arr::get($purchaseOrder->data, 'delivery_address')),
            'lines'           => $lines,
            'totals'          => $lines->groupBy(fn ($transaction) => $transaction->supplierProduct?->currency?->code ?? $purchaseOrder->currency->code)
                ->map(fn ($transactions) => $transactions->sum(fn ($transaction) => (float)$transaction->net_amount)),
        ])->output();
    }

    public function filename(PurchaseOrder $purchaseOrder): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $purchaseOrder->reference).'.pdf';
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        abort_unless($purchaseOrder->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        return response($this->handle($purchaseOrder), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"');
    }
}
