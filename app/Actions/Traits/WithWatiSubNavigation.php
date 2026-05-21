<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

trait WithWatiSubNavigation
{
    public function getWatiSubNavigation(Shop $shop, ActionRequest $request): array
    {
        return [
            [
                'label'    => __('Dashboard'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.dashboard',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-chart-line',
                    'tooltip' => __('Dashboard'),
                ],
            ],
            [
                'label'    => __('Contacts'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.contacts.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-address-card',
                    'tooltip' => __('Contacts'),
                ],
            ],
            [
                'label'    => __('Broadcast'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.broadcast.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-bullhorn',
                    'tooltip' => __('Broadcast'),
                ],
            ],
            [
                'label'    => __('Live Inbox'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.live_inbox.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-inbox-in',
                    'tooltip' => __('Live Inbox'),
                ],
            ],
            [
                'label'    => __('Templates'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.templates.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-file-alt',
                    'tooltip' => __('Templates'),
                ],
            ],
            [
                'label'    => __('Analytics'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.analytics.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-analytics',
                    'tooltip' => __('Analytics'),
                ],
            ],
            [
                'label'    => __('Settings'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.wati.settings',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
                'leftIcon' => [
                    'icon'    => 'fal fa-cog',
                    'tooltip' => __('Settings'),
                ],
            ],
        ];
    }
}
