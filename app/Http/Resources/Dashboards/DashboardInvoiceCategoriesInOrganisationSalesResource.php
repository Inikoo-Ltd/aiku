<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Mar 2025 21:39:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInvoiceCategoriesInOrganisationSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $routeTargets = [
            'refunds' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoice-categories.show.refunds.index',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'invoiceCategory' => $data['slug'],
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoice-categories.show.invoices.index',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'invoiceCategory' => $data['slug'],
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'invoiceCategories' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoice-categories.show',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'],
                        'invoiceCategory' => $data['slug'],
                    ],
                ],
            ],
        ];

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['invoiceCategories']
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($data['name'] ?? 'Unknown'),
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['invoiceCategories']
            ]
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'refunds' => $routeTargets['refunds'],
                'refunds_minified' => $routeTargets['refunds'],
                'refunds_inverse_delta',
                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',
                'sales',
                'sales_minified',
                'sales_delta',
                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => ($data['state'] ?? InvoiceCategoryStateEnum::ACTIVE->value) == InvoiceCategoryStateEnum::ACTIVE->value ? 'active' : 'inactive',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? null,
        ];
    }
}
