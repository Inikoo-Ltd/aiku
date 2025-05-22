<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 22 May 2025 11:03:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Models\Catalogue\ProductCategory;

trait WithWebpageActions
{
    public function getWebpageActions(ProductCategory $productCategory): array
    {
        return $productCategory->webpage ?
            [
                'type'  => 'button',
                'style' => 'edit',
                'tooltip' => __('Webpage'),
                'icon'  => ["fal", "fa-browser"],
                'route' => [
                    'name'       => 'grp.org.shops.show.web.webpages.show',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $this->shop->slug,
                        'website'      => $this->shop->website->slug,
                        'webpage'      => $productCategory->webpage->slug
                    ]
                ]
            ] :
            [
                'type'  => 'button',
                'style' => 'edit',
                'tooltip' => __('Create Webpage'),
                'label'   => __('Create Webpage'),
                'icon'  => ["fal", "fa-drafting-compass"],
                'route' => [
                    'name'       => 'grp.models.webpages.product_category.store',
                    'parameters' => $productCategory->id,
                    'method'     => 'post'
                ]
            ];
    }
}
