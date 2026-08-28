<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Traits\WithPullIntoJsonColumn;

trait WithSupplierJsonColumns
{
    use WithPullIntoJsonColumn;

    private const DATA_FIELDS = [
        'delivery_type',
        'incoterm',
        'port_of_export',
        'port_of_import',
        'production_waiting_time',
        'delivery_time',
    ];

    private const CONTAINER_ONLY_FIELDS = [
        'incoterm',
        'port_of_export',
        'port_of_import',
    ];

    private const SETTINGS_FIELDS = [
        'default_product_allow_on_demand',
        'default_product_country_origin',
        'payment_terms',
        'order_number_prefix',
        'minimum_order',
        'cooling_period',
    ];

    /**
     * @param array<string, mixed> $modelData
     *
     * @return array<string, mixed>
     */
    protected function pullSupplierJsonColumns(array $modelData): array
    {
        $modelData = $this->pullIntoJsonColumn($modelData, 'data', self::DATA_FIELDS);

        return $this->pullIntoJsonColumn($modelData, 'settings', self::SETTINGS_FIELDS);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function supplierJsonFieldRules(): array
    {
        return [
            'delivery_type'                   => ['sometimes', 'nullable', 'string', 'in:parcel,container'],
            'incoterm'                        => ['sometimes', 'nullable', 'string', 'max:8'],
            'port_of_export'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'port_of_import'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'production_waiting_time'         => ['sometimes', 'nullable', 'integer', 'min:0'],
            'delivery_time'                   => ['sometimes', 'nullable', 'integer', 'min:0'],

            'default_product_allow_on_demand' => ['sometimes', 'boolean'],
            'default_product_country_origin'  => ['sometimes', 'nullable', 'exists:countries,id'],
            'payment_terms'                   => ['sometimes', 'nullable', 'string', 'max:255'],
            'order_number_prefix'             => ['sometimes', 'nullable', 'string', 'max:16'],
            'minimum_order'                   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cooling_period'                  => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
