<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 19 May 2026 16:19:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsApp\UI;

use App\Actions\UI\Marketing\MarketingHub;
use App\Models\Catalogue\Shop;

trait HasUIWhatsAppMarketing
{
    public function getBreadcrumbs(string $routeName, array $routeParameters, Shop $shop = null): array
    {
        return array_merge(
            MarketingHub::make()->getBreadcrumbs('grp.org.shops.show.marketing.whatsapp.index', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.whatsapp.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('WhatsApp Marketing'),
                        'icon'  => 'fab fa-whatsapp'
                    ],
                ],
            ]
        );
    }
}
