<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:33:19 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Search;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Product $product): void
    {
        if ($product->trashed()) {
            $product->universalSearch()->delete();
            return;
        }

        $shop = $product->shop;

        $modelData = [
            'group_id'                    => $product->group_id,
            'organisation_id'             => $product->organisation_id,
            'organisation_slug'           => $product->organisation->slug,
            'shop_id'                     => $product->shop_id,
            'shop_slug'                   => $product->shop->slug,
            'sections'                    => ['catalogue'],
            'haystack_tier_1'             => trim($product->code . ' ' . $product->name),
            'haystack_tier_2'             => $product->description,
            'result'                      => [
                'route'         => [
                    'name'          => 'grp.org.shops.show.catalogue.products.current_products.show',
                    'parameters'    => [
                        $product->organisation->slug,
                        $product->shop->slug,
                        $product->slug,
                    ]
                ],
                'description'     => [
                    'label'   => $product->name,
                ],
                'code'         => [
                    'code' => $product->name
                ],
                'icon'      => [
                    'icon'      => 'fal fa-cube',
                ],
                'meta'      => [
                    [
                        'label'   => $product->state,
                        'tooltip' => __('State'),
                    ],
                    [
                        'type'      => 'date',
                        'label'     => $product->created_at,
                        'tooltip'   => __('Created at')
                    ],
                    [
                        'type'       => 'currency',
                        'label'      => __('Price') . ': ',
                        'code'       => $product->currency->code,
                        'amount'     => $product->price,
                        'tooltip'    => __('Price')
                    ],
                ],
            ]
        ];

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            $modelData['fulfilment_id']     = $shop->fulfilment->id;
            $modelData['fulfilment_slug']   = $shop->fulfilment->slug;
        }


        $product->universalSearch()->updateOrCreate(
            [],
            $modelData
        );
    }

}
