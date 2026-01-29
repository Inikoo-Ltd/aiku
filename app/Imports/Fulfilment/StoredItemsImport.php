<?php

/*
 * author Arya Permana - Kirin
 * created on 13-02-2025-10h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Imports\Fulfilment;

use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Imports\WithImport;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Upload;
use Exception;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StoredItemsImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields =
            array_merge(
                array_keys(
                    $this->rules()
                )
            );

        $rowData = $row->only($fields)->all();

        $modelData = [
            'reference' => $rowData['reference_do_not_modify'],
            'name' => $rowData['name']
        ];

        try {
            $storedItem = StoredItem::where('reference', $modelData['reference'])->first();
            UpdateStoredItem::run($storedItem, $modelData);

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception|\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'reference_do_not_modify' => [
                'sometimes',
                'nullable',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
                Rule::exists('stored_items', 'reference')
            ],
            'name'                    => ['nullable']
        ];
    }
}
