<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
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

    public function handle(OrgPartner $orgPartner, string $bucket, ?string $rank): LengthAwarePaginator
    {
        $stockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($orgPartner, $bucket, $rank);

        $paginator = OrgStock::query()
            ->where('organisation_id', $orgPartner->partner_id)
            ->whereIn('stock_id', $stockIds)
            ->select(['id', 'slug', 'code', 'name', 'stock_id', 'quantity_available'])
            ->when(
                $stockIds,
                fn ($query) => $query->orderByRaw(
                    'array_position(array['.implode(',', array_map(intval(...), $stockIds)).']::bigint[], stock_id)'
                )
            )
            ->paginate(25)
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
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->select([
                'product_has_org_stocks.org_stock_id',
                'products.web_images',
                'products.price',
                'product_has_org_stocks.quantity',
            ])
            ->get()
            ->keyBy('org_stock_id');

        $buyerOrgStocks = OrgStock::with('stats')
            ->where('organisation_id', $this->orgPartner->organisation_id)
            ->whereIn('stock_id', $stockIds)
            ->get()
            ->keyBy('stock_id');

        $openItems = PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereIn('stock_id', $stockIds)
            ->get()
            ->keyBy('stock_id');

        $paginator->getCollection()->transform(function (OrgStock $orgStock) use ($products, $buyerOrgStocks, $openItems) {
            $product       = $products->get($orgStock->id);
            $webImages     = $product ? json_decode($product->web_images ?? 'null', true) : null;
            $buyerOrgStock = $buyerOrgStocks->get($orgStock->stock_id);
            $openItem      = $openItems->get($orgStock->stock_id);
            $unitsPerSko   = $product && (float) $product->quantity > 0 ? (float) $product->quantity : null;

            return [
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
                    ? (int) ceil((float) $buyerOrgStock->stats->recommended_order_quantity)
                    : null,
                'price'                 => $product && $product->price !== null && $unitsPerSko
                    ? round((float) $product->price * $this->orgPartner->exchangeToOrgCurrency() / $unitsPerSko, 4)
                    : null,
                'shopping_list_item_id' => $openItem?->id,
                'ordered_quantity'      => $openItem ? (float) $openItem->quantity : 0,
            ];
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
