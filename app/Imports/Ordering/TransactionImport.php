<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Sept 2024 14:27:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Imports\Ordering;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Imports\WithImport;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Upload;
use App\Models\Ordering\Order;
use Exception;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TransactionImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    protected Order $scope;

    public function __construct(Order $order, Upload $upload)
    {
        $this->upload            = $upload;
        $this->scope             = $order;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields =
            array_merge(
                array_keys(
                    $this->rules()
                )
            );

        $validatedData = $row->only($fields)->all();

        $modelData = [
            'quantity_ordered' => (int) $validatedData['quantity']
        ];

        data_set($modelData, 'data.bulk_import', [
            'id'   => $this->upload->id,
            'type' => 'Upload',
        ]);

        /** @var Product $product */
        $product = Product::where('code', $validatedData['code'])->first();
        $historicAsset = $product->historicAsset;

        try {
            StoreTransaction::make()->action(
                $this->scope,
                $historicAsset,
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
            'code' => [
                'sometimes',
                'nullable',
                'max:64',
                'string'
            ],
            'quantity' => ['sometimes'],
        ];
    }
}
