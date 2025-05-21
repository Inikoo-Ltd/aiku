<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\Comms\PostRoom\UI\ShowPostRoom;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\UI\Marketing\MarketingHub;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait HasUIMailshots
{
    public function getBreadcrumbs(string $routeName, array $routeParameters, Group|Outbox|PostRoom|Organisation|Shop $parent, $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) use ($routeName, $suffix) {
            return [
                $routeName => [
                    'route'           => $routeName,
                    'routeParameters' => $routeParameters,
                    'modelLabel'      => [
                        'label' => __('mailshot')
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.overview.comms-marketing.marketing-mailshots.index',
            'grp.overview.comms-marketing.invite-mailshots.index',
            'grp.overview.comms-marketing.abandoned-cart-mailshots.index',
            'grp.overview.comms-marketing.newsletters.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Newsletter'),
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                ]
            ),
            'grp.org.shops.show.marketing.mailshots.index' =>
            array_merge(
                MarketingHub::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.mailshots.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Mailshots'),
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                ]
            ),
            'grp.org.shops.show.marketing.newsletters.index' =>
            array_merge(
                MarketingHub::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.newsletters.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Newsletter'),
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                ]
            ),
            'mail.post_rooms.show.mailshots.show' =>
            array_merge(
                ShowPostRoom::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb([$parent->slug])
            ),
            default => []
        };
    }
}
