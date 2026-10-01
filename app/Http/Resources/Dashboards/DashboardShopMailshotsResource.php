<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 01 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardShopMailshotsResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $shopParameters = [
            'organisation' => $data['organisation_slug'],
            'shop'         => $data['slug'],
        ];

        $shopRouteTarget = [
            'route_target' => [
                'name'       => 'grp.majordomo.redirect_shops_from_dashboard',
                'parameters' => ['shop' => $data['id']],
            ],
        ];

        $newslettersRouteTarget = [
            'route_target' => [
                'name'       => 'grp.org.shops.show.marketing.newsletters.index',
                'parameters' => $shopParameters,
            ],
        ];

        $marketingMailshotsRouteTarget = [
            'route_target' => [
                'name'       => 'grp.org.shops.show.marketing.mailshots.index',
                'parameters' => $shopParameters,
            ],
        ];

        $columns = array_merge(
            [
                'label'          => [
                    'formatted_value' => $data['name'],
                    'align'           => 'left',
                    ...$shopRouteTarget,
                ],
                'label_minified' => [
                    'formatted_value' => Abbreviate::run($data['name']),
                    'tooltip'         => $data['name'],
                    'align'           => 'left',
                    ...$shopRouteTarget,
                ],
            ],
            $this->getDashboardColumnsFromArray($data, [
                'newsletters'                  => $newslettersRouteTarget,
                'newsletters_minified'         => $newslettersRouteTarget,
                'marketing_mailshots'          => $marketingMailshotsRouteTarget,
                'marketing_mailshots_minified' => $marketingMailshotsRouteTarget,
                'abandoned_cart_mailshots',
                'abandoned_cart_mailshots_minified',
                'mailshots',
                'mailshots_minified',
                'mailshots_delta',
                'abandoned_cart_reminder_emails',
                'abandoned_cart_reminder_emails_minified',
                'emails_sent',
                'emails_sent_minified',
                'emails_sent_delta',
            ])
        );

        return [
            'slug'    => $data['slug'],
            'state'   => $data['state'] == ShopStateEnum::OPEN->value ? 'active' : 'inactive',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? '',
        ];
    }
}
