<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-25 15:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\SetOrgStockPreferredSupplierProduct;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\SetAgentDraftLine;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrderTransaction;
use App\Actions\SupplyChain\Supplier\GetSupplierOptionFields;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Gives supplier products of an agent to another supplier of the same agent. When the target already sells the same
 * SKOs, the product merges into the target's product: its SKOs are bought from the target instead and it is discontinued.
 * Otherwise the product itself moves to the target with its history, under the code, name, cost and carton size the
 * user gives for the target.
 */
class MoveSupplierProductsToSupplier extends OrgAction
{
    private Supplier $supplier;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return CopySupplierProductsToSupplier::make()->canEditSupplier($this->supplier, $request->user());
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @param  array<int, array{code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null}>  $newProducts  keyed by supplier product id
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, Collection $supplierProducts, array $newProducts = []): array
    {
        $this->assertCanMove($supplier, $supplierProducts);

        $twins          = $this->twinsOf($supplier, $supplierProducts);
        $untwinned      = $supplierProducts->reject(fn (SupplierProduct $supplierProduct) => $twins->has($supplierProduct->id));
        $sameSkoLeads   = $this->sameSkoLeadsOf($untwinned);
        $movingProducts = $untwinned->reject(fn (SupplierProduct $supplierProduct) => $sameSkoLeads->has($supplierProduct->id));
        $this->assertNewProductsFilled($supplier, $movingProducts, $newProducts);

        $sourceSuppliers = Supplier::whereIn('id', $supplierProducts->pluck('supplier_id')->unique())->get();
        $orgSuppliers    = collect();
        $result          = ['moved' => 0, 'merged' => 0];

        DB::transaction(function () use ($supplier, $supplierProducts, $twins, $sameSkoLeads, $newProducts, $orgSuppliers, &$result) {
            foreach ($supplierProducts->sortBy(fn (SupplierProduct $supplierProduct) => $sameSkoLeads->has($supplierProduct->id) ? 1 : 0) as $supplierProduct) {
                $twin = $twins->get($supplierProduct->id) ?? $sameSkoLeads->get($supplierProduct->id)?->refresh();

                if ($twin) {
                    $this->mergeIntoTwin($supplierProduct, $twin);
                    $result['merged']++;

                    continue;
                }

                $this->moveToSupplier($supplierProduct, $supplier, $orgSuppliers);

                $newProduct = $newProducts[$supplierProduct->id];
                UpdateSupplierProduct::make()->action($supplierProduct->refresh(), array_filter([
                    'code'             => trim((string) $newProduct['code']),
                    'name'             => trim((string) Arr::get($newProduct, 'name')) ?: null,
                    'cost'             => (float) $newProduct['cost'],
                    'units_per_carton' => (int) $newProduct['units_per_carton'],
                ], fn ($value) => $value !== null), strict: false);
                $result['moved']++;
            }
        });

        $this->hydrateSuppliers($sourceSuppliers->push($supplier), $orgSuppliers);

        return $result;
    }

    /**
     * The target's product for each source product: one selling exactly the same SKOs, an active one first.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return Collection<int, SupplierProduct>  keyed by source supplier product id
     */
    public function twinsOf(Supplier $supplier, Collection $supplierProducts): Collection
    {
        return $this->twinsBySupplier([$supplier->id], $supplierProducts)->get($supplier->id, collect());
    }

    /**
     * @param  array<int, int>  $supplierIds
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return Collection<int, Collection<int, SupplierProduct>>  keyed by target supplier id, then by source supplier product id
     */
    private function twinsBySupplier(array $supplierIds, Collection $supplierProducts): Collection
    {
        $sourceStocks = $this->stockIdsOf($supplierProducts->pluck('id')->all());
        $stockIds     = $sourceStocks->flatten()->unique()->values()->all();

        if ($stockIds === [] || $supplierIds === []) {
            return collect();
        }

        $candidates = SupplierProduct::whereIn('supplier_id', $supplierIds)
            ->whereIn('id', DB::table('stock_has_supplier_products')->whereIn('stock_id', $stockIds)->select('supplier_product_id'))
            ->get()
            ->sortBy(fn (SupplierProduct $candidate) => $candidate->state === SupplierProductStateEnum::ACTIVE ? 0 : 1);
        $candidateStocks = $this->stockIdsOf($candidates->pluck('id')->all());

        return $candidates
            ->groupBy('supplier_id')
            ->map(fn (Collection $supplierCandidates) => $supplierProducts
                ->mapWithKeys(function (SupplierProduct $supplierProduct) use ($sourceStocks, $supplierCandidates, $candidateStocks) {
                    $stocks = $sourceStocks->get($supplierProduct->id);

                    return [$supplierProduct->id => $stocks ? $supplierCandidates->first(fn (SupplierProduct $candidate) => $candidateStocks->get($candidate->id) === $stocks) : null];
                })
                ->filter());
    }

    /**
     * Chosen products selling exactly the same SKOs as another chosen product (UK and EU plug of one SKO) do not
     * both move: one moves, an active one first, and the others merge into it once it is at the target.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts  the chosen products the target has no twin for
     * @return Collection<int, SupplierProduct>  the product that moves, keyed by the id of each product merging into it
     */
    public function sameSkoLeadsOf(Collection $supplierProducts): Collection
    {
        $stocks = $this->stockIdsOf($supplierProducts->pluck('id')->all());
        $leads  = collect();

        $supplierProducts
            ->filter(fn (SupplierProduct $supplierProduct) => $stocks->has($supplierProduct->id))
            ->sortBy(fn (SupplierProduct $supplierProduct) => [$supplierProduct->state === SupplierProductStateEnum::ACTIVE ? 0 : 1, $supplierProduct->id])
            ->groupBy(fn (SupplierProduct $supplierProduct) => implode(',', $stocks->get($supplierProduct->id)))
            ->each(function (Collection $sameSkoProducts) use ($leads) {
                $sameSkoProducts->skip(1)->each(fn (SupplierProduct $supplierProduct) => $leads->put($supplierProduct->id, $sameSkoProducts->first()));
            });

        return $leads;
    }

    /**
     * @param  array<int, int>  $supplierProductIds
     * @return Collection<int, array<int, int>>
     */
    private function stockIdsOf(array $supplierProductIds): Collection
    {
        return DB::table('stock_has_supplier_products')
            ->whereIn('supplier_product_id', $supplierProductIds)
            ->get(['supplier_product_id', 'stock_id'])
            ->groupBy('supplier_product_id')
            ->map(fn (Collection $rows) => $rows->pluck('stock_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all());
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    private function assertCanMove(Supplier $supplier, Collection $supplierProducts): void
    {
        $message = match (true) {
            $supplierProducts->isEmpty() => __('Choose the products to move.'),
            !$supplier->agent_id => __('Products can only go to a supplier of the same agent.'),
            !$supplier->status => __(':supplier is archived.', ['supplier' => $supplier->code]),
            $supplierProducts->contains(fn (SupplierProduct $supplierProduct) => $supplierProduct->agent_id !== $supplier->agent_id) => __('Products can only go to a supplier of the same agent.'),
            $supplierProducts->contains(fn (SupplierProduct $supplierProduct) => $supplierProduct->supplier_id === $supplier->id) => __('Some products already belong to :supplier: :codes', [
                'supplier' => $supplier->code,
                'codes'    => $supplierProducts->where('supplier_id', $supplier->id)->pluck('code')->implode(', '),
            ]),
            default => $this->onAgreedPurchaseOrders($supplierProducts),
        };

        if ($message) {
            throw ValidationException::withMessages(['supplier_products' => $message]);
        }
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts  the products that move, not the ones merging into a twin
     * @param  array<int, array<string, mixed>>  $newProducts
     */
    private function assertNewProductsFilled(Supplier $supplier, Collection $supplierProducts, array $newProducts): void
    {
        $errors     = [];
        $typedCodes = [];

        foreach ($supplierProducts as $supplierProduct) {
            $newProduct = $newProducts[$supplierProduct->id] ?? [];
            $code       = trim((string) Arr::get($newProduct, 'code'));
            $key        = "products.$supplierProduct->id";

            if ($code === '') {
                $errors["$key.code"] = __('Give the code at :supplier.', ['supplier' => $supplier->code]);
            } elseif (in_array(mb_strtolower($code), $typedCodes, true)) {
                $errors["$key.code"] = __('Code :code is given to another product moving to :supplier.', ['supplier' => $supplier->code, 'code' => $code]);
            } elseif ($supplier->supplierProducts()->whereRaw('lower(code) = ?', [mb_strtolower($code)])->exists()) {
                $errors["$key.code"] = __(':supplier already has a product :code.', ['supplier' => $supplier->code, 'code' => $code]);
            }
            $typedCodes[] = mb_strtolower($code);

            if (!is_numeric(Arr::get($newProduct, 'cost')) || (float) $newProduct['cost'] < 0) {
                $errors["$key.cost"] = __('Give the unit cost.');
            }

            if (!is_numeric(Arr::get($newProduct, 'units_per_carton')) || (int) $newProduct['units_per_carton'] < 1) {
                $errors["$key.units_per_carton"] = __('Give the units per carton.');
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A purchase order the supplier already agreed is being made: its lines stay as they are, so the product
     * waits until that order is received or cancelled.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    public function onAgreedPurchaseOrders(Collection $supplierProducts): ?string
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

        return __('These products are on a purchase order the supplier already confirmed, move them once it is received or cancelled: :lines', [
            'lines' => $ordered->map(fn ($line) => $line->code.' ('.$line->reference.')')->implode(', '),
        ]);
    }

    /**
     * @param  Collection<int, OrgSupplier>  $orgSuppliers
     */
    public function moveToSupplier(SupplierProduct $supplierProduct, Supplier $supplier, Collection $orgSuppliers): void
    {
        foreach ($supplierProduct->orgSupplierProducts as $orgSupplierProduct) {
            $targetOrgSupplier = $this->targetOrgSupplier($supplier, $orgSupplierProduct->organisation_id);
            $orgSuppliers->push($orgSupplierProduct->orgSupplier, $targetOrgSupplier);
            $orgSupplierProduct->update(['org_supplier_id' => $targetOrgSupplier->id]);
            $this->moveDraftLines($supplierProduct, $orgSupplierProduct);
        }

        $supplierProduct->update(['supplier_id' => $supplier->id]);
    }

    public function mergeIntoTwin(SupplierProduct $supplierProduct, SupplierProduct $twin): void
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
     * The target's product may be discontinued while the source is still sold: it comes back as active.
     * Reactivating it would also reactivate it in every organisation, so each organisation keeps its own
     * state, and only those where the source was active take it over afterwards.
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
     * Purchase orders with the old supplier that the supplier has not confirmed yet, drafts or submitted,
     * give their line of this product to the target supplier's draft, opened if it has none, so nothing is
     * ordered from the old supplier for a product it no longer holds.
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
     * @param  Collection<int, Supplier>  $suppliers
     * @param  Collection<int, OrgSupplier|null>  $orgSuppliers
     */
    public function hydrateSuppliers(Collection $suppliers, Collection $orgSuppliers): void
    {
        foreach ($suppliers->unique('id') as $supplier) {
            SupplierHydrateSupplierProducts::dispatch($supplier);
        }
        foreach ($orgSuppliers->filter()->unique('id') as $orgSupplier) {
            OrgSupplierHydrateOrgSupplierProducts::dispatch($orgSupplier);
        }
    }

    /**
     * The other suppliers of the agent, each with its product for every chosen supplier product when it already sells the same SKOs.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return array<int, array<string, mixed>>
     */
    public function getTargets(Agent $agent, Collection $supplierProducts): array
    {
        $sourceSupplierIds = $supplierProducts->pluck('supplier_id')->unique()->all();
        $suppliers         = Supplier::where('agent_id', $agent->id)
            ->where('status', true)
            ->whereNotIn('id', $sourceSupplierIds)
            ->with('currency')
            ->orderBy('code')
            ->get();
        $twinsBySupplier = $this->twinsBySupplier($suppliers->pluck('id')->all(), $supplierProducts);
        $sameSkoLeads    = $this->sameSkoLeadsOf($supplierProducts);
        $optionFields    = GetSupplierOptionFields::make()->handle($suppliers);

        return $suppliers
            ->map(function (Supplier $supplier) use ($supplierProducts, $twinsBySupplier, $sameSkoLeads, $optionFields) {
                $twins    = $twinsBySupplier->get($supplier->id, collect());
                $products = $supplierProducts->mapWithKeys(fn (SupplierProduct $supplierProduct) => [
                    $supplierProduct->id => ($twin = $twins->get($supplierProduct->id)) ? [
                        'code'             => $twin->code,
                        'cost'             => (float) $twin->cost,
                        'units_per_carton' => (float) $twin->units_per_carton,
                    ] : null,
                ]);
                $mergesIntoMoving = $sameSkoLeads
                    ->reject(fn (SupplierProduct $lead, int $supplierProductId) => $twins->has($supplierProductId))
                    ->map(fn (SupplierProduct $lead) => $lead->id);

                return [
                    'id'             => $supplier->id,
                    'slug'           => $supplier->slug,
                    'name'           => $supplier->officialName(),
                    ...$optionFields->get($supplier->id),
                    'currency'       => $supplier->currency?->code,
                    'products'       => $products->all(),
                    'products_count' => $products->filter()->count(),
                    'merges_into'    => (object) $mergesIntoMoving->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'products'                    => ['required', 'array', 'min:1', 'max:200'],
            'products.*.id'               => ['required', 'integer', 'distinct'],
            'products.*.code'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'products.*.name'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'products.*.cost'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products.*.units_per_carton' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function asController(Supplier $supplier, ActionRequest $request): array
    {
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        $products         = collect($this->validatedData['products'])->keyBy('id');
        $supplierProducts = SupplierProduct::where('agent_id', $supplier->agent_id)
            ->whereIn('id', $products->keys())
            ->with('supplier', 'orgSupplierProducts.orgSupplier')
            ->get();

        if ($supplierProducts->count() !== $products->count()) {
            throw ValidationException::withMessages(['supplier_products' => __('Products can only go to a supplier of the same agent.')]);
        }

        return $this->handle($supplier, $supplierProducts, $products->all());
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @param  array<int, array<string, mixed>>  $newProducts
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function action(Supplier $supplier, Collection $supplierProducts, array $newProducts = []): array
    {
        $this->asAction = true;

        return $this->handle($supplier, $supplierProducts, $newProducts);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
