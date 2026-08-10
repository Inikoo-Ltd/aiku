<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Traits\WithPullIntoJsonColumn;
use Lorisleiva\Actions\ActionRequest;

trait WithSupplierProductJsonColumns
{
    use WithPullIntoJsonColumn;

    private const DATA_FIELDS = [
        'minimum_carton_order',
        'delivery_time',
    ];

    private const NULLABLE_NUMERIC_FIELDS = [
        'cbm',
        'extra_costs',
        'minimum_carton_order',
        'delivery_time',
    ];

    /**
     * @param array<string, mixed> $modelData
     *
     * @return array<string, mixed>
     */
    protected function pullSupplierProductJsonColumns(array $modelData): array
    {
        return $this->pullIntoJsonColumn($modelData, 'data', self::DATA_FIELDS);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function supplierProductJsonFieldRules(): array
    {
        return [
            'minimum_carton_order' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'delivery_time'        => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        foreach (self::NULLABLE_NUMERIC_FIELDS as $field) {
            if ($this->has($field) && $this->get($field) === '') {
                $this->set($field, null);
            }
        }
    }
}
