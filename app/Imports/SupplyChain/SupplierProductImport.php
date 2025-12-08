<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-16h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Imports\SupplyChain;

use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\SupplyChain\Supplier;
use Exception;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierProductImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected Supplier $scope;

    public function __construct(Supplier $supplier, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $supplier;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $sanitizedData = $this->processExcelData([$row]);
        $validatedData = array_intersect_key($sanitizedData, array_flip(array_keys($this->rules())));

        if ($validatedData['availability'] == 'Available') {
            $availability = true;
        } else {
            $availability = false;
        }

        $modelData = [
            'code' => $validatedData['suppliers_product_code'],
            'name' => $validatedData['suppliers_unit_description'],
            'is_available' => $availability,
            'cost' => $validatedData['unit_cost'],
            'units_per_pack' => $validatedData['units_per_sko'],
            'units_per_carton' => $validatedData['skos_per_carton'],
            'cbm' => $validatedData['carton_cbm'],
        ];

        try {
            $partKey = $validatedData['id_supplier_part_key'];
            $existingProduct = null;
            if (is_numeric($partKey)) {
                $partKey = (int) $partKey;
                $existingProduct = $this->scope->supplierProducts()
                    ->where('id', $partKey)
                    ->first();
            }

            $isNew = is_string($validatedData['id_supplier_part_key'])
                && strtolower($validatedData['id_supplier_part_key']) === 'new';

            if ($existingProduct) {
                UpdateSupplierProduct::run($existingProduct, $modelData);
            } elseif ($isNew) {
                StoreSupplierProduct::run($this->scope, $modelData);
            } else {
                throw new Exception('Part key not found');
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        } catch (\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    protected function processExcelData($data)
    {
        $mappedRow = [];

        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                $mappedKey = str_replace([' ', ':', "'"], '_', strtolower($key));
                $mappedRow[$mappedKey] = $value;
            }
            break;
        }

        return $mappedRow;
    }

    public function rules(): array
    {
        return [
            'id_supplier_part_key' => [
                'sometimes',
                'nullable',
            ],
            'suppliers_product_code' => [
                'sometimes',
                'nullable',
            ],
            'units_per_sko' => [
                'sometimes',
                'nullable',
            ],
            'skos_per_carton' => [
                'sometimes',
                'nullable',
            ],
            'carton_cbm' => [
                'sometimes',
                'nullable',
            ],
            'unit_cost' => [
                'sometimes',
                'nullable',
            ],
            'availability' => [
                'sometimes',
                'nullable',
            ],
            'suppliers_unit_description' => [
                'sometimes',
                'nullable',
            ],
        ];
    }
}
