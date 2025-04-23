<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Mar 2025 14:51:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Models\Helpers\Currency;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

/**
 * @property mixed $name
 * @property mixed $code
 * @property mixed $organisation_currency_id
 */
class DashboardOrganisationSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;
    protected string $organisationCurrencyCode;


    public function toArray($request): array
    {

        $currencyOrganisationId = $this->organisation_currency_id;


        $organisationCurrencyCode = Cache::remember('currency_code_'.$currencyOrganisationId, 3600 * 24 * 30, function () use ($currencyOrganisationId) {
            return Currency::find($currencyOrganisationId)->code;
        });
        $this->organisationCurrencyCode = $organisationCurrencyCode;

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'organisations' => [
                'route_target' => [
                    'name' => 'grp.org.dashboard.show',
                    'parameters' => [
                        'organisation' => $this->slug,
                    ],
                ],
            ],
        ];


        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->name,
                    'align'           => 'left',
                    ...$routeTargets['organisations']

                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => $this->code,
                    'tooltip'         => $this->name,
                    'align'           => 'left',
                    ...$routeTargets['organisations']
                ]
            ],
            $this->getDashboardTableColumn($this, 'baskets_created_org_currency'),
            $this->getDashboardTableColumn($this, 'baskets_created_org_currency_minified'),
            $this->getDashboardTableColumn($this, 'baskets_created_grp_currency'),
            $this->getDashboardTableColumn($this, 'baskets_created_grp_currency_minified'),
            $this->getDashboardTableColumn($this, 'registrations', $routeTargets['registrations']),
            $this->getDashboardTableColumn($this, 'registrations_minified', $routeTargets['registrations']),
            $this->getDashboardTableColumn($this, 'registrations_delta'),
            $this->getDashboardTableColumn($this, 'invoices', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this, 'invoices_minified', $routeTargets['invoices']),
            $this->getDashboardTableColumn($this, 'invoices_delta'),
            $this->getDashboardTableColumn($this, 'sales_org_currency'),
            $this->getDashboardTableColumn($this, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_org_currency_delta'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_grp_currency_delta'),
        );


        return [
            'slug'    => $this->slug,
            'state'   => 'active',
            'columns' => $columns


        ];
    }
}
