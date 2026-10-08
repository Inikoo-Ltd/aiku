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
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;

class PdfPurchaseOrder extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder): string
    {
        return PDF::loadView('procurement.templates.pdf.purchase-order', $this->viewData($purchaseOrder))->output();
    }

    /**
     * One PDF for the supplier orders of an agent order, a section per supplier order.
     *
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    public function handleMany(Collection $purchaseOrders): string
    {
        return PDF::loadView('procurement.templates.pdf.purchase-orders', [
            'reference'      => $purchaseOrders->first()->agent_order_reference ?? $purchaseOrders->first()->reference,
            'purchaseOrders' => $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $this->viewData($purchaseOrder))->all(),
        ])->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['organisation.address', 'currency', 'parent', 'agent', 'purchaseOrderTransactions.supplierProduct.currency', 'purchaseOrderTransactions.orgStock']);

        $counterparty = match (true) {
            $purchaseOrder->isAgentOrder()                => $purchaseOrder->agent,
            $purchaseOrder->parent instanceof OrgSupplier => $purchaseOrder->parent->supplier,
            $purchaseOrder->parent instanceof OrgAgent    => $purchaseOrder->parent->agent,
            $purchaseOrder->parent instanceof OrgPartner  => $purchaseOrder->parent->partner,
            default                                       => null,
        };

        $lines = $purchaseOrder->purchaseOrderTransactions
            ->reject(fn ($transaction) => (float)$transaction->quantity_ordered <= 0)
            ->sortBy(fn ($transaction) => $transaction->supplierProduct?->code)
            ->values();

        return [
            'purchaseOrder'   => $purchaseOrder,
            'organisation'    => $purchaseOrder->organisation,
            'counterparty'    => $counterparty,
            'deliveryAddress' => ResolvePurchaseOrderDeliveryAddress::run($purchaseOrder->organisation, Arr::get($purchaseOrder->data, 'delivery_address')),
            'lines'           => $lines,
            'totals'          => $lines->groupBy(fn ($transaction) => $transaction->supplierProduct?->currency?->code ?? $purchaseOrder->currency->code)
                ->map(fn ($transactions) => $transactions->sum(fn ($transaction) => (float)$transaction->net_amount)),
        ];
    }

    public function filenameFor(string $reference): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $reference).'.pdf';
    }

    public function filename(PurchaseOrder $purchaseOrder): string
    {
        return $this->filenameFor($purchaseOrder->reference);
    }

    public function inAgentOrder(Organisation $organisation, OrgAgent $orgAgent, string $agentOrderReference, ActionRequest $request): Response
    {
        abort_unless($orgAgent->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        $purchaseOrders = PurchaseOrder::inAgentOrder($orgAgent->organisation_id, $orgAgent->agent_id, $agentOrderReference)
            ->with(['organisation.address', 'currency', 'parent', 'agent', 'purchaseOrderTransactions.supplierProduct.currency', 'purchaseOrderTransactions.orgStock'])
            ->orderBy('parent_code')
            ->get();
        abort_if($purchaseOrders->isEmpty(), 404);

        return response($this->handleMany($purchaseOrders), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filenameFor($agentOrderReference).'"');
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        abort_unless($purchaseOrder->organisation_id === $organisation->id || $organisation->type === OrganisationTypeEnum::AGENT, 404);
        $this->initialisation($organisation, $request);

        return response($this->handle($purchaseOrder), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"');
    }
}
