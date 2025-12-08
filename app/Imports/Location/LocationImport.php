<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 19 Jan 2024 12:04:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Imports\Location;

use App\Actions\Inventory\Location\StoreLocation;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LocationImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected Warehouse|WarehouseArea|Organisation $scope;

    public function __construct(Warehouse|WarehouseArea|Organisation $scope, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $scope;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields =
            array_merge(
                array_keys(
                    Arr::except(
                        $this->rules(),
                        []
                    )
                ),
                []
            );

        try {
            $modelData = $row->only($fields)->all();

            data_set($modelData, 'data.bulk_import', [
                'id' => $this->upload->id,
                'type' => 'Upload',
            ]);

            if ($this->scope instanceof Organisation) {
                $this->scope = Warehouse::where('slug', $modelData['warehouse_slug'])->first();
                $warehouseArea = WarehouseArea::where('slug', $modelData['warehouse_area_slug'])->first();

                data_set($modelData, 'warehouse_id', $this->scope->id);
                data_set($modelData, 'warehouse_area_id', $warehouseArea->id);
            }

            StoreLocation::make()->action($this->scope, $modelData);
            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'max:64',
                'alpha_dash',
            ],
            'warehouse_slug' => ['nullable', 'exists:warehouses,slug'],
            'warehouse_area_slug' => ['nullable', 'exists:warehouse_areas,slug'],
            'max_weight' => ['nullable', 'numeric', 'min:0.1', 'max:1000000'],
            'max_volume' => ['nullable', 'numeric', 'min:0.1', 'max:1000000'],
        ];
    }
}
