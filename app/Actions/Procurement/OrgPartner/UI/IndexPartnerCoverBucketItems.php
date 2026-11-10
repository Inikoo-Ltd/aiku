<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Inventory\OrgStock\GetOrgStocksStockDeliveries;
use App\Actions\Procurement\PartnerShoppingListItem\RoundPartnerQuantityToBatches;
use App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPartnerCoverBucketItems extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPartnerShoppingSubNavigation;

    private OrgPartner $orgPartner;
    private string $bucket;
    private ?string $rank;
    private int $perPage = 25;

    public const array PER_PAGE_OPTIONS = [25, 100, 500];

    public function handle(OrgPartner $orgPartner, string $bucket, ?string $rank): LengthAwarePaginator
    {
        $stockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($orgPartner, $bucket, $rank);

        $paginator = OrgStock::query()
            ->where('organisation_id', $orgPartner->partner_id)
            ->whereIn('stock_id', $stockIds)
            ->whereRaw('not '.PartnerSkoPrice::offLimitsToPartnerSql('org_stocks.id', $orgPartner))
            ->select(['id', 'slug', 'code', 'name', 'stock_id', 'quantity_available'])
            ->when(
                $stockIds,
                fn ($query) => $query->orderByRaw(
                    'array_position(array['.implode(',', array_map(intval(...), $stockIds)).']::bigint[], stock_id)'
                )
            )
            ->paginate($this->perPage)
            ->withQueryString();

        return $this->hydrate($paginator);
    }

    private function hydrate(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $sellerOrgStockIds = collect($paginator->items())->pluck('id');
        $stockIds          = collect($paginator->items())->pluck('stock_id');

        $products = DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->whereIn('product_has_org_stocks.org_stock_id', $sellerOrgStockIds)
            ->tap(fn ($query) => PartnerSkoPrice::scopeToPricingProducts($query, GetPartnerSellingShopIds::run($this->orgPartner->partner), $this->orgPartner->id))
            ->select([
                'product_has_org_stocks.org_stock_id',
                'products.web_images',
                'products.price',
                'product_has_org_stocks.quantity',
            ])
            ->get()
            ->groupBy('org_stock_id')
            ->map->first();

        $buyerOrgStocks = OrgStock::with(['stats', 'stock.stockFamily'])
            ->where('organisation_id', $this->orgPartner->organisation_id)
            ->whereIn('stock_id', $stockIds)
            ->get()
            ->keyBy('stock_id');

        $openItems = PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::DRAFT)
            ->whereIn('stock_id', $stockIds)
            ->get()
            ->keyBy('stock_id');

        $sentQuantities = DB::table('partner_shopping_list_items')
            ->leftJoin('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->leftJoin('orders', 'orders.id', 'transactions.order_id')
            ->where('partner_shopping_list_items.org_partner_id', $this->orgPartner->id)
            ->whereIn('partner_shopping_list_items.state', [ShoppingListItemStateEnum::OPEN->value, ShoppingListItemStateEnum::ORDERED->value])
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->whereIn('partner_shopping_list_items.stock_id', $stockIds)
            ->where(fn ($query) => $query->whereNull('orders.state')->orWhereNotIn('orders.state', ['dispatched', 'cancelled']))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('delivery_note_order')
                ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_order.delivery_note_id')
                ->whereColumn('delivery_note_order.order_id', 'orders.id')
                ->where('delivery_notes.state', 'dispatched')
                ->whereNull('delivery_notes.deleted_at'))
            ->groupBy('partner_shopping_list_items.stock_id')
            ->selectRaw('partner_shopping_list_items.stock_id, sum(partner_shopping_list_items.quantity) as total')
            ->pluck('total', 'stock_id');

        $priceFactor = GetPartnerBuyingPriceFactor::run($this->orgPartner);
        $landedCosts = GetPartnerLandedCost::appliesTo($this->orgPartner) ? GetPartnerLandedCost::run($sellerOrgStockIds->all()) : [];
        $quanta      = RoundPartnerQuantityToBatches::make()->quanta($this->orgPartner, $sellerOrgStockIds->all());

        $buyerOrgStockIds = $buyerOrgStocks->pluck('id')->values();
        $quarterlyUsage   = $buyerOrgStockIds->isEmpty() ? collect() : GetOrgStocksQuarterlyUsage::run($buyerOrgStockIds);
        $stockDeliveries  = $buyerOrgStockIds->isEmpty() ? collect() : GetOrgStocksStockDeliveries::run($buyerOrgStockIds);
        $leadTimeDays     = GetPartnerLeadTime::run($this->orgPartner)['days'];

        $paginator->getCollection()->transform(function (OrgStock $orgStock) use ($products, $buyerOrgStocks, $openItems, $sentQuantities, $priceFactor, $landedCosts, $quanta, $quarterlyUsage, $stockDeliveries, $leadTimeDays) {
            $quantum       = $quanta[$orgStock->id] ?? 1;
            $product       = $products->get($orgStock->id);
            $webImages     = $product ? json_decode($product->web_images ?? 'null', true) : null;
            $buyerOrgStock = $buyerOrgStocks->get($orgStock->stock_id);
            $openItem      = $openItems->get($orgStock->stock_id);
            $unitsPerSko   = $product && (float) $product->quantity > 0 ? (float) $product->quantity : null;
            $packedIn      = (float) ($buyerOrgStock?->packed_in ?: 1);

            return (object) [
                'id'                    => $orgStock->id,
                'slug'                  => $orgStock->slug,
                'code'                  => $orgStock->code,
                'name'                  => $orgStock->name,
                'image'                 => Arr::get($webImages, 'main.gallery') ?? Arr::get($webImages, 'main.thumbnail'),
                'their_stock'           => (float) $orgStock->quantity_available,
                'our_stock'             => $buyerOrgStock ? (float) $buyerOrgStock->quantity_available : null,
                'our_days_of_cover'     => $buyerOrgStock?->stats?->days_of_cover !== null
                    ? (int) $buyerOrgStock->stats->days_of_cover
                    : null,
                'recommended_quantity'  => $buyerOrgStock?->stats?->recommended_order_quantity !== null
                    ? (int) RoundPartnerQuantityToBatches::roundUp(ceil((float) $buyerOrgStock->stats->recommended_order_quantity), $quantum)
                    : null,
                'order_quantum'         => $quantum,
                'price'                 => isset($landedCosts[$orgStock->id]) && (float) $orgStock->packed_in > 0
                    ? round($landedCosts[$orgStock->id] * $this->orgPartner->exchangeToOrgCurrency() / (float) $orgStock->packed_in, 4)
                    : ($product && $product->price !== null && $unitsPerSko
                    ? round((float) $product->price * $priceFactor * $this->orgPartner->exchangeToOrgCurrency() / $unitsPerSko, 4)
                    : null),
                'shopping_list_item_id' => $openItem?->id,
                'ordered_quantity'      => $openItem ? (float) $openItem->quantity : 0,
                'sent_quantity'         => (float) ($sentQuantities[$orgStock->stock_id] ?? 0),
                'org_stock_id'             => $buyerOrgStock?->id,
                'supplier_product_id'      => null,
                'stock_in_locations'       => $buyerOrgStock?->quantity_in_locations === null ? null : trimDecimalZeros($buyerOrgStock->quantity_in_locations),
                'stock_available'          => $buyerOrgStock?->quantity_available === null ? null : trimDecimalZeros(max(0, $buyerOrgStock->quantity_available)),
                'stock_cover'              => $buyerOrgStock ? GetOrgStockBuyingSignals::run($buyerOrgStock, null, $leadTimeDays) : null,
                'quarterly_usage'          => $buyerOrgStock ? ($quarterlyUsage->get($buyerOrgStock->id) ?? collect()) : collect(),
                'stock_deliveries'         => $buyerOrgStock ? $stockDeliveries->get($buyerOrgStock->id) : null,
                'units_per_pack'           => $packedIn,
                'quantity_ordered'         => ($openItem ? (float) $openItem->quantity : 0) * $packedIn,
                'partner_units_per_carton' => (float) $quantum * $packedIn,
                'whole_cartons_only'       => true,
            ];
        });

        IndexPurchaseOrderOrgSupplierProducts::make()->attachOtherOpenPurchaseOrders($paginator, $this->orgPartner->organisation_id);

        $partnerName = $this->orgPartner->partner->name;
        $paginator->getCollection()->transform(function (object $row) use ($partnerName) {
            if ($row->sent_quantity > 0) {
                $row->other_open_purchase_orders = collect($row->other_open_purchase_orders ?? [])->push([
                    'slug'             => null,
                    'reference'        => __('from :partner', ['partner' => $partnerName]),
                    'state'            => 'sent',
                    'quantity_ordered' => $row->sent_quantity * $row->units_per_pack,
                ])->all();
            }

            return $row;
        });

        return $paginator;
    }

    private function bucketLabel(): string
    {
        $meta = GetPartnerStockCoverBuckets::BUCKETS[$this->bucket];
        $edges = ['w2' => 2, 'w3' => 3, 'w4' => 4];

        return __($meta['label'], ['days' => ($edges[$this->bucket] ?? 1) * GetPartnerLeadTime::run($this->orgPartner)['days']]);
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->orgPartner = $orgPartner;
        $cover            = $request->input('cover', 'out');
        $this->bucket     = array_key_exists($cover, GetPartnerStockCoverBuckets::BUCKETS) ? $cover : 'out';
        $rank             = $request->input('rank');
        $this->rank       = in_array($rank, ['A', 'B', 'C', 'D', 'Z'], true) ? $rank : null;
        $perPage          = (int) $request->input('per_page', 25);
        $this->perPage    = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;

        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->bucket, $this->rank);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/PartnerCoverBucketItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => $this->bucketLabel(),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => $this->bucketLabel(),
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => $this->bucketLabel().($this->rank ? ' · '.$this->rank : ''),
                    'subNavigation' => $this->getPartnerShoppingNavigation($this->orgPartner),
                ],
                'orgPartner'  => [
                    'id'       => $this->orgPartner->id,
                    'slug'     => $this->orgPartner->partner->slug,
                    'currency' => $this->orgPartner->organisation->currency->code,
                ],
                'addRoute'    => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.store',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'bucket'      => $this->bucket,
                'bucketLabel' => $this->bucketLabel(),
                'rank'        => $this->rank,
                'perPage'     => $this->perPage,
                'perPageOptions' => self::PER_PAGE_OPTIONS,
                'items'       => $items,
            ]
        );
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowPartnerShoppingDashboard::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.shopping.items.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $this->bucketLabel(),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
