<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 12:41:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Web\Website;

trait WithWebsiteWorkshop
{
    public function getActions(Website $website, $publishRoute): array
    {
        return [
            [
                'type'  => 'button',
                'style' => 'exit',
                'label' => __('Exit workshop'),
                'route' => ($website->shop->type === ShopTypeEnum::FULFILMENT)
                    ? [
                        'name'       => 'grp.org.fulfilments.show.web.websites.workshop',
                        'parameters' => [
                            'organisation' => $website->organisation,
                            'fulfilment'   => $website->shop->slug,
                            'website'      => $website
                        ],
                    ]
                    : [
                        'name'       => 'grp.org.shops.show.web.websites.workshop',
                        'parameters' => [
                            'organisation' => $website->organisation->slug,
                            'shop'         => $website->shop->slug,
                            'website'      => $website->slug
                        ],
                    ]
            ],
            [
                'type'  => 'button',
                'style' => 'primary',
                'icon'  => ["fas", "fa-rocket"],
                'label' => __('Publish'),
                'route' => [
                    'method'     => 'post',
                    'name'       => $publishRoute,
                    'parameters' => [
                        'website' => $website->id
                    ],
                ]
            ],
        ];
    }
}
