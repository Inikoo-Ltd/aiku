<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Models\Procurement\PartnerShoppingListItem;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Read-only picture of everything that still hangs off an organisation stock, so whoever is about to
 * discontinue it sees the open purchase orders, the customers carrying it on their channels, the
 * marketplace listings, the live web pages and the open customer orders before deciding.
 *
 * Mailshots are reported as unknown: nothing in aiku links a mailshot to the products it features,
 * so the marketing check stays a human one. Backorders are not a separate concept in aiku either,
 * the open customer orders already include everything not yet dispatched.
 */
class GetOrgStockDiscontinuePreview extends OrgAction
{
    /** @var array<string, Organisation|null> */
    private array $organisationCache = [];

    /**
     * @param  Collection<int, OrgStock>  $orgStocks
     * @return array<int, array<string, mixed>>
     */
    public function handle(Collection $orgStocks, ?User $user = null): array
    {
        return $orgStocks->map(fn (OrgStock $orgStock) => $this->previewFor($orgStock, $user))->values()->all();
    }

    private function previewFor(OrgStock $orgStock, ?User $user): array
    {
        $orgStock->loadMissing('stats');
        $productIds = DB::table('product_has_org_stocks')->where('org_stock_id', $orgStock->id)->pluck('product_id')->all();
        $organisations = $this->siblingStates($orgStock);

        return [
            'id'              => $orgStock->id,
            'slug'            => $orgStock->slug,
            'code'            => $orgStock->code,
            'name'            => $orgStock->name,
            'state'           => $orgStock->state->value,
            'state_label'     => $orgStock->state->labels()[$orgStock->state->value],
            'organisation'    => $orgStock->organisation->code,
            'updated_at'      => $orgStock->updated_at?->toIso8601String(),
            'organisations'   => $organisations,
            'quantity'        => (float) $orgStock->quantity_in_locations,
            'days_of_cover'   => $orgStock->stats?->week_of_cover === null ? null : round($orgStock->stats->week_of_cover * 7),
            'number_products' => count($productIds),
            'purchase_orders'  => $this->openPurchaseOrders($orgStock),
            'stock_deliveries' => $this->pendingStockDeliveries($orgStock),
            'restock_requests' => $orgStock->stock_id ? PartnerShoppingListItem::openRestockRequestsFor($orgStock)->count() : 0,
            'portfolios'       => $this->portfolios($productIds),
            'external_shops'   => $this->externalShopProducts($productIds),
            'webpages'         => $this->liveWebpages($productIds),
            'mailshots'        => ['known' => false, 'reason' => __('Mailshots are not linked to products in aiku')],
            'orders'           => $this->openOrders($productIds),
            'is_exclusive'     => $this->isExclusive($productIds),
            'can_change_group' => $user ? DiscontinueOrgStocks::canChangeGroupStatus($user) : false,
            'changeable_organisations' => $user ? $this->changeableOrganisationCodes(array_keys($organisations), $user) : [],
        ];
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function changeableOrganisationCodes(array $codes, User $user): array
    {
        return collect($codes)->filter(function (string $code) use ($user) {
            $organisation = $this->organisationCache[$code] ??= Organisation::where('code', $code)->first();

            return $organisation && DiscontinueOrgStocks::canChangeStatus($user, $organisation);
        })->values()->all();
    }

    private function siblingStates(OrgStock $orgStock): array
    {
        if (!$orgStock->stock_id) {
            return [];
        }

        return DB::table('org_stocks')
            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
            ->where('org_stocks.stock_id', $orgStock->stock_id)
            ->whereNull('org_stocks.deleted_at')
            ->orderBy('organisations.code')
            ->pluck('org_stocks.state', 'organisations.code')
            ->all();
    }

    private function openPurchaseOrders(OrgStock $orgStock): array
    {
        $references = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_transactions.purchase_order_id')
            ->where('purchase_order_transactions.org_stock_id', $orgStock->id)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereIn('purchase_orders.state', [
                PurchaseOrderStateEnum::IN_PROCESS->value,
                PurchaseOrderStateEnum::SUBMITTED->value,
                PurchaseOrderStateEnum::CONFIRMED->value,
            ])
            ->distinct()
            ->orderBy('purchase_orders.reference')
            ->pluck('purchase_orders.reference')
            ->all();

        return ['count' => count($references), 'references' => $references];
    }

    private function pendingStockDeliveries(OrgStock $orgStock): array
    {
        $references = DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', '=', 'stock_delivery_items.stock_delivery_id')
            ->where('stock_delivery_items.org_stock_id', $orgStock->id)
            ->whereIn('stock_deliveries.state', [
                StockDeliveryStateEnum::IN_PROCESS->value,
                StockDeliveryStateEnum::CONFIRMED->value,
                StockDeliveryStateEnum::READY_TO_SHIP->value,
                StockDeliveryStateEnum::DISPATCHED->value,
            ])
            ->distinct()
            ->orderBy('stock_deliveries.reference')
            ->pluck('stock_deliveries.reference')
            ->all();

        return ['count' => count($references), 'references' => $references];
    }

    private function portfolios(array $productIds): array
    {
        if (!$productIds) {
            return ['count' => 0, 'customers' => 0, 'by_platform' => []];
        }

        $query = DB::table('portfolios')
            ->join('platforms', 'platforms.id', '=', 'portfolios.platform_id')
            ->where('portfolios.item_type', 'Product')
            ->whereIn('portfolios.item_id', $productIds)
            ->where('portfolios.status', true);

        return [
            'count'       => (clone $query)->count(),
            'customers'   => (clone $query)->distinct()->count('portfolios.customer_id'),
            'by_platform' => (clone $query)->select('platforms.type', DB::raw('count(*) as count'))
                ->groupBy('platforms.type')->orderBy('platforms.type')->pluck('count', 'platforms.type')->all(),
        ];
    }

    private function externalShopProducts(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        return DB::table('products')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->whereIn('products.id', $productIds)
            ->where('shops.type', ShopTypeEnum::EXTERNAL->value)
            ->orderBy('shops.code')
            ->get(['products.code', 'products.status', 'shops.code as shop_code', 'shops.name as shop_name'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function liveWebpages(array $productIds): array
    {
        if (!$productIds) {
            return ['count' => 0, 'urls' => []];
        }

        $own = DB::table('products')
            ->join('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->whereIn('products.id', $productIds)
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->pluck('webpages.url');

        $featuring = DB::table('webpage_has_products')
            ->join('webpages', 'webpages.id', '=', 'webpage_has_products.webpage_id')
            ->whereIn('webpage_has_products.product_id', $productIds)
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->pluck('webpages.url');

        $urls = $own->merge($featuring)->unique()->sort()->values()->all();

        return ['count' => count($urls), 'urls' => $urls];
    }

    private function openOrders(array $productIds): array
    {
        if (!$productIds) {
            return ['count' => 0, 'quantity' => 0, 'references' => []];
        }

        $rows = DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->where('transactions.model_type', 'Product')
            ->whereIn('transactions.model_id', $productIds)
            ->whereNull('transactions.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.state', [
                OrderStateEnum::SUBMITTED->value,
                OrderStateEnum::IN_WAREHOUSE->value,
                OrderStateEnum::HANDLING->value,
                OrderStateEnum::HANDLING_BLOCKED->value,
                OrderStateEnum::PICKED->value,
                OrderStateEnum::PACKING->value,
                OrderStateEnum::PACKED->value,
                OrderStateEnum::FINALISED->value,
            ])
            ->select('orders.reference', DB::raw('sum(transactions.quantity_ordered) as quantity'))
            ->groupBy('orders.id', 'orders.reference')
            ->orderBy('orders.reference')
            ->get();

        return [
            'count'      => $rows->count(),
            'quantity'   => (float) $rows->sum('quantity'),
            'references' => $rows->pluck('reference')->all(),
        ];
    }

    private function isExclusive(array $productIds): bool
    {
        if (!$productIds) {
            return false;
        }

        return DB::table('products')->whereIn('id', $productIds)->whereNotNull('exclusive_for_customer_id')->exists()
            || DB::table('product_has_exclusive_customers')->whereIn('product_id', $productIds)->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return DiscontinueOrgStocks::canChangeStatus($request->user(), $this->organisation);
    }

    public function rules(): array
    {
        return [
            'org_stock_ids'   => ['required', 'array', 'max:200'],
            'org_stock_ids.*' => ['integer'],
        ];
    }

    private function orgStocksFromIds(array $ids): Collection
    {
        return OrgStock::where('organisation_id', $this->organisation->id)->whereIn('id', $ids)->orderBy('code')->get();
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($this->orgStocksFromIds($this->validatedData['org_stock_ids']), $request->user());
    }

    public function action(Organisation $organisation, array $orgStockIds, ?User $user = null): array
    {
        $this->asAction = true;
        $this->initialisation($organisation, ['org_stock_ids' => $orgStockIds]);

        return $this->handle($this->orgStocksFromIds($this->validatedData['org_stock_ids']), $user);
    }
}
