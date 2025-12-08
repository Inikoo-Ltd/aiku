<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Sep 2023 01:20:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Imports\Warehouse;

use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Warehouse;
use Exception;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class WarehouseAreaImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected Warehouse $scope;

    public function __construct(Warehouse $warehouse, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $warehouse;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields =
            array_merge(
                array_keys(
                    $this->rules()
                )
            );

        $modelData = $row->only($fields)->all();

        data_set($modelData, 'data.bulk_import', [
            'id' => $this->upload->id,
            'type' => 'Upload',
        ]);

        try {
            StoreWarehouseArea::make()->action(
                $this->scope,
                $modelData
            );

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        } catch (\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required'],
            'name' => ['required'],
        ];
    }
}
