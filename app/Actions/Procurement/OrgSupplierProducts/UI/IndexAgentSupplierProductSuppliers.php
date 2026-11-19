<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\SupplierProduct;
use App\Http\Resources\Procurement\AgentSupplierProductSuppliersResource;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Services\QueryBuilder;
use App\Support\AgentOrganisationSelection;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The Suppliers tab of an agent's supplier product: every product of the agent's suppliers that supplies the same
 * SKU (stock_has_supplier_products), other agents and free suppliers left out, and which of them each buying
 * organisation prefers (the active link with the highest local_priority on its SKO).
 */
class IndexAgentSupplierProductSuppliers extends OrgAction
{
    public function handle(SupplierProduct $supplierProduct, Agent $agent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(SupplierProduct::class)
            ->join('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->leftJoin('currencies', 'currencies.id', 'supplier_products.currency_id')
            ->where('suppliers.agent_id', $agent->id)
            ->whereIn('supplier_products.id', StockHasSupplierProduct::whereIn('stock_id', self::stockIds($supplierProduct))->select('supplier_product_id'))
            ->select([
                'supplier_products.id',
                'supplier_products.slug',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.cost',
                'supplier_products.state',
                'supplier_products.availability_status',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.slug as supplier_slug',
                'currencies.code as currency_code',
            ])
            ->defaultSort('supplier_code')
            ->allowedSorts(['supplier_code', 'code', 'cost'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * @return Collection<int, int>
     */
    public static function stockIds(SupplierProduct $supplierProduct): Collection
    {
        return StockHasSupplierProduct::where('supplier_product_id', $supplierProduct->id)->pluck('stock_id');
    }

    /**
     * The other suppliers of the agent that supply the same SKU, for the Provided by box of the showcase,
     * each linked to its own page when the agent has one.
     *
     * @return array<int, array{supplier_code: string, supplier_name: string, code: string, is_available: bool, route: array<string, mixed>|null}>
     */
    public static function otherSuppliers(SupplierProduct $supplierProduct, AgentOrganisationSelection $selection, Organisation $agentOrganisation): array
    {
        $agent = $agentOrganisation->agent;
        if (!$agent) {
            return [];
        }

        $others = SupplierProduct::query()
            ->join('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->where('suppliers.agent_id', $agent->id)
            ->where('supplier_products.id', '!=', $supplierProduct->id)
            ->whereIn('supplier_products.id', StockHasSupplierProduct::whereIn('stock_id', self::stockIds($supplierProduct))->select('supplier_product_id'))
            ->orderBy('suppliers.code')
            ->get([
                'supplier_products.id',
                'supplier_products.slug',
                'supplier_products.code',
                'supplier_products.is_available',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
            ]);

        $withPage = OrgSupplierProduct::whereIn('supplier_product_id', $others->pluck('id'))
            ->whereIn('org_agent_id', OrgAgent::where('agent_id', $agent->id)->select('id'))
            ->distinct()
            ->pluck('supplier_product_id')
            ->all();

        return $others->map(fn (SupplierProduct $other) => [
            'supplier_code' => $other->supplier_code,
            'supplier_name' => $other->supplier_name,
            'code'          => $other->code,
            'is_available'  => (bool) $other->is_available,
            'route'         => in_array($other->id, $withPage, true)
                ? $selection->route('grp.org.procurement.org_supplier_products.show', ['organisation' => $agentOrganisation->slug, 'supplierProduct' => $other->slug])
                : null,
        ])->values()->all();
    }

    /**
     * The supplier product each buying organisation prefers for these SKUs, by organisation id (null when none).
     *
     * @return array<int, int|null>
     */
    public static function preferredByOrganisation(SupplierProduct $supplierProduct, AgentOrganisationSelection $selection): array
    {
        $stockIds = self::stockIds($supplierProduct);

        return $selection->organisations()->mapWithKeys(fn (Organisation $organisation) => [
            $organisation->id => OrgStockHasOrgSupplierProduct::query()
                ->join('org_stocks', 'org_stocks.id', 'org_stock_has_org_supplier_products.org_stock_id')
                ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
                ->whereIn('org_stocks.stock_id', $stockIds)
                ->where('org_stocks.organisation_id', $organisation->id)
                ->where('org_stock_has_org_supplier_products.status', true)
                ->orderByDesc('org_stock_has_org_supplier_products.local_priority')
                ->orderBy('org_stock_has_org_supplier_products.id')
                ->value('org_supplier_products.supplier_product_id'),
        ])->all();
    }

    /**
     * What the Suppliers tab shows: the rows, with where each is preferred, and in All the preferred supplier of
     * every buying organisation; who may edit the agent's procurement also gets the Set as preferred route.
     */
    public static function tabData(SupplierProduct $supplierProduct, AgentOrganisationSelection $selection, Organisation $agentOrganisation, User $user, string $prefix): AnonymousResourceCollection
    {
        $preferred     = self::preferredByOrganisation($supplierProduct, $selection);
        $organisations = $selection->organisations()->keyBy('id');

        $suppliers = self::make()->handle($supplierProduct, $agentOrganisation->agent, $prefix);
        $withPage  = OrgSupplierProduct::whereIn('supplier_product_id', $suppliers->getCollection()->pluck('id'))
            ->whereIn('org_agent_id', OrgAgent::where('agent_id', $agentOrganisation->agent->id)->select('id'))
            ->distinct()
            ->pluck('supplier_product_id')
            ->all();
        $suppliers->through(function (SupplierProduct $row) use ($preferred, $organisations, $supplierProduct, $selection, $agentOrganisation, $withPage) {
            $row->setAttribute('preferred_in', collect($preferred)->filter(fn ($id) => $id === $row->id)->keys()->map(fn ($organisationId) => $organisations[$organisationId]->code)->values()->all());
            $row->setAttribute('is_preferred', $selection->selected !== null && ($preferred[$selection->selected->id] ?? null) === $row->id);
            $row->setAttribute('is_current', $row->id === $supplierProduct->id);
            $row->setAttribute('supplier_route', $selection->route('grp.org.procurement.org_suppliers.show', ['organisation' => $agentOrganisation->slug, 'supplier' => $row->supplier_slug]));
            $row->setAttribute('product_route', in_array($row->id, $withPage, true)
                ? $selection->route('grp.org.procurement.org_supplier_products.show', ['organisation' => $agentOrganisation->slug, 'supplierProduct' => $row->slug])
                : null);

            return $row;
        });

        $preferredProducts = SupplierProduct::whereIn('id', array_filter($preferred))->with('supplier')->get()->keyBy('id');

        return AgentSupplierProductSuppliersResource::collection($suppliers)->additional([
            'preferred_by_organisation' => $selection->selected ? null : $organisations->map(fn (Organisation $organisation) => [
                'organisation_code' => $organisation->code,
                'supplier_code'     => $preferredProducts->get($preferred[$organisation->id] ?? 0)?->supplier->code,
                'product_code'      => $preferredProducts->get($preferred[$organisation->id] ?? 0)?->code,
            ])->values()->all(),
            'set_preferred' => $user->authTo("procurement.$agentOrganisation->id.edit") ? [
                'route'           => ['name' => 'grp.models.org.agent_supplier_product.set_preferred', 'parameters' => ['organisation' => $agentOrganisation->id, 'supplierProduct' => $supplierProduct->id]],
                'organisation_id' => $selection->selected?->id,
                'scope'           => $selection->selected?->code,
            ] : null,
        ]);
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState(['title' => __('No other supplier of the agent supplies this SKU')])
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: 'code', label: __('Supplier code'), canBeHidden: false, sortable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false)
                ->column(key: 'cost', label: __('Unit cost'), canBeHidden: false, sortable: true, type: 'currency')
                ->column(key: 'availability', label: __('Availability'), canBeHidden: false)
                ->column(key: 'preferred', label: __('Preferred'), canBeHidden: false)
                ->column(key: 'actions', label: '', canBeHidden: false);
        };
    }
}
