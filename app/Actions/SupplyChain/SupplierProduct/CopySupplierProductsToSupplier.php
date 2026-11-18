<?php

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CopySupplierProductsToSupplier extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementEditAuthorisation;

    private const COPIED_FIELDS = [
        'code',
        'name',
        'cost',
        'units_per_pack',
        'units_per_carton',
        'cbm',
        'extra_costs',
        'carton_weight',
        'carton_net_weight',
    ];

    private const COPIED_DATA_FIELDS = [
        'delivery_time',
        'unit_expense',
    ];

    private const MONEY_FIELDS = [
        'cost',
        'extra_costs',
        'unit_expense',
    ];

    private const COPIED_COLUMNS = [
        'description',
        'image_id',
        'minimum_carton_order',
        'estimated_lead_time_days',
        'supplier_unit',
        'units_per_supplier_unit',
    ];

    /**
     * A supplier product belongs to one supplier, so a product two suppliers both sell is one supplier
     * product each, with the same code, trade units and SKOs. The copy joins each SKO as a further,
     * not preferred, source. Products the supplier already has under the same code are left alone.
     * A supplier of another agent may work in another currency: money is converted at today's rate.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, Collection $supplierProducts): int
    {
        $this->assertProductsCanBeCopied($supplier, $supplierProducts);

        $existingCodes = $supplier->supplierProducts()
            ->withTrashed()
            ->pluck('code')
            ->map(fn (string $code) => mb_strtolower($code))
            ->flip();

        $supplierProducts = $supplierProducts
            ->filter(fn (SupplierProduct $supplierProduct) => !$existingCodes->has(mb_strtolower($supplierProduct->code)))
            ->unique(fn (SupplierProduct $supplierProduct) => mb_strtolower($supplierProduct->code))
            ->values();

        if ($supplierProducts->isEmpty()) {
            throw ValidationException::withMessages([
                'org_supplier_products' => __('Supplier :supplier already has all the selected products.', ['supplier' => $supplier->code]),
            ]);
        }

        DB::transaction(function () use ($supplier, $supplierProducts) {
            foreach ($supplierProducts as $supplierProduct) {
                $this->copySupplierProduct($supplier, $supplierProduct);
            }
        });

        return $supplierProducts->count();
    }

    /**
     * @throws \Throwable
     */
    private function copySupplierProduct(Supplier $supplier, SupplierProduct $supplierProduct): SupplierProduct
    {
        $modelData = array_merge(
            Arr::only($supplierProduct->getAttributes(), self::COPIED_FIELDS),
            array_filter(Arr::only($supplierProduct->data ?? [], self::COPIED_DATA_FIELDS), fn ($value) => $value !== null),
        );

        $modelData = $this->convertMoney($supplier, $supplierProduct, $modelData);

        $copy = StoreSupplierProduct::make()->action(
            $supplier,
            $modelData,
            hydratorsDelay: $this->hydratorsDelay,
            strict: false
        );

        $copy->update(Arr::only($supplierProduct->getAttributes(), self::COPIED_COLUMNS));

        SyncSupplierProductTradeUnits::run(
            $copy,
            $supplierProduct->tradeUnits
                ->mapWithKeys(fn ($tradeUnit) => [
                    $tradeUnit->id => ['quantity' => $tradeUnit->pivot->quantity, 'notes' => $tradeUnit->pivot->notes],
                ])
                ->all()
        );

        $this->attachCopyToOrgStocks($supplierProduct, $copy);

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @return array<string, mixed>
     */
    private function convertMoney(Supplier $supplier, SupplierProduct $supplierProduct, array $modelData): array
    {
        if ($supplierProduct->currency_id === $supplier->currency_id) {
            return $modelData;
        }

        $exchange = GetCurrencyExchange::run($supplierProduct->currency, $supplier->currency);

        if (!$exchange) {
            throw ValidationException::withMessages([
                'org_supplier_products' => __('No exchange rate from :from to :to, the currency of supplier :supplier.', [
                    'from'     => $supplierProduct->currency->code,
                    'to'       => $supplier->currency->code,
                    'supplier' => $supplier->code,
                ]),
            ]);
        }

        foreach (self::MONEY_FIELDS as $field) {
            if (isset($modelData[$field])) {
                $modelData[$field] = round((float) $modelData[$field] * $exchange, 3);
            }
        }

        return $modelData;
    }

    private function attachCopyToOrgStocks(SupplierProduct $supplierProduct, SupplierProduct $copy): void
    {
        $copyOrgSupplierProducts = $copy->orgSupplierProducts()->get()->keyBy('organisation_id');

        $links = OrgStockHasOrgSupplierProduct::with('orgStock')
            ->whereIn('org_supplier_product_id', $supplierProduct->orgSupplierProducts()->select('id'))
            ->get();

        foreach ($links as $link) {
            $copyOrgSupplierProduct = $link->orgStock ? $copyOrgSupplierProducts->get($link->orgStock->organisation_id) : null;

            if ($copyOrgSupplierProduct) {
                AttachOrgSupplierProductToOrgStock::make()->action($link->orgStock, $copyOrgSupplierProduct);
            }
        }
    }

    /**
     * The agents whose procurement the user may edit: products are copied from and to their suppliers only.
     *
     * @return Collection<int, Agent>
     */
    public function getAccessibleAgents(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        return Agent::with('organisation')
            ->where('group_id', $user->group_id)
            ->orderBy('code')
            ->get()
            ->filter(fn (Agent $agent) => $user->authTo("procurement.{$agent->organisation_id}.edit"))
            ->values();
    }

    /**
     * @param  array<int, int>  $agentIds
     * @param  array<int, int>  $orgSupplierProductIds
     * @return Collection<int, SupplierProduct>
     */
    public function getAgentsSupplierProducts(array $agentIds, array $orgSupplierProductIds): Collection
    {
        return SupplierProduct::whereIn('agent_id', $agentIds)
            ->whereIn('id', OrgSupplierProduct::whereIn('id', $orgSupplierProductIds)->select('supplier_product_id'))
            ->get();
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    private function assertProductsCanBeCopied(Supplier $supplier, Collection $supplierProducts): void
    {
        if ($supplierProducts->isEmpty()) {
            throw ValidationException::withMessages([
                'org_supplier_products' => __('Select at least one product.'),
            ]);
        }

        if (!$supplier->agent_id) {
            throw ValidationException::withMessages([
                'org_supplier_products' => __('Products can only be shared with the supplier of an agent.'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'org_supplier_products'   => ['required_without:supplier_products', 'array', 'min:1'],
            'org_supplier_products.*' => ['integer'],
            'supplier_products'       => ['required_without:org_supplier_products', 'array', 'min:1'],
            'supplier_products.*'     => ['integer'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Supplier $supplier, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        $agent            = $this->getOrganisationAgent($organisation);
        $accessibleAgents = $this->getAccessibleAgents($request->user());
        abort_unless($agent && $accessibleAgents->contains('id', $supplier->agent_id), 404);

        if (isset($this->validatedData['supplier_products'])) {
            return $this->handle(
                $supplier,
                SupplierProduct::whereIn('agent_id', $accessibleAgents->pluck('id'))
                    ->where('supplier_id', '!=', $supplier->id)
                    ->whereIn('id', $this->validatedData['supplier_products'])
                    ->get()
            );
        }

        return $this->handle($supplier, $this->getAgentsSupplierProducts([$agent->id], $this->validatedData['org_supplier_products']));
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     *
     * @throws \Throwable
     */
    public function action(Supplier $supplier, Collection $supplierProducts, int $hydratorsDelay = 0): int
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        return $this->handle($supplier, $supplierProducts);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
