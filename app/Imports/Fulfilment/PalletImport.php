<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Sept 2024 14:27:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Imports\Fulfilment;

use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Imports\WithImport;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Helpers\Upload;
use App\Rules\IUnique;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PalletImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected PalletDelivery $scope;

    public function __construct(PalletDelivery $palletDelivery, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $palletDelivery;
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
            'type' => $validatedData['pallet_type'],
            'customer_reference' => $validatedData['pallet_customer_reference'],
            'notes' => $validatedData['pallet_notes'],
        ];

        if (! Arr::get($modelData, 'type')) {
            data_set($modelData, 'type', PalletTypeEnum::PALLET->value);
        } else {
            $type = strtolower(str_replace(' ', '', trim($modelData['type'])));

            $type = match ($type) {
                'oversize' => PalletTypeEnum::OVERSIZE->value,
                'box', 'carton' => PalletTypeEnum::BOX->value,
                default => PalletTypeEnum::PALLET->value,
            };
            data_set($modelData, 'type', $type);
        }

        data_set($modelData, 'data.bulk_import', [
            'id' => $this->upload->id,
            'type' => 'Upload',
        ]);

        try {
            StorePalletCreatedInPalletDelivery::run(
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
            'pallet_customer_reference' => [
                'sometimes',
                'nullable',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'pallets',
                    column: 'customer_reference',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->scope->fulfilment_customer_id],
                    ]
                ),

            ],
            'pallet_notes' => ['nullable'],
            'pallet_type' => ['nullable'],
        ];
    }
}
