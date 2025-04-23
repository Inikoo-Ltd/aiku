<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Apr 2025 18:17:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Actions\Utils\Abbreviate;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Models\Helpers\Currency;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

/**
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $category_currency_id
 * @property mixed $name
 * @property mixed $organisation_currency_id
 */
class DashboardInvoiceCategoriesInGroupSalesResource extends JsonResource
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
            // 'refunds' => [
            //     'route_target' => [
            //         'name' => 'grp.accounting.refunds.index',
            //         'parameters' => [
            //             'organisation' => $this->slug,
            //         ],
            //         'key_date_filter' => 'between[date]',
            //     ],
            // ],
            // 'registrations' => [
            //     'route_target' => [
            //         'name' => 'grp.org.overview.customers.index',
            //         'parameters' => [
            //             'organisation' => $this->slug,
            //         ],
            //         'key_date_filter' => 'between[registered_at]',
            //     ],
            // ],
            'invoiceCategories' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoice-categories.show',
                    'parameters' => [
                        'organisation' => $this->organisation_slug,
                        'invoiceCategory' => $this->slug,
                    ],
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->name,
                    ...$routeTargets['invoiceCategories'],
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => Abbreviate::run($this->name),
                    'tooltip'         => $this->name,
                    ...$routeTargets['invoiceCategories'],
                ]
            ],
            $this->getDashboardTableColumn($this, 'refunds'),
            $this->getDashboardTableColumn($this, 'refunds_minified'),
            $this->getDashboardTableColumn($this, 'refunds_delta'),
            $this->getDashboardTableColumn($this, 'invoices'),
            $this->getDashboardTableColumn($this, 'invoices_minified'),
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
            'state'   => $this->state == InvoiceCategoryStateEnum::ACTIVE ? 'active' : 'inactive',
            'columns' => $columns


        ];
    }
}
