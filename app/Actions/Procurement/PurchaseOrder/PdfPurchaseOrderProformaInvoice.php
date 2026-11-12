<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-12 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\SupplierAgreedTerm\GetPurchaseOrderAgreedTerms;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfPurchaseOrderProformaInvoice extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentOrganisation;

    private const array PDF_RELATIONS = ['organisation.address', 'currency', 'parent', 'agent', 'purchaseOrderTransactions.supplierProduct.currency', 'purchaseOrderTransactions.orgStock'];

    public static function isAvailable(PurchaseOrder $purchaseOrder): bool
    {
        return in_array($purchaseOrder->parent_type, ['OrgAgent', 'OrgSupplier'])
            && !in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED]);
    }

    public function handle(PurchaseOrder $purchaseOrder): string
    {
        return PDF::loadView('procurement.templates.pdf.proforma-invoice', $this->viewData($purchaseOrder), [], ['autoScriptToLang' => true, 'autoLangToFont' => true])->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(self::PDF_RELATIONS);

        $seller = match (true) {
            $purchaseOrder->isAgentOrder()                => $purchaseOrder->agent,
            $purchaseOrder->parent instanceof OrgSupplier => $purchaseOrder->parent->supplier,
            $purchaseOrder->parent instanceof OrgAgent    => $purchaseOrder->parent->agent,
            $purchaseOrder->parent instanceof OrgPartner  => $purchaseOrder->parent->partner,
            default                                       => null,
        };

        $lines = PdfPurchaseOrder::make()->lines($purchaseOrder);

        return [
            'purchaseOrder'   => $purchaseOrder,
            'seller'          => $seller,
            'buyer'           => $purchaseOrder->organisation,
            'deliveryAddress' => ResolvePurchaseOrderDeliveryAddress::run($purchaseOrder->organisation, Arr::get($purchaseOrder->data, 'delivery_address')),
            'lines'           => $lines,
            'images'          => null,
            'agreedTerms'     => GetPurchaseOrderAgreedTerms::make()->forMany(collect([$purchaseOrder]), forDocument: true)[$purchaseOrder->id] ?? collect(),
            'depositAmount'   => (float) $purchaseOrder->deposit_amount,
            'totals'          => $lines->groupBy(fn ($transaction) => $transaction->currencyCodeIn($purchaseOrder))
                ->map(fn ($transactions) => $transactions->sum(fn ($transaction) => (float) $transaction->net_amount)),
        ];
    }

    public function filename(PurchaseOrder $purchaseOrder): string
    {
        return PdfPurchaseOrder::make()->filenameFor('Proforma-'.$purchaseOrder->reference);
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($purchaseOrder);

        return response($this->handle($purchaseOrder), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"');
    }
}
