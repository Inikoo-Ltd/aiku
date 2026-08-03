<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Utils\Abbreviate;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class DashboardTopCustomersSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $name      = $data['name'] ?? __('Unknown');
        $reference = $data['reference'] ?? '';

        $routeTarget = !empty($data['organisation_slug']) && !empty($data['shop_slug']) && !empty($data['slug']) ? [
            'route_target' => [
                'name'       => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $data['organisation_slug'],
                    'shop'         => $data['shop_slug'],
                    'customer'     => $data['slug'],
                ],
            ],
        ] : [];

        $columns = [
            'label' => [
                'formatted_value' => $name,
                'align'           => 'left',
                ...$routeTarget,
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($name),
                'tooltip'         => $name,
                'align'           => 'left',
                ...$routeTarget,
            ],
            'reference' => [
                'formatted_value' => $reference,
                'align'           => 'left',
            ],
            'invoices' => [
                'raw_value'       => (int) ($data['invoices'] ?? 0),
                'formatted_value' => Number::format((int) ($data['invoices'] ?? 0)),
            ],
            'last_invoiced_at' => [
                'raw_value'       => $data['last_invoiced_at'] ?? null,
                'formatted_value' => $data['last_invoiced_at'] ?? '—',
            ],
            'sales' => $this->salesColumn(
                (float) ($data['sales'] ?? 0),
                $data['shop_currency_code'] ?? 'GBP'
            ),
            'sales_org_currency' => $this->salesColumn(
                (float) ($data['sales_org_currency'] ?? 0),
                $data['organisation_currency_code'] ?? 'GBP'
            ),
            'sales_grp_currency' => $this->salesColumn(
                (float) ($data['sales_grp_currency'] ?? 0),
                $data['group_currency_code'] ?? 'GBP'
            ),
        ];

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => 'active',
            'columns' => $columns,
        ];
    }

    private function salesColumn(float $value, string $currency): array
    {
        return [
            'raw_value'       => $value,
            'formatted_value' => Number::currency($value, $currency),
        ];
    }
}
