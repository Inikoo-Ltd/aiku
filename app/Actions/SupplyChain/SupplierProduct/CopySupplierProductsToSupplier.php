<?php

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Goods\StockHasSupplierProduct\SetStockPreferredSupplierProduct;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
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

    public const FREE_SUPPLIERS_KEY = 0;

    private bool $isInGroup = false;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->isInGroup) {
            return $this->canEditFreeSuppliers($request->user());
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

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
     * With $makePreferred the target supplier becomes the preferred supplier of every SKO the source
     * product supplies, at group level and in every organisation, so purchase orders go to it; this
     * also applies to products the target already had under the same code, for the SKOs both share.
     *
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, Collection $supplierProducts, bool $makePreferred = false): int
    {
        $this->assertProductsCanBeCopied($supplier, $supplierProducts);

        $existingProducts = $supplier->supplierProducts()
            ->withTrashed()
            ->get()
            ->keyBy(fn (SupplierProduct $supplierProduct) => mb_strtolower($supplierProduct->code));

        $supplierProducts = $supplierProducts
            ->unique(fn (SupplierProduct $supplierProduct) => mb_strtolower($supplierProduct->code))
            ->values();

        [$alreadyThere, $toCopy] = $supplierProducts
            ->partition(fn (SupplierProduct $supplierProduct) => $existingProducts->has(mb_strtolower($supplierProduct->code)));

        if ($toCopy->isEmpty() && !$makePreferred) {
            throw ValidationException::withMessages([
                'org_supplier_products' => __('Supplier :supplier already has all the selected products.', ['supplier' => $supplier->code]),
            ]);
        }

        DB::transaction(function () use ($supplier, $toCopy, $alreadyThere, $existingProducts, $makePreferred) {
            foreach ($toCopy as $supplierProduct) {
                $copy = $this->copySupplierProduct($supplier, $supplierProduct);

                if ($makePreferred) {
                    $this->makePreferred($supplierProduct, $copy);
                }
            }

            if ($makePreferred) {
                foreach ($alreadyThere as $supplierProduct) {
                    $existing = $existingProducts->get(mb_strtolower($supplierProduct->code));

                    if (!$existing->trashed()) {
                        $this->makePreferred($supplierProduct, $existing);
                    }
                }
            }
        });

        return $toCopy->count();
    }

    private function makePreferred(SupplierProduct $source, SupplierProduct $target): void
    {
        if (!$this->canBecomePreferred($target)) {
            return;
        }

        $sharedStockIds = StockHasSupplierProduct::where('supplier_product_id', $target->id)
            ->whereIn('stock_id', StockHasSupplierProduct::where('supplier_product_id', $source->id)->select('stock_id'))
            ->pluck('stock_id');

        Stock::whereIn('id', $sharedStockIds)
            ->get()
            ->each(fn (Stock $stock) => SetStockPreferredSupplierProduct::make()->handle($stock, $target));
    }

    public static function canBecomePreferred(SupplierProduct $target): bool
    {
        return $target->is_available
            && !in_array($target->state, [SupplierProductStateEnum::DISCONTINUING, SupplierProductStateEnum::DISCONTINUED], true)
            && $target->supplier?->status;
    }

    /**
     * Making the target the preferred supplier re-points the SKOs in every buying organisation, which is the
     * goods.edit permission SetStockPreferredSupplierProduct asks for, not only the agent's procurement.
     */
    public function canMakePreferred(?User $user): bool
    {
        return (bool) $user?->authTo('goods.edit');
    }

    private function assertCanMakePreferred(bool $makePreferred, ?User $user): void
    {
        if ($makePreferred && !$this->canMakePreferred($user)) {
            throw ValidationException::withMessages([
                'make_preferred' => __('You are not allowed to change the preferred supplier of the SKOs.'),
            ]);
        }
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

    public function canEditFreeSuppliers(?User $user): bool
    {
        return (bool) $user?->authTo('supply-chain.edit');
    }

    public function canEditSupplier(Supplier $supplier, ?User $user): bool
    {
        if (!$supplier->agent_id) {
            return $this->canEditFreeSuppliers($user);
        }

        return $this->getAccessibleAgents($user)->contains('id', $supplier->agent_id);
    }

    /**
     * Supplier products of the agents in $agentIds and, with $includeFreeSuppliers, of every free supplier.
     *
     * @param  Collection<int, int>|array<int, int>  $agentIds
     * @return Builder<SupplierProduct>
     */
    public function copyableSupplierProducts(Collection|array $agentIds, bool $includeFreeSuppliers): Builder
    {
        return SupplierProduct::query()->where(function (Builder $query) use ($agentIds, $includeFreeSuppliers) {
            $query->whereIn('agent_id', $agentIds);

            if ($includeFreeSuppliers) {
                $query->orWhereNull('agent_id');
            }
        });
    }

    /**
     * @return array<int, array{id: int, label: string, create_route: array{name: string, parameters: array<int, string>}}>
     */
    private function getAgentOptions(Collection $accessibleAgents): array
    {
        return $accessibleAgents
            ->map(fn (Agent $accessibleAgent) => [
                'id'           => $accessibleAgent->id,
                'label'        => $accessibleAgent->organisation->name,
                'create_route' => [
                    'name'       => 'grp.org.procurement.org_suppliers.create_new',
                    'parameters' => [$accessibleAgent->organisation->slug],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * What the Supplier Products page of a supplier needs to push products to another supplier, pull
     * products from one, or start a new supplier with them; null when the user may not edit that supplier.
     * A free supplier shares with the other free suppliers (listed as agent FREE_SUPPLIERS_KEY) and with
     * the suppliers of the agents the user may edit.
     *
     * @return array{agent_id: int, agents: array<int, array{id: int, label: string, create_route: array{name: string, parameters: array<int, string>}}>, copy_route: array{name: string, parameters: array<int, int>}, supplier_id: int, source_products_route: array{name: string, parameters: array<int, int>}, can_make_preferred: bool}|null
     */
    public function getCopyProductsProps(Supplier $supplier, ?User $user): ?array
    {
        $accessibleAgents = $this->getAccessibleAgents($user);

        if (!$supplier->agent_id) {
            if (!$this->canEditFreeSuppliers($user)) {
                return null;
            }

            return [
                'agent_id'   => self::FREE_SUPPLIERS_KEY,
                'agents'     => [
                    [
                        'id'           => self::FREE_SUPPLIERS_KEY,
                        'label'        => __('Free suppliers'),
                        'create_route' => [
                            'name'       => 'grp.supply-chain.suppliers.create',
                            'parameters' => [],
                        ],
                    ],
                    ...$this->getAgentOptions($accessibleAgents),
                ],
                'copy_route' => [
                    'name'       => 'grp.models.supplier.supplier_products.copy',
                    'parameters' => [],
                ],
                'supplier_id'           => $supplier->id,
                'source_products_route' => [
                    'name'       => 'grp.json.supplier.supplier_products_to_copy_in_group',
                    'parameters' => [$supplier->id],
                ],
                'can_make_preferred' => $this->canMakePreferred($user),
            ];
        }

        $agent = $accessibleAgents->firstWhere('id', $supplier->agent_id);

        if (!$agent) {
            return null;
        }

        return [
            'agent_id'   => $agent->id,
            'agents'     => $this->getAgentOptions($accessibleAgents),
            'copy_route' => [
                'name'       => 'grp.models.org.supplier.supplier_products.copy',
                'parameters' => [$agent->organisation_id],
            ],
            'supplier_id'           => $supplier->id,
            'source_products_route' => [
                'name'       => 'grp.json.supplier.supplier_products_to_copy',
                'parameters' => [$agent->organisation_id, $supplier->id],
            ],
            'can_make_preferred' => $this->canMakePreferred($user),
        ];
    }

    /**
     * @return array<int, array{id: int, agent_id: int, label: string, number_products: int, company_name: string|null, contact_name: string|null}>
     */
    public function getCopyTargetsProps(Supplier $supplier, ?User $user): array
    {
        $agentIds             = $this->getAccessibleAgents($user)->pluck('id');
        $includeFreeSuppliers = !$supplier->agent_id && $this->canEditFreeSuppliers($user);

        return Supplier::where('group_id', $supplier->group_id)
            ->where(function ($query) use ($agentIds, $includeFreeSuppliers) {
                $query->whereIn('agent_id', $agentIds);

                if ($includeFreeSuppliers) {
                    $query->orWhereNull('agent_id');
                }
            })
            ->where('status', true)
            ->where('id', '!=', $supplier->id)
            ->withCount('supplierProducts')
            ->orderBy('code')
            ->get(['id', 'agent_id', 'code', 'name', 'company_name', 'contact_name'])
            ->map(fn (Supplier $target) => [
                'id'              => $target->id,
                'agent_id'        => $target->agent_id ?? self::FREE_SUPPLIERS_KEY,
                'number_products' => $target->supplier_products_count,
                'label'           => $target->name && $target->name !== $target->code ? $target->code.' — '.$target->name : $target->code,
                'company_name'    => $target->company_name,
                'contact_name'    => $target->contact_name,
            ])
            ->all();
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
    }

    public function rules(): array
    {
        return [
            'org_supplier_products'   => ['required_without:supplier_products', 'array', 'min:1'],
            'org_supplier_products.*' => ['integer'],
            'supplier_products'       => ['required_without:org_supplier_products', 'array', 'min:1'],
            'supplier_products.*'     => ['integer'],
            'make_preferred'          => ['sometimes', 'boolean'],
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

        $makePreferred = (bool) ($this->validatedData['make_preferred'] ?? false);
        $this->assertCanMakePreferred($makePreferred, $request->user());

        if (isset($this->validatedData['supplier_products'])) {
            return $this->handle(
                $supplier,
                SupplierProduct::whereIn('agent_id', $accessibleAgents->pluck('id'))
                    ->where('supplier_id', '!=', $supplier->id)
                    ->whereIn('id', $this->validatedData['supplier_products'])
                    ->get(),
                $makePreferred
            );
        }

        return $this->handle($supplier, $this->getAgentsSupplierProducts([$agent->id], $this->validatedData['org_supplier_products']), $makePreferred);
    }

    /**
     * The group Supplier Products page of a free supplier posts supplier products of free suppliers
     * and of the agents the user may edit, to or from any of those suppliers.
     *
     * @throws \Throwable
     */
    public function inGroup(Supplier $supplier, ActionRequest $request): int
    {
        $this->isInGroup = true;
        $this->initialisationFromGroup($supplier->group, $request);

        abort_unless($this->canEditSupplier($supplier, $request->user()), 404);

        $makePreferred = (bool) ($this->validatedData['make_preferred'] ?? false);
        $this->assertCanMakePreferred($makePreferred, $request->user());

        return $this->handle(
            $supplier,
            $this->copyableSupplierProducts($this->getAccessibleAgents($request->user())->pluck('id'), true)
                ->where('group_id', $supplier->group_id)
                ->where('supplier_id', '!=', $supplier->id)
                ->whereIn('id', $this->validatedData['supplier_products'] ?? [])
                ->get(),
            $makePreferred
        );
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     *
     * @throws \Throwable
     */
    public function action(Supplier $supplier, Collection $supplierProducts, int $hydratorsDelay = 0, bool $makePreferred = false): int
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        return $this->handle($supplier, $supplierProducts, $makePreferred);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
