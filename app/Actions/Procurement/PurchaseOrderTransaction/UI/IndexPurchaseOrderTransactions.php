<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 09 May 2023 09:25:51 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexPurchaseOrderTransactions extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    protected function getElementGroups(PurchaseOrder $purchaseOrder): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => collect(PurchaseOrderTransactionStateEnum::cases())->mapWithKeys(
                    fn (PurchaseOrderTransactionStateEnum $state) => [
                        $state->value => [
                            PurchaseOrderTransactionStateEnum::labels()[$state->value],
                            $purchaseOrder->{'number_purchase_order_transactions_state_'.$state->snake()},
                        ],
                    ]
                )->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('purchase_order_transactions.state', $elements);
                },
            ],
        ];
    }

    public function handle(PurchaseOrder $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereHas('supplierProduct', function ($query) use ($value) {
                $query->where('code', 'ILIKE', "%$value%")
                    ->orWhere('name', 'ILIKE', "%$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PurchaseOrderTransaction::class);
        $query->with([
            'supplierProduct.currency',
            'supplierProduct.supplier',
            'orgSupplierProduct.orgSupplier',
            'organisation.currency',
            'orgStock.tradeUnits.image',
        ]);

        $weight = DB::table('model_has_trade_units as mhtu')
            ->join('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->whereColumn('mhtu.model_id', 'purchase_order_transactions.org_stock_id')
            ->where('mhtu.model_type', 'OrgStock')
            ->selectRaw('
                case
                    when count(*) = 0 or count(*) filter (where tu.gross_weight is null) > 0 then null
                    else round(sum(tu.gross_weight * mhtu.quantity) * purchase_order_transactions.quantity_ordered / 1000, 1)
                end
            ');

        $query->leftJoin('supplier_products as sp', 'sp.id', '=', 'purchase_order_transactions.supplier_product_id')
            ->select('purchase_order_transactions.*')
            ->selectSub($weight, 'weight')
            ->selectRaw('round(sp.cbm * purchase_order_transactions.quantity_ordered / nullif(sp.units_per_carton, 0), 2) as volume');

        if ($parent instanceof PurchaseOrder) {
            $query->where('purchase_order_transactions.purchase_order_id', $parent->id);
        }

        if ($parent->state !== PurchaseOrderStateEnum::IN_PROCESS) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $query->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix,
                );
            }
        }

        return $query->allowedSorts([AllowedSort::field('code', 'sp.code')])
            ->defaultSort('purchase_order_transactions.id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(PurchaseOrder $purchaseOrder, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($purchaseOrder, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
                foreach ($this->getElementGroups($purchaseOrder) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements'],
                    );
                }
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false);

            if ($purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS) {
                $table
                    ->column(key: 'description', label: __('Description'), canBeHidden: false)
                    ->column(key: 'subtotals', label: __('Subtotals'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Units'), canBeHidden: false, align: 'right');
            } else {
                $table
                    ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Qty'), canBeHidden: false)
                    ->column(key: 'weight', label: __('Weight'), canBeHidden: false)
                    ->column(key: 'volume', label: __('CBM'), canBeHidden: false)
                    ->column(key: 'amount', label: __('Amount'), canBeHidden: false)
                    ->column(key: 'state', label: __('State'), canBeHidden: false);
            }

            $table->defaultSort('code');
        };
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(LengthAwarePaginator $purchaseOrders): AnonymousResourceCollection
    {
        return PurchaseOrderTransactionResource::collection($purchaseOrders);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Purchase Orders'),
                        'icon'  => 'fal fa-bars',
                        'route' => [
                            'name' => 'grp.org.procurement.purchase_orders.index',
                        ],
                    ]
                ]
            ]
        );
    }
}
