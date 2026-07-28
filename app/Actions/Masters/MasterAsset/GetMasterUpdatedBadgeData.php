<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterUpdatedBadgeData
{
    use AsObject;

    public function handle(User $user): array
    {
        $organisationsMap = [];

        foreach ($user->authorisedShops()->with('organisation')->get() as $shop) {
            if (!$user->authTo("products.{$shop->id}.view")) {
                continue;
            }

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
                'master_updated_items' => [
                    'count' => $this->query($shop)->count(),
                    'route' => [
                        'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                        'parameters' => [
                            'organisation' => $org->slug,
                            'shop'         => $shop->slug,
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

        foreach ($user->authorisedShops()->get() as $shop) {
            if (!$user->authTo("products.{$shop->id}.view")) {
                continue;
            }

            $total += $this->query($shop)->count();
        }

        return $total;
    }

    /**
     * Products that opted out of master pricing and whose price or RRP no longer
     * matches the master value for the product's own currency code. A drift in
     * either one is enough; a master with no entry for that currency is skipped.
     */
    private function query(Shop $shop): Builder
    {
        return Product::where('products.shop_id', $shop->id)
            ->where('products.not_follow_master_prices', true)
            ->whereNotNull('products.master_product_id')
            ->join('master_assets', 'master_assets.id', '=', 'products.master_product_id')
            ->join('currencies', 'currencies.id', '=', 'products.currency_id')
            ->where(function (Builder $query) {
                // ponytail: jsonb_exists() not the `?` operator, PDO binds `?` as a placeholder
                $query
                    ->whereRaw("jsonb_exists(master_assets.master_prices, currencies.code)
                        AND products.price <> (master_assets.master_prices #>> ARRAY[currencies.code, 'value'])::numeric")
                    ->orWhereRaw("jsonb_exists(master_assets.master_rrps, currencies.code)
                        AND products.rrp <> (master_assets.master_rrps #>> ARRAY[currencies.code, 'value'])::numeric");
            });
    }
}
