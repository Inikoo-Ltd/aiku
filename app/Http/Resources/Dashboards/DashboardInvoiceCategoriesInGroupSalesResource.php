<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Apr 2025 18:17:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInvoiceCategoriesInGroupSalesResource extends JsonResource
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

        $organisationCode = $data['organisation_code'] ?? '';
        $name = $data['name'] ?? 'Unknown';
        $label = $organisationCode ? $organisationCode . ': ' . $name : $name;

        $columns = [
            'label' => [
                'formatted_value' => $label,
                'align'           => 'left',
                ...$routeTargets['invoiceCategories']
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($name),
                'tooltip'         => $name,
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
                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => ($data['state'] ?? InvoiceCategoryStateEnum::ACTIVE->value) == InvoiceCategoryStateEnum::ACTIVE->value ? 'active' : 'inactive',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? '',
        ];
    }
}
