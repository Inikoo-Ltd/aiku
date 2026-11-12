<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use Illuminate\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Support\Facades\DB;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\SupplyChain\SupplierProduct\RepairSupplierProductsSupplier;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The products of an AWChina catch-all record, to be sent to their real supplier.
 */
class IndexRepairSupplierProducts extends OrgAction
{
    use WithOrgSupplierSubNavigation;
    use WithProcurementAuthorisation;

    private const string ON_PO = 'exists (select 1 from purchase_order_transactions pot join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
        where pot.supplier_product_id = supplier_products.id and pot.deleted_at is null and po.state in (%s))';

    /**
     * @return array<string, string>
     */
    private static function purchaseOrderFilters(): array
    {
        $onDraft = sprintf(self::ON_PO, "'in_process', 'submitted'");
        $onSent  = sprintf(self::ON_PO, PurchaseOrderStateEnum::agreedSql());

        return [
            'none'  => "not $onDraft and not $onSent",
            'draft' => $onDraft,
            'sent'  => $onSent,
        ];
    }

    public function handle(Supplier $supplier, ?string $state, ?string $purchaseOrders): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for($this->supplierProducts($supplier))
            ->when($state, fn ($query) => $query->where('supplier_products.state', $state))
            ->when($purchaseOrders, fn ($query) => $query->whereRaw(self::purchaseOrderFilters()[$purchaseOrders]));

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'supplier_products.id',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.state',
            ])
            ->selectSub($this->purchaseOrderReferences([PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED]), 'draft_purchase_orders')
            ->selectSub($this->purchaseOrderReferences(PurchaseOrderStateEnum::agreed()), 'sent_purchase_orders')
            ->allowedSorts(['code', 'name', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(fn (SupplierProduct $supplierProduct) => array_merge($supplierProduct->only(['id', 'code', 'name', 'state']), [
                'draft_purchase_orders' => json_decode($supplierProduct->draft_purchase_orders ?? '[]', true),
                'sent_purchase_orders'  => json_decode($supplierProduct->sent_purchase_orders ?? '[]', true),
            ]));
    }

    /**
     * @param  array<int, PurchaseOrderStateEnum>  $states
     */
    private function purchaseOrderReferences(array $states): QueryBuilderContract
    {
        return DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->join('organisations as o', 'o.id', 'po.organisation_id')
            ->whereColumn('pot.supplier_product_id', 'supplier_products.id')
            ->whereNull('pot.deleted_at')
            ->whereNull('po.deleted_at')
            ->whereIn('po.state', array_map(fn (PurchaseOrderStateEnum $state) => $state->value, $states))
            ->selectRaw("json_agg(json_build_object(
                'organisation', o.code,
                'organisation_slug', o.slug,
                'reference', po.reference,
                'slug', po.slug,
                'state', po.state,
                'quantity', pot.quantity_ordered,
                'created_at', coalesce(po.in_process_at, po.date, po.created_at),
                'submitted_at', po.submitted_at,
                'confirmed_at', po.confirmed_at,
                'estimated_received_at', po.estimated_received_at
            ) order by po.id)");
    }

    private function supplierProducts(Supplier $supplier): Builder
    {
        return SupplierProduct::query()->where('supplier_products.supplier_id', $supplier->id);
    }

    /**
     * @return array{state: array<int, array{value: string, label: string, count: int}>, purchase_orders: array<int, array{value: string, label: string, count: int}>}
     */
    private function filterOptions(Supplier $supplier): array
    {
        $stateCounts = $this->supplierProducts($supplier)->selectRaw('state, count(*) as count')->groupBy('state')->pluck('count', 'state')->all();
        $poCounts    = (array) $this->supplierProducts($supplier)->toBase()->selectRaw(
            implode(', ', array_map(fn (string $key) => 'count(*) filter (where '.self::purchaseOrderFilters()[$key].') as '.$key, array_keys(self::purchaseOrderFilters())))
        )->first();

        return [
            'state'           => collect(SupplierProductStateEnum::labels())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label, 'count' => (int) ($stateCounts[$value] ?? 0)])
                ->values()
                ->all(),
            'purchase_orders' => [
                ['value' => 'none', 'label' => __('No PO'), 'count' => (int) $poCounts['none']],
                ['value' => 'draft', 'label' => __('On draft or submitted PO'), 'count' => (int) $poCounts['draft']],
                ['value' => 'sent', 'label' => __('On confirmed PO'), 'count' => (int) $poCounts['sent']],
            ],
        ];
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withLabelRecord([__('Product'), __('Products')])
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('Nothing left to repair')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'draft_purchase_orders', label: __('On draft or submitted POs, moved with it'), canBeHidden: false)
                ->column(key: 'sent_purchase_orders', label: __('On confirmed POs, cannot repair yet'), canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request);
        abort_unless(
            RepairSupplierProductsSupplier::canRepairIn($organisation, $orgSupplier->supplier)
            && in_array($orgSupplier->supplier->code, RepairSupplierProductsSupplier::REPAIRABLE_SUPPLIER_CODES, true),
            404
        );

        return $orgSupplier;
    }

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        $supplier       = $orgSupplier->supplier;
        $title          = __('Repair');
        $state          = $request->query('state', SupplierProductStateEnum::ACTIVE->value);
        $state          = SupplierProductStateEnum::tryFrom((string) $state)?->value;
        $purchaseOrders = array_key_exists((string) $request->query('purchase_orders'), self::purchaseOrderFilters()) ? (string) $request->query('purchase_orders') : null;

        return Inertia::render(
            'Procurement/RepairSupplierProducts',
            [
                'title'       => $title.' '.$supplier->code,
                'breadcrumbs' => ShowOrgSupplier::make()->getBreadcrumbs('grp.org.procurement.org_suppliers.show', $request->route()->originalParameters(), $title),
                'pageHead'    => [
                    'title'         => $supplier->name,
                    'model'         => $title,
                    'icon'          => [
                        'title' => __('Supplier'),
                        'icon'  => 'fal fa-person-dolly',
                    ],
                    'subNavigation' => $this->getOrgSupplierNavigation($orgSupplier),
                ],
                'suppliers'   => Supplier::where('agent_id', $supplier->agent_id)
                    ->whereNotIn('code', RepairSupplierProductsSupplier::REPAIRABLE_SUPPLIER_CODES)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (Supplier $target) => ['id' => $target->id, 'label' => $target->code.' · '.$target->name])
                    ->all(),
                'repairRoute' => [
                    'name'       => 'grp.models.org.supplier.supplier_products.repair',
                    'parameters' => [$this->organisation->id],
                ],
                'filterOptions' => $this->filterOptions($supplier),
                'active'        => ['state' => $state, 'purchase_orders' => $purchaseOrders],
                'data'        => JsonResource::collection($this->handle($supplier, $state, $purchaseOrders)),
            ]
        )->table($this->tableStructure());
    }
}
