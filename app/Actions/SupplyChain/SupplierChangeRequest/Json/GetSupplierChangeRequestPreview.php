<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 11:44
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierChangeRequest\Json;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\DeactivateSupplier;
use App\Actions\SupplyChain\Supplier\MergeSuppliers;
use App\Actions\SupplyChain\SupplierChangeRequest\DecideSupplierChangeRequest;
use App\Actions\SupplyChain\SupplierProduct\MergeSupplierProducts;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierChangeRequest\SupplierChangeRequestTypeEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierChangeRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * What approving a suggestion will do, counted from the live data, so the director sees it before deciding.
 */
class GetSupplierChangeRequestPreview extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return DecideSupplierChangeRequest::canDecide($request->user());
    }

    public function handle(SupplierChangeRequest $supplierChangeRequest): array
    {
        $supplier = $supplierChangeRequest->supplier;

        if ($supplierChangeRequest->type === SupplierChangeRequestTypeEnum::MERGE) {
            return $this->mergePreview($supplier, $supplierChangeRequest->targetSupplier);
        }

        return $this->deactivatePreview($supplier);
    }

    private function mergePreview(Supplier $duplicate, Supplier $kept): array
    {
        $mergeSuppliers  = MergeSuppliers::make();
        $refusedProducts = MergeSupplierProducts::make()->mergeSupplier($duplicate, $kept)['refused'];

        $keptCodes       = $kept->supplierProducts()->pluck('code')->map(fn (string $code) => mb_strtolower($code))->flip();
        $duplicateCodes  = $duplicate->supplierProducts()->orderBy('code')->pluck('code');
        $sharedCodes     = $duplicateCodes->filter(fn (string $code) => $keptCodes->has(mb_strtolower($code)));
        $blockedOrders   = $mergeSuppliers->blockedAgentOrderReferences($duplicate, $kept);

        $keptOrganisationIds = $kept->orgSuppliers()->pluck('organisation_id')->flip();

        return [
            'type'          => SupplierChangeRequestTypeEnum::MERGE->value,
            'supplier'      => $this->supplierSummary($duplicate),
            'target'        => $this->supplierSummary($kept),
            'products'      => [
                'moved'   => $duplicateCodes->count() - $sharedCodes->count(),
                'merged'  => $sharedCodes->reject(fn (string $code) => isset($refusedProducts[$code]))->values(),
                'refused' => collect($refusedProducts)->map(fn (string $reason, int|string $code) => ['code' => (string) $code, 'reason' => $reason])->values(),
            ],
            'agent_orders'  => [
                'folded'  => $mergeSuppliers->sharedAgentOrderReferences($duplicate, $kept)->diff($blockedOrders)->values(),
                'blocked' => $blockedOrders,
            ],
            'organisations' => $this->organisations($duplicate)->map(fn (object $organisation) => [
                'code'   => $organisation->code,
                'name'   => $organisation->name,
                'joins'  => $keptOrganisationIds->has($organisation->id),
                'active' => (bool) $organisation->status,
            ])->values(),
            'blockers'      => $mergeSuppliers->blockers($duplicate, $kept),
        ];
    }

    private function deactivatePreview(Supplier $supplier): array
    {
        $openOrders = DeactivateSupplier::make()->openPurchaseOrderReferences($supplier);

        return [
            'type'          => SupplierChangeRequestTypeEnum::DEACTIVATE->value,
            'supplier'      => $this->supplierSummary($supplier),
            'organisations' => $this->organisations($supplier)->map(fn (object $organisation) => [
                'code'   => $organisation->code,
                'name'   => $organisation->name,
                'active' => (bool) $organisation->status,
            ])->values(),
            'blockers'      => $openOrders->isNotEmpty()
                ? [__('The supplier still has open purchase orders: :references', ['references' => $openOrders->implode(', ')])]
                : [],
        ];
    }

    private function supplierSummary(Supplier $supplier): array
    {
        return [
            'code'                 => $supplier->code,
            'name'                 => $supplier->name,
            'products'             => $supplier->supplierProducts()->count(),
            'active_products'      => $supplier->supplierProducts()->where('state', SupplierProductStateEnum::ACTIVE)->count(),
            'purchase_orders'      => $supplier->purchaseOrders()->count(),
            'open_purchase_orders' => $supplier->purchaseOrders()->whereIn('state', PurchaseOrderStateEnum::live())->count(),
            'stock_deliveries'     => $supplier->stockDeliveries()->count(),
        ];
    }

    private function organisations(Supplier $supplier): Collection
    {
        return DB::table('org_suppliers')
            ->join('organisations', 'organisations.id', '=', 'org_suppliers.organisation_id')
            ->where('org_suppliers.supplier_id', $supplier->id)
            ->orderBy('organisations.id')
            ->get(['organisations.id', 'organisations.code', 'organisations.name', 'org_suppliers.status']);
    }

    public function asController(SupplierChangeRequest $supplierChangeRequest, ActionRequest $request): array
    {
        $this->initialisationFromGroup($supplierChangeRequest->group, $request);

        return $this->handle($supplierChangeRequest);
    }
}
