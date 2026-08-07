<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-16h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
 */

namespace App\Imports\SupplyChain;

use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Imports\WithImport;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Upload;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierProductImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    protected Supplier $scope;

    public function __construct(Supplier $supplier, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope  = $supplier;
    }

    public function storeModel(Collection $row, $uploadRecord): void
    {
        $data = $row->all();

        try {
            $tradeUnit     = $this->resolveTradeUnit(Arr::get($data, 'part_reference'));
            $unitsPerSko   = ((int)Arr::get($data, 'units_per_sko')) ?: 1;
            $skosPerCarton = ((int)Arr::get($data, 'skos_per_carton')) ?: 1;

            $modelData = $this->onlyFilled([
                'code'                 => Arr::get($data, 'suppliers_product_code'),
                'name'                 => Arr::get($data, 'suppliers_unit_description'),
                'cost'                 => Arr::get($data, 'unit_cost'),
                'cbm'                  => Arr::get($data, 'carton_cbm'),
                'extra_costs'          => Arr::get($data, 'unit_extra_costs'),
                'minimum_carton_order' => Arr::get($data, 'minimum_order_cartons'),
                'delivery_time'        => Arr::get($data, 'average_delivery_time_days'),
            ]);

            $modelData['units_per_pack']   = $unitsPerSko;
            $modelData['units_per_carton'] = $unitsPerSko * $skosPerCarton;

            $availability = $this->cleanString(Arr::get($data, 'availability'));
            if ($availability !== null) {
                $modelData['is_available'] = strtolower($availability) === 'available';
            }

            $supplierProduct = $this->storeOrUpdate(Arr::get($data, 'id_supplier_part_key'), $modelData);

            if ($tradeUnit) {
                SyncSupplierProductTradeUnits::run($supplierProduct, [
                    $tradeUnit->id => ['quantity' => $unitsPerSko],
                ]);
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'id_supplier_part_key'       => ['sometimes', 'nullable'],
            'suppliers_product_code'     => ['sometimes', 'nullable'],
            'suppliers_unit_description' => ['sometimes', 'nullable'],
            'part_reference'             => ['sometimes', 'nullable'],
            'units_per_sko'              => ['sometimes', 'nullable'],
            'skos_per_carton'            => ['sometimes', 'nullable'],
            'carton_cbm'                 => ['sometimes', 'nullable'],
            'unit_cost'                  => ['sometimes', 'nullable'],
            'unit_extra_costs'           => ['sometimes', 'nullable'],
            'minimum_order_cartons'      => ['sometimes', 'nullable'],
            'average_delivery_time_days' => ['sometimes', 'nullable'],
            'availability'               => ['sometimes', 'nullable'],
        ];
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @throws \Throwable
     */
    protected function storeOrUpdate(mixed $partKey, array $modelData): SupplierProduct
    {
        if (is_numeric($partKey)) {
            $supplierProduct = $this->scope->supplierProducts()->find((int)$partKey);

            if (!$supplierProduct) {
                throw new Exception("Supplier product not found: $partKey");
            }

            return UpdateSupplierProduct::make()->action($supplierProduct, $modelData, strict: false);
        }

        if (strtolower((string)$this->cleanString($partKey)) === 'new') {
            return StoreSupplierProduct::make()->action($this->scope, $modelData, strict: false);
        }

        throw new Exception('Part key not found, use an existing key or "new"');
    }

    /**
     * @throws \Exception
     */
    protected function resolveTradeUnit(mixed $reference): ?TradeUnit
    {
        $reference = $this->cleanString($reference);

        if ($reference === null) {
            return null;
        }

        $tradeUnit = TradeUnit::where('group_id', $this->scope->group_id)
            ->where('code', $reference)
            ->first();

        if (!$tradeUnit) {
            throw new Exception("Trade unit not found: $reference");
        }

        return $tradeUnit;
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @return array<string, mixed>
     */
    protected function onlyFilled(array $modelData): array
    {
        return array_filter($modelData, fn ($value) => $value !== null && $value !== '');
    }

    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string)$value) ?: null;
    }
}
