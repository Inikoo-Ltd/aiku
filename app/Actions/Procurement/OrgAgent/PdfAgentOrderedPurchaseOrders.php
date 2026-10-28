<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\ResolvePurchaseOrderDeliveryAddress;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every purchase order on the agent's Ordered stage as one purchase order, the lines of each supplier under its
 * own heading, as Aurora printed an agent's purchase order.
 */
class PdfAgentOrderedPurchaseOrders extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * @return Collection<int, array{purchaseOrder: PurchaseOrder, supplier: mixed, lines: Collection<int, PurchaseOrderTransaction>}>
     */
    public function sections(OrgAgent $orgAgent): Collection
    {
        $pdfPurchaseOrder = PdfPurchaseOrder::make();

        return ShowAgentOrderStage::documents($orgAgent, ShowAgentOrderStage::ORDERED)
            ->join('suppliers as sup', 'sup.id', 'po.supplier_id')
            ->orderByRaw('sup.code collate "C"')
            ->orderBy('po.id')
            ->select('po.*')
            ->with(['currency', 'parent.supplier', 'purchaseOrderTransactions.supplierProduct.currency', 'purchaseOrderTransactions.orgStock'])
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'purchaseOrder' => $purchaseOrder,
                'supplier'      => $purchaseOrder->parent?->supplier,
                'lines'         => $pdfPurchaseOrder->lines($purchaseOrder),
            ])
            ->filter(fn (array $section) => $section['lines']->isNotEmpty())
            ->values();
    }

    public function handle(OrgAgent $orgAgent): string
    {
        $sections = $this->sections($orgAgent);

        $totals = $sections
            ->flatMap(fn (array $section) => $section['lines']->map(fn (PurchaseOrderTransaction $line) => [
                'currency' => $line->supplierProduct?->currency?->code ?? $section['purchaseOrder']->currency->code,
                'amount'   => (float) $line->net_amount,
            ]))
            ->groupBy('currency')
            ->map(fn (Collection $amounts) => $amounts->sum('amount'));

        return PDF::loadView('procurement.templates.pdf.agent-ordered-purchase-orders', [
            'organisation'    => $orgAgent->organisation,
            'agent'           => $orgAgent->agent,
            'deliveryAddress' => ResolvePurchaseOrderDeliveryAddress::run($orgAgent->organisation),
            'date'            => now(),
            'sections'        => $sections,
            'totals'          => $totals,
        ])->output();
    }

    public function filename(OrgAgent $orgAgent): string
    {
        return PdfPurchaseOrder::make()->filenameFor($orgAgent->agent->code.'-ordered-'.now()->toDateString());
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return response($this->handle($orgAgent), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($orgAgent).'"');
    }
}
