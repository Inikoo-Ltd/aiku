<?php

/*
 * author Arya Permana - Kirin
 * created on 15-11-2024-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Models\SupplyChain\SupplierProduct;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Inventory\OrgStock\GetOrgStocksStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Database\Query\Builder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;

class IndexPurchaseOrderOrgSupplierProducts extends OrgAction
{
    use WithProcurementAuthorisation;
    public function handle(Organisation|OrgSupplier|OrgAgent $parent, ?PurchaseOrder $purchaseOrder, $prefix = null, ?string $agentOrderReference = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('supplier_products.code', $value)
                    ->orWhereStartWith('supplier_products.name', $value)
                    ->orWhereExists(fn ($query) => $query->selectRaw('1')
                        ->from('org_stocks')
                        ->join('stock_has_supplier_products', 'stock_has_supplier_products.stock_id', 'org_stocks.stock_id')
                        ->whereColumn('stock_has_supplier_products.supplier_product_id', 'supplier_products.id')
                        ->whereColumn('org_stocks.organisation_id', 'org_supplier_products.organisation_id')
                        ->where('org_stocks.code', 'ilike', addcslashes($value, '%_\\').'%'));
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $orgId = $purchaseOrder?->organisation_id ?? $parent->organisation_id;

        $orgStockSub = "(select os.id from org_stocks os
            inner join stock_has_supplier_products shsp on shsp.stock_id = os.stock_id
            where shsp.supplier_product_id = supplier_products.id
                and os.organisation_id = {$orgId}
            limit 1)";

        $queryBuilder = QueryBuilder::for(OrgSupplierProduct::class);
        $queryBuilder->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->leftJoin('suppliers', 'supplier_products.supplier_id', 'suppliers.id')
            ->leftJoin('org_suppliers', 'org_suppliers.id', 'org_supplier_products.org_supplier_id')
            ->leftJoin('currencies as supplier_currency', 'supplier_currency.id', 'supplier_products.currency_id')
            ->leftJoin('organisations', 'organisations.id', 'org_supplier_products.organisation_id')
            ->leftJoin('currencies as org_currency', 'org_currency.id', 'organisations.currency_id');

        $queryBuilder->leftJoin('purchase_order_transactions', function ($join) use ($purchaseOrder, $parent, $agentOrderReference) {
            $join->on('purchase_order_transactions.org_supplier_product_id', '=', 'org_supplier_products.id');
            if ($parent instanceof OrgAgent) {
                $join->whereIn('purchase_order_transactions.purchase_order_id', PurchaseOrder::inAgentOrder($parent->organisation_id, $parent->agent_id, (string) $agentOrderReference)
                    ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
                    ->select('id'));
            } else {
                $join->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id);
            }
        });

        if ($parent instanceof OrgAgent) {
            $queryBuilder->where('org_supplier_products.org_agent_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgSupplier') {
            $queryBuilder->where('org_supplier_products.org_supplier_id', $parent->id);
        } else {
            $queryBuilder->where('org_supplier_products.organisation_id', $this->organisation->id);
        }

        $hasDiscontinuingSko = "exists (select 1 from org_stocks os
            inner join stock_has_supplier_products shsp on shsp.stock_id = os.stock_id
            where shsp.supplier_product_id = supplier_products.id
                and os.organisation_id = {$orgId}
                and os.state in ('".OrgStockStateEnum::DISCONTINUING->value."', '".OrgStockStateEnum::DISCONTINUED->value."'))";

        $queryBuilder->where(function ($query) use ($hasDiscontinuingSko) {
            $query->where(fn ($query) => $query->where('org_supplier_products.state', OrgSupplierProductStateEnum::ACTIVE)
                ->where('org_supplier_products.is_available', true)
                ->where('supplier_products.is_available', true)
                ->whereRaw("not $hasDiscontinuingSko"))
                ->orWhereNotNull('purchase_order_transactions.id');
        });

        $paginator = $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'org_supplier_products.id',
                'supplier_products.code',
                'supplier_products.slug',
                'supplier_products.id as supplier_product_id',
                'supplier_products.name',
                DB::raw('coalesce(purchase_order_transactions.unit_cost, supplier_products.cost) as unit_cost'),
                'supplier_products.units_per_pack',
                'supplier_products.units_per_carton',
                'supplier_products.current_historic_supplier_product_id as historic_id',
                'supplier_currency.code as net_currency',
                'org_currency.code as org_currency',
                'purchase_order_transactions.quantity_ordered as quantity_ordered',
                'purchase_order_transactions.net_amount as net_amount',
                'purchase_order_transactions.org_net_amount as org_net_amount',
                'purchase_order_transactions.org_exchange as org_exchange',
                'purchase_order_transactions.id as purchase_order_transaction_id',
                'suppliers.name as supplier_name',
                'org_suppliers.slug as supplier_slug',
            ])
            ->selectRaw("{$orgStockSub} as org_stock_id")
            ->selectRaw($purchaseOrder ? "{$purchaseOrder->id} as purchase_order_id" : 'purchase_order_transactions.purchase_order_id as purchase_order_id')
            ->selectRaw(($purchaseOrder?->org_exchange ?: 1).' as po_org_exchange')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();

        $this->attachOrgStockData($paginator, $purchaseOrder);
        $this->attachOtherOpenPurchaseOrders($paginator, $orgId, $purchaseOrder?->id);

        if ($parent instanceof OrgAgent) {
            $paginator->getCollection()->each(fn ($row) => $row->agent_order = ['org_agent_id' => $parent->id, 'reference' => $agentOrderReference]);
        }

        return $paginator;
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->column(key: 'code', label: __('S. Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'image_thumbnail', label: __('Image'), canBeHidden: false)
                ->column(key: 'description', label: __('Description'), canBeHidden: false)
                ->column(key: 'subtotals', label: __('Subtotals'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, align: 'right')
                ->column(key: 'actions', label: 'Actions', canBeHidden: false, align: 'right')
                ->defaultSort('code');
        };
    }

    public function inOrgSupplier(OrgSupplier $orgSupplier, PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgSupplier->organisation, $request);

        return $this->handle($orgSupplier, $purchaseOrder);
    }

    /**
     * Every product the organisation buys through the agent, for the agent order: a product goes on its
     * supplier's order in that agent order.
     */
    public function inAgentOrder(OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgAgent->organisation, $request);

        return $this->handle($orgAgent, null, agentOrderReference: (string) $request->query('agentOrderReference'));
    }

    public function inOrgPartner(OrgPartner $orgPartner, PurchaseOrder $purchaseOrder, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($orgPartner->organisation, $request);

        return $this->partnerOrgStocks($orgPartner, $purchaseOrder);
    }

    /**
     * Our SKOs the partner sells too, priced at what the partner sells one SKO for.
     */
    public function partnerOrgStocks(OrgPartner $orgPartner, PurchaseOrder $purchaseOrder): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        $pricePerSko       = PartnerSkoPrice::pricePerSkoSql('seller_org_stocks.id', GetPartnerSellingShopIds::run($orgPartner->partner));
        $buyingPricePerSko = GetPartnerLandedCost::appliesTo($orgPartner)
            ? 'coalesce('.GetPartnerLandedCost::perSkoSql('seller_org_stocks.id').", $pricePerSko)"
            : "$pricePerSko * ".GetPartnerBuyingPriceFactor::run($orgPartner);

        $paginator = QueryBuilder::for(OrgStock::class)
            ->join('org_stocks as seller_org_stocks', function ($join) use ($orgPartner) {
                $join->on('seller_org_stocks.stock_id', 'org_stocks.stock_id')
                    ->where('seller_org_stocks.organisation_id', $orgPartner->partner_id)
                    ->where('seller_org_stocks.state', OrgStockStateEnum::ACTIVE->value);
            })
            ->leftJoin('purchase_order_transactions', function ($join) use ($purchaseOrder) {
                $join->on('purchase_order_transactions.org_stock_id', 'org_stocks.id')
                    ->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id)
                    ->whereNull('purchase_order_transactions.deleted_at');
            })
            ->where('org_stocks.organisation_id', $purchaseOrder->organisation_id)
            ->where(function ($query) use ($pricePerSko) {
                $query->whereNotNull('purchase_order_transactions.id')
                    ->orWhere(fn ($query) => $query->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE->value])->whereRaw("$pricePerSko is not null"));
            })
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.id as org_stock_id',
                'org_stocks.packed_in as units_per_pack',
                'purchase_order_transactions.quantity_ordered',
                'purchase_order_transactions.net_amount',
                'purchase_order_transactions.org_net_amount',
                'purchase_order_transactions.org_exchange',
                'purchase_order_transactions.id as purchase_order_transaction_id',
            ])
            ->selectRaw("coalesce(purchase_order_transactions.unit_cost, $buyingPricePerSko / nullif(seller_org_stocks.packed_in, 0)) as unit_cost")
            ->selectRaw('null as units_per_carton')
            ->selectRaw('true as is_partner_org_stock')
            ->selectRaw('? as supplier_name', [$orgPartner->partner->name])
            ->selectRaw('? as net_currency', [$purchaseOrder->currency->code])
            ->selectRaw('? as org_currency', [$purchaseOrder->organisation->currency->code])
            ->selectRaw("{$purchaseOrder->id} as purchase_order_id")
            ->selectRaw(($purchaseOrder->org_exchange ?: 1).' as po_org_exchange')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();

        $this->attachOrgStockData($paginator, $purchaseOrder);
        $this->attachOtherOpenPurchaseOrders($paginator, $purchaseOrder->organisation_id, $purchaseOrder->id);

        return $paginator;
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return PurchaseOrderOrgSupplierProductsResource::collection($orgSupplierProducts);
    }

    private function attachOrgStockData(LengthAwarePaginator $paginator, ?PurchaseOrder $purchaseOrder): void
    {
        $orgStockIds = $paginator->getCollection()->pluck('org_stock_id')->filter()->unique()->values();

        if ($orgStockIds->isEmpty()) {
            return;
        }

        $orgStocks = OrgStock::with('tradeUnits.image', 'stats', 'stock.stockFamily')->whereIn('id', $orgStockIds)->get()->keyBy('id');
        $supplierProducts    = SupplierProduct::whereIn('id', $paginator->getCollection()->pluck('supplier_product_id')->filter()->unique())->get()->keyBy('id');
        $partnerLeadTimeDays = $purchaseOrder?->parent instanceof OrgPartner ? GetPartnerLeadTime::run($purchaseOrder->parent)['days'] : null;

        $quarterlyUsage  = GetOrgStocksQuarterlyUsage::run($orgStockIds);
        $stockDeliveries = GetOrgStocksStockDeliveries::run($orgStockIds);

        $paginator->getCollection()->transform(function ($row) use ($orgStocks, $quarterlyUsage, $stockDeliveries, $supplierProducts, $partnerLeadTimeDays) {
            $orgStock  = $orgStocks->get($row->org_stock_id);
            $tradeUnit = $orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

            $row->image_sources      = $tradeUnit?->imageSources(64, 64);
            $row->stock_in_locations = $orgStock?->quantity_in_locations;
            $row->quarterly_usage    = $quarterlyUsage->get($row->org_stock_id) ?? collect();
            $row->stock_cover        = GetOrgStockBuyingSignals::run($orgStock, $supplierProducts->get($row->supplier_product_id ?? null), $partnerLeadTimeDays);
            $row->stock_deliveries   = $stockDeliveries->get($row->org_stock_id);

            return $row;
        });
    }

    public function attachOtherOpenPurchaseOrders(LengthAwarePaginator $paginator, int $organisationId, ?int $exceptPurchaseOrderId = null): void
    {
        $rows               = $paginator->getCollection();
        $supplierProductIds = $rows->pluck('supplier_product_id')->filter()->unique()->values();
        $orgStockIds        = $rows->pluck('org_stock_id')->filter()->unique()->values();

        if ($supplierProductIds->isEmpty() && $orgStockIds->isEmpty()) {
            return;
        }

        $openPurchaseOrderLines = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->where(function ($query) use ($supplierProductIds, $orgStockIds) {
                $query->whereIn('purchase_order_transactions.supplier_product_id', $supplierProductIds)
                    ->orWhereIn('purchase_order_transactions.org_stock_id', $orgStockIds);
            })
            ->where('purchase_orders.organisation_id', $organisationId)
            ->when($exceptPurchaseOrderId, fn ($query) => $query->where('purchase_orders.id', '!=', $exceptPurchaseOrderId))
            ->whereIn('purchase_orders.state', [
                PurchaseOrderStateEnum::SUBMITTED->value,
                PurchaseOrderStateEnum::CONFIRMED->value,
            ])
            ->whereNull('purchase_orders.deleted_at')
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNotExists(
                fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::deliveryItemsOfTransaction($items)
                    ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.stock_delivery_id', 'stock_delivery_items.stock_delivery_id')
                    ->whereColumn('purchase_order_stock_delivery.purchase_order_id', 'purchase_order_transactions.purchase_order_id')
                    ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value])
            )
            ->orderBy('purchase_orders.id')
            ->select([
                'purchase_order_transactions.supplier_product_id',
                'purchase_order_transactions.org_stock_id',
                'purchase_orders.slug',
                'purchase_orders.reference',
                'purchase_orders.state',
                'purchase_order_transactions.quantity_ordered',
            ])
            ->get();

        $rows->transform(function ($row) use ($openPurchaseOrderLines) {
            $row->other_open_purchase_orders = $openPurchaseOrderLines
                ->filter(fn ($line) => ($row->supplier_product_id && $line->supplier_product_id == $row->supplier_product_id)
                    || ($row->org_stock_id && $line->org_stock_id == $row->org_stock_id))
                ->groupBy('slug')
                ->map(fn ($lines) => [
                    'slug'             => $lines->first()->slug,
                    'reference'        => $lines->first()->reference,
                    'state'            => $lines->first()->state,
                    'quantity_ordered' => (float) $lines->sum('quantity_ordered'),
                ])
                ->values();

            return $row;
        });
    }
}
