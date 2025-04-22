<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:08:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Helpers\Currency;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $code
 * @property mixed $shop_currency_id
 * @property mixed $organisation_currency_id
 * @property mixed $slug
 * @property mixed $state
 */
class DashboardShopSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;

    protected string $shopCurrencyCode;
    protected string $organisationCurrencyCode;


    public function toArray($request): array
    {
        $currencyShopId = $this->shop_currency_id;
        $shopCurrencyCode = Cache::remember('currency_code_'.$currencyShopId, 3600 * 24 * 30, function () use ($currencyShopId) {
            return Currency::find($currencyShopId)->code;
        });
        $this->shopCurrencyCode = $shopCurrencyCode;


        $currencyOrganisationId = $this->organisation_currency_id;
        $organisationCurrencyCode = Cache::remember('currency_code_'.$currencyOrganisationId, 3600 * 24 * 30, function () use ($currencyOrganisationId) {
            return Currency::find($currencyOrganisationId)->code;
        });
        $this->organisationCurrencyCode = $organisationCurrencyCode;

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.helpers.redirect_invoices_from_dashboard',
                    'parameters' => [
                        'shop' => $this->id,
                    ],
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.helpers.redirect_customers_from_dashboard',
                    'parameters' => [
                        'shop' => $this->id,
                    ],
                ],
            ],
            'shops' => [
                'route_target' => [
                    'name' => 'grp.helpers.redirect_shops_from_dashboard',
                    'parameters' => [
                        'shop' => $this->id,
                    ],
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->name,
                    'align'           => 'left',
                    ...$routeTargets['shops']
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $this->code,
                    'tooltip'         => $this->name,
                    'align'           => 'left',
                    ...$routeTargets['shops']
                ]
            ],
            $this->getDashboardTableColumn($this, 'baskets_created_shop_currency'),
            $this->getDashboardTableColumn($this, 'baskets_created_shop_currency_minified'),
            $this->getDashboardTableColumn($this, 'baskets_created_shop_currency_delta'),
            $this->getDashboardTableColumn($this, 'baskets_created_org_currency'),
            $this->getDashboardTableColumn($this, 'baskets_created_org_currency_minified'),
            $this->getDashboardTableColumn($this, 'baskets_created_org_currency_delta'),
            $this->getDashboardTableColumn($this, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this, 'invoices_delta'),
            $this->getDashboardTableColumn($this, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($this, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($this, 'registrations_delta'),
            $this->getDashboardTableColumn($this, 'sales_shop_currency'),
            $this->getDashboardTableColumn($this, 'sales_shop_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_shop_currency_delta'),
            $this->getDashboardTableColumn($this, 'sales_org_currency'),
            $this->getDashboardTableColumn($this, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_org_currency_delta'),
        );


        return [
            'slug'    => $this->slug,
            'state'   => $this->state == ShopStateEnum::OPEN ? 'active' : 'inactive',
            'columns' => $columns


        ];
    }
}
