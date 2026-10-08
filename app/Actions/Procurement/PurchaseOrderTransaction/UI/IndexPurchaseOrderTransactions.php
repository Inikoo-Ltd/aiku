<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 09 May 2023 09:25:51 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction\UI;

use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\Models\Procurement\OrgPartner;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Inventory\OrgStock\GetOrgStocksStockDeliveries;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

class IndexPurchaseOrderTransactions extends OrgAction
{
    use WithProcurementAuthorisation;
    protected function showDeliveryState(PurchaseOrder $purchaseOrder): bool
    {
        return in_array($purchaseOrder->state, [PurchaseOrderStateEnum::CONFIRMED, PurchaseOrderStateEnum::SETTLED], true)
            && $purchaseOrder->stockDeliveries()->exists();
    }

    protected function getElementGroups(PurchaseOrder $purchaseOrder): array
    {
        $elementGroups = [
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

        if ($this->showDeliveryState($purchaseOrder)) {
            $elementGroups['delivery_state'] = [
                'label'    => __('Delivery State'),
                'elements' => collect(PurchaseOrderTransactionDeliveryStateEnum::cases())->mapWithKeys(
                    fn (PurchaseOrderTransactionDeliveryStateEnum $deliveryState) => [
                        $deliveryState->value => [
                            PurchaseOrderTransactionDeliveryStateEnum::labels()[$deliveryState->value],
                            $purchaseOrder->{'number_purchase_orders_transactions_delivery_state_'.$deliveryState->snake()},
                        ],
                    ]
                )->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('purchase_order_transactions.delivery_state', $elements);
                },
            ];
        }

        return $elementGroups;
    }

    public function handle(PurchaseOrder $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereHas('supplierProduct', function ($query) use ($value) {
                    $query->where('code', 'ILIKE', "%$value%")
                        ->orWhere('name', 'ILIKE', "%$value%");
                })->orWhereHas('orgStock', function ($query) use ($value) {
                    $query->where('code', 'ILIKE', "%$value%")
                        ->orWhere('name', 'ILIKE', "%$value%");
                });
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
            'orgStock.stats',
            'orgStock.stock.stockFamily',
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
            ->leftJoin('org_stocks as os', 'os.id', '=', 'purchase_order_transactions.org_stock_id')
            ->select('purchase_order_transactions.*')
            ->selectSub($weight, 'weight')
            ->selectRaw('round(sp.cbm * purchase_order_transactions.quantity_ordered / nullif(sp.units_per_carton, 0), 2) as volume')
            ->selectSub(
                StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction(DB::query())->selectRaw('count(*) > 0'),
                'is_on_delivery'
            );

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

        $paginator = $query->allowedSorts([
            AllowedSort::custom('code', new class () implements Sort {
                public function __invoke(Builder $query, bool $descending, string $property): void
                {
                    $query->orderByRaw('coalesce(sp.code, os.code) '.($descending ? 'desc' : 'asc'));
                }
            }),
        ])
            ->defaultSort('purchase_order_transactions.id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $orgStockIds     = $paginator->getCollection()->pluck('org_stock_id')->filter()->unique()->values();
        $quarterlyUsage  = GetOrgStocksQuarterlyUsage::run($orgStockIds);
        $stockDeliveries = GetOrgStocksStockDeliveries::run($orgStockIds);
        $partnerLeadTimeDays = $parent->parent instanceof OrgPartner ? GetPartnerLeadTime::run($parent->parent)['days'] : null;
        $partnerStocks       = $parent->parent instanceof OrgPartner ? $this->partnerStocks($parent->parent, $orgStockIds) : collect();
        $paginator->getCollection()->each(
            fn (PurchaseOrderTransaction $transaction) => $transaction
                ->setAttribute('quarterly_usage', $quarterlyUsage->get($transaction->org_stock_id) ?? collect())
                ->setAttribute('stock_deliveries', $stockDeliveries->get($transaction->org_stock_id))
                ->setAttribute('buying_signals', GetOrgStockBuyingSignals::run($transaction->orgStock, $transaction->supplierProduct, $partnerLeadTimeDays))
                ->setAttribute('partner_stock', $partnerStocks->get($transaction->org_stock_id))
        );
        IndexPurchaseOrderOrgSupplierProducts::make()->attachOtherOpenPurchaseOrders($paginator, $parent->organisation_id, $parent->id);

        return $paginator;
    }

    /**
     * The partner's own stock of each of our SKOs, and the carton the partner buys it in from its
     * primary supplier. Cartons differ between organisations and are often wrong, so they are a guide only.
     *
     * @return Collection<int, object{stock: float|null, units_per_carton: int|null, hub_name: string|null}>
     */
    private function partnerStocks(OrgPartner $orgPartner, Collection $orgStockIds): Collection
    {
        if ($orgStockIds->isEmpty()) {
            return collect();
        }

        $primarySupplierProduct = DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->join('supplier_products as sp', 'sp.id', 'osp.supplier_product_id')
            ->whereColumn('link.org_stock_id', 'seller.id')
            ->where('link.status', true)
            ->orderByDesc('link.local_priority')
            ->select('sp.units_per_carton')
            ->limit(1);

        return DB::table('org_stocks as buyer')
            ->join('org_stocks as seller', function ($join) use ($orgPartner) {
                $join->on('seller.stock_id', 'buyer.stock_id')
                    ->where('seller.organisation_id', $orgPartner->partner_id);
            })
            ->leftJoinLateral($primarySupplierProduct, 'primary_sp')
            ->whereIn('buyer.id', $orgStockIds)
            ->select(['buyer.id as org_stock_id', 'primary_sp.units_per_carton'])
            ->selectRaw('seller.quantity_in_locations * seller.packed_in / nullif(buyer.packed_in, 0) as stock')
            ->selectRaw(GetPartnerStockCoverBuckets::hubNameSql('buyer.stock_id').' as hub_name')
            ->get()
            ->keyBy('org_stock_id');
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
                ->column(key: 'state_icon', label: ['fal', 'fa-clipboard-list'], canBeHidden: false, type: 'icon');

            if ($this->showDeliveryState($purchaseOrder)) {
                $table->column(key: 'delivery_state', label: ['fal', 'fa-people-arrows'], canBeHidden: false, type: 'icon');
            }

            $table
                ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false);

            if ($purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS) {
                $table
                    ->column(key: 'description', label: __('Description'), canBeHidden: false)
                    ->column(key: 'subtotals', label: __('Subtotals'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Units'), canBeHidden: false, align: 'right')
                    ->column(key: 'actions', label: 'Actions', canBeHidden: false, align: 'right');
            } else {
                $table
                    ->column(key: 'description', label: __('Unit description'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Qty'), canBeHidden: false)
                    ->column(key: 'weight', label: __('Weight'), canBeHidden: false)
                    ->column(key: 'volume', label: __('CBM'), canBeHidden: false)
                    ->column(key: 'amount', label: __('Amount'), canBeHidden: false);

                if (in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)) {
                    $table->column(key: 'actions', label: __('Actions'), canBeHidden: false, align: 'right');
                }
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
