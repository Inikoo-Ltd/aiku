<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Imports\Dispatching;

use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Exception;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BatchCodeImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    protected Warehouse $scope;

    public function __construct(Warehouse $warehouse, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope  = $warehouse;
    }

    public function storeModel($row, $uploadRecord): void
    {
        try {
            $orgStock = OrgStock::where('organisation_id', $this->scope->organisation_id)
                ->where('code', $row->get('sku'))
                ->first();

            $modelData = [
                'code'         => $row->get('code'),
                'expiry_date'  => $row->get('expiry_date') ?: null,
                'org_stock_id' => $orgStock?->id,
            ];

            data_set($modelData, 'data.bulk_import', [
                'id'   => $this->upload->id,
                'type' => 'Upload',
            ]);

            StoreBatchCode::make()->action($this->scope, $modelData);
            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'sku'         => ['required', 'string'],
        ];
    }

    public function prepareForValidation($data): array
    {
        if (!Arr::exists($data, 'expiry_date') || blank($data['expiry_date'])) {
            return $data;
        }

        if (is_numeric($data['expiry_date'])) {
            $data['expiry_date'] = ExcelDate::excelToDateTimeObject((float) $data['expiry_date'])->format('Y-m-d');
        }

        return $data;
    }
}
