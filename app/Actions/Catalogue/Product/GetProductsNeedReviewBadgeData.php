<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Masters\MasterAsset\PropagateMasterContentToProducts;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductsNeedReviewBadgeData
{
    use AsObject;

    public function handle(User $user): array
    {
        $organisationsMap = [];

        foreach ($this->reviewableShops($user) as $shop) {
            $org = $shop->organisation;

            if (!isset($organisationsMap[$org->slug])) {
                $organisationsMap[$org->slug] = [
                    'organisation' => [
                        'slug' => $org->slug,
                        'name' => $org->name,
                        'code' => $org->code,
                    ],
                    'shops' => [],
                ];
            }

            $organisationsMap[$org->slug]['shops'][] = [
                'slug'                 => $shop->slug,
                'name'                 => $shop->name,
                'code'                 => $shop->code,
                'needs_review_items'   => [
                    'count' => $this->query($shop)->count(),
                    'route' => [
                        'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                        'parameters' => [
                            'organisation'   => $org->slug,
                            'shop'           => $shop->slug,
                            'index_elements' => ['state' => 'needs_content_review'],
                        ],
                    ],
                ],
            ];
        }

        return array_values($organisationsMap);
    }

    public function totalCount(User $user): int
    {
        $total = 0;

        foreach ($this->reviewableShops($user) as $shop) {
            $total += $this->query($shop)->count();
        }

        return $total;
    }

    /**
     * A master writes in English, so an English shop has nothing to review: its text is copied
     * verbatim and never falls behind. Only the shops that keep their own translation can.
     *
     * @return \Illuminate\Support\Collection<int, Shop>
     */
    private function reviewableShops(User $user)
    {
        return $user->authorisedShops()
            ->with(['organisation', 'language'])
            ->whereRelation('language', 'code', '!=', 'en')
            ->get()
            ->filter(fn (Shop $shop) => $user->authTo("products.{$shop->id}.view"));
    }

    /**
     * Only an explicit false counts. Null is the state of every product that predates the
     * badge, and counting it would open the badge on tens of thousands of rows nobody will
     * ever work through.
     *
     * Shared by the badge count and by the products index element filter, so the two can
     * never report a different set.
     */
    public function applyReviewConstraints(Builder|QueryBuilder $query): void
    {
        $query
            ->where('products.is_for_sale', true)
            ->whereNotNull('products.master_product_id')
            ->where(function ($query) {
                foreach (PropagateMasterContentToProducts::REVIEW_FLAGS as $reviewFlag) {
                    $query->orWhere('products.'.$reviewFlag, false);
                }
            });
    }

    private function query(Shop $shop): Builder
    {
        $query = Product::where('products.shop_id', $shop->id);

        $this->applyReviewConstraints($query);

        return $query;
    }
}
