<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\Procurement\OrgSupplierProduct;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrderTransaction;
use App\Actions\Procurement\OrgAgent\SetAgentDraftLine;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\SetOrgStockPreferredSupplierProduct;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Repairs products filed under the catch-all AWChina records, which hold products of many real suppliers.
 * A product whose code the right supplier does not have yet moves there with its history. When the right
 * supplier already has that code, the duplicate under AWChina is retired: its SKOs are bought from the right
 * supplier's product instead, which takes over as the preferred source where the duplicate was.
 *
 * Temporary: it exists only to empty those two records, and goes once they are empty.
 */
class RepairSupplierProductsSupplier extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public const array REPAIRABLE_SUPPLIER_CODES = ['AWC', 'AWC2'];

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, Collection $supplierProducts): array
    {
        $this->assertCanRepair($supplier, $supplierProducts);

        $sourceSuppliers = Supplier::whereIn('id', $supplierProducts->pluck('supplier_id')->unique())->get();
        $orgSuppliers    = collect();
        $repaired        = ['moved' => 0, 'merged' => 0];

        DB::transaction(function () use ($supplier, $supplierProducts, $orgSuppliers, &$repaired) {
            foreach ($supplierProducts as $supplierProduct) {
                $twin = $supplier->supplierProducts()->whereRaw('lower(code) = ?', [mb_strtolower($supplierProduct->code)])->first();

                if ($twin) {
                    $this->mergeIntoTwin($supplierProduct, $twin);
                    $repaired['merged']++;
                } else {
                    $this->moveToSupplier($supplierProduct, $supplier, $orgSuppliers);
                    $repaired['moved']++;
                }
            }
        });

        foreach ($sourceSuppliers->push($supplier) as $hydratedSupplier) {
            SupplierHydrateSupplierProducts::dispatch($hydratedSupplier);
        }
        foreach ($orgSuppliers->filter()->unique('id') as $orgSupplier) {
            OrgSupplierHydrateOrgSupplierProducts::dispatch($orgSupplier);
        }

        return $repaired;
    }

    /**
     * @param  Collection<int, OrgSupplier>  $orgSuppliers
     */
    private function moveToSupplier(SupplierProduct $supplierProduct, Supplier $supplier, Collection $orgSuppliers): void
    {
        foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
            $targetOrgSupplier = $this->targetOrgSupplier($supplier, $orgSupplierProduct->organisation_id);
            $orgSuppliers->push($orgSupplierProduct->orgSupplier, $targetOrgSupplier);
            $orgSupplierProduct->update(['org_supplier_id' => $targetOrgSupplier->id]);
            $this->moveDraftLines($supplierProduct, $orgSupplierProduct);
        }

        $supplierProduct->update(['supplier_id' => $supplier->id]);
    }

    private function mergeIntoTwin(SupplierProduct $supplierProduct, SupplierProduct $twin): void
    {
        $this->reactivateTwin($supplierProduct, $twin);

        foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
            $twinOrgSupplierProduct = $twin->orgSupplierProducts()->where('organisation_id', $orgSupplierProduct->organisation_id)->first()
                ?? StoreOrgSupplierProduct::make()->action($this->targetOrgSupplier($twin->supplier, $orgSupplierProduct->organisation_id), $twin);

            if ($orgSupplierProduct->state === OrgSupplierProductStateEnum::ACTIVE->value) {
                UpdateOrgSupplierProduct::make()->action($twinOrgSupplierProduct, [
                    'state'        => OrgSupplierProductStateEnum::ACTIVE->value,
                    'is_available' => $orgSupplierProduct->is_available,
                ]);
            }


            $links = OrgStockHasOrgSupplierProduct::with('orgStock')
                ->where('org_supplier_product_id', $orgSupplierProduct->id)
                ->where('status', true)
                ->get();

            foreach ($links as $link) {
                $wasPreferred = $link->local_priority >= OrgStockHasOrgSupplierProduct::where('org_stock_id', $link->org_stock_id)->max('local_priority');

                AttachOrgSupplierProductToOrgStock::make()->action($link->orgStock, $twinOrgSupplierProduct);

                if ($wasPreferred) {
                    SetOrgStockPreferredSupplierProduct::make()->handle($link->orgStock, $twinOrgSupplierProduct);
                }
            }

            $this->moveDraftLines($supplierProduct, $twinOrgSupplierProduct);

            UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, [
                'state'        => OrgSupplierProductStateEnum::DISCONTINUED->value,
                'is_available' => false,
            ]);
        }

        UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::DISCONTINUED->value], strict: false);
    }

    /**
     * The real supplier's product may be discontinued while its AWChina copy is still sold: it comes back as
     * active. Reactivating it would also reactivate it in every organisation, so each organisation keeps its own
     * state, and only those where the AWChina copy was active take it over afterwards.
     */
    private function reactivateTwin(SupplierProduct $supplierProduct, SupplierProduct $twin): void
    {
        if ($supplierProduct->state !== SupplierProductStateEnum::ACTIVE || $twin->state === SupplierProductStateEnum::ACTIVE) {
            return;
        }

        $twinOrgSupplierProducts = $twin->orgSupplierProducts()->get(['id', 'state', 'is_available']);

        UpdateSupplierProduct::make()->action($twin, ['state' => SupplierProductStateEnum::ACTIVE->value], strict: false);

        foreach ($twinOrgSupplierProducts as $twinOrgSupplierProduct) {
            UpdateOrgSupplierProduct::make()->action(OrgSupplierProduct::find($twinOrgSupplierProduct->id), [
                'state'        => $twinOrgSupplierProduct->state,
                'is_available' => $twinOrgSupplierProduct->is_available,
            ]);
        }
    }

    /**
     * Purchase orders with the AWChina record that the supplier has not confirmed yet, drafts or submitted,
     * give their line of this product to the real supplier's draft, opened if it has none, so nothing is
     * ordered from AWChina for a product it no longer holds.
     */
    private function moveDraftLines(SupplierProduct $supplierProduct, OrgSupplierProduct $targetOrgSupplierProduct): void
    {
        $draftLines = PurchaseOrderTransaction::with('purchaseOrder')
            ->where('supplier_product_id', $supplierProduct->id)
            ->where('organisation_id', $targetOrgSupplierProduct->organisation_id)
            ->whereNull('deleted_at')
            ->whereHas('purchaseOrder', fn ($query) => $query
                ->whereIn('state', [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED])
                ->where(fn ($query) => $query->where('parent_type', '!=', 'OrgSupplier')->orWhere('parent_id', '!=', $targetOrgSupplierProduct->org_supplier_id)))
            ->get();

        if ($draftLines->isEmpty()) {
            return;
        }

        $quantityOnTargetDrafts = PurchaseOrderTransaction::where('org_supplier_product_id', $targetOrgSupplierProduct->id)
            ->whereNull('deleted_at')
            ->whereHas('purchaseOrder', fn ($query) => $query
                ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
                ->where('parent_type', 'OrgSupplier')
                ->where('parent_id', $targetOrgSupplierProduct->org_supplier_id))
            ->sum('quantity_ordered');

        $quantity = (float) $quantityOnTargetDrafts + (float) $draftLines->sum('quantity_ordered');

        $orgAgent = OrgAgent::where('organisation_id', $targetOrgSupplierProduct->organisation_id)->where('agent_id', $supplierProduct->agent_id)->firstOrFail();
        if ($targetOrgSupplierProduct->org_agent_id !== $orgAgent->id) {
            $targetOrgSupplierProduct->update(['org_agent_id' => $orgAgent->id]);
        }

        $draftLines->each(fn (PurchaseOrderTransaction $line) => DeletePurchaseOrderTransaction::make()->action($line));

        SetAgentDraftLine::make()->handle($orgAgent, $targetOrgSupplierProduct->refresh(), $quantity);
    }

    private function targetOrgSupplier(Supplier $supplier, int $organisationId): OrgSupplier
    {
        $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $organisationId)->first();
        if ($orgSupplier) {
            return $orgSupplier;
        }

        $orgAgent = OrgAgent::where('agent_id', $supplier->agent_id)->where('organisation_id', $organisationId)->firstOrFail();

        return StoreOrgSupplier::make()->action($orgAgent, $supplier);
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    private function assertCanRepair(Supplier $supplier, Collection $supplierProducts): void
    {
        $message = match (true) {
            $supplierProducts->isEmpty() => __('Choose the products to repair.'),
            !$supplier->agent_id => __('Products can only go to a supplier of the same agent.'),
            in_array($supplier->code, self::REPAIRABLE_SUPPLIER_CODES, true) => __('Choose the real supplier of these products.'),
            $supplierProducts->contains(fn (SupplierProduct $supplierProduct) => !in_array($supplierProduct->supplier?->code, self::REPAIRABLE_SUPPLIER_CODES, true)) => __('Only products under :suppliers can be repaired.', ['suppliers' => implode(', ', self::REPAIRABLE_SUPPLIER_CODES)]),
            $supplierProducts->contains(fn (SupplierProduct $supplierProduct) => $supplierProduct->agent_id !== $supplier->agent_id) => __('Products can only go to a supplier of the same agent.'),
            default => $this->onOrderedPurchaseOrders($supplierProducts),
        };

        if ($message) {
            throw ValidationException::withMessages(['supplier_product_ids' => $message]);
        }
    }

    /**
     * A purchase order AWChina already confirmed is being made: its lines stay as they are, so the product
     * waits until that order is received or cancelled.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    private function onOrderedPurchaseOrders(Collection $supplierProducts): ?string
    {
        $ordered = PurchaseOrderTransaction::query()
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->whereIn('purchase_order_transactions.supplier_product_id', $supplierProducts->pluck('id'))
            ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::agreed())
            ->whereNull('purchase_orders.deleted_at')
            ->whereNull('purchase_order_transactions.deleted_at')
            ->distinct()
            ->orderBy('supplier_products.code')
            ->get(['supplier_products.code', 'purchase_orders.reference']);

        if ($ordered->isEmpty()) {
            return null;
        }

        return __('These products are on a purchase order the supplier already confirmed, repair them once it is received or cancelled: :lines', [
            'lines' => $ordered->map(fn ($line) => $line->code.' ('.$line->reference.')')->implode(', '),
        ]);
    }

    /**
     * The agent's own organisation, or one that buys through that agent.
     */
    public static function canRepairIn(Organisation $organisation, Supplier $supplier): bool
    {
        return $supplier->agent_id
            && ($organisation->agent?->id === $supplier->agent_id
                || OrgAgent::where('organisation_id', $organisation->id)->where('agent_id', $supplier->agent_id)->exists());
    }

    public function rules(): array
    {
        return [
            'supplier_product_ids'   => ['required', 'array', 'min:1'],
            'supplier_product_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Supplier $supplier, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        abort_unless(self::canRepairIn($organisation, $supplier), 404);

        return $this->handle(
            $supplier,
            SupplierProduct::where('agent_id', $supplier->agent_id)
                ->whereIn('id', $this->validatedData['supplier_product_ids'])
                ->with('supplier', 'orgSupplierProducts.orgSupplier')
                ->get()
        );
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function action(Supplier $supplier, Collection $supplierProducts): array
    {
        $this->asAction = true;

        return $this->handle($supplier, $supplierProducts);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
