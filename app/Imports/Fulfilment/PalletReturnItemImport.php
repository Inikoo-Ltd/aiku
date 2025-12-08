<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Sept 2024 14:27:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Imports\Fulfilment;

use App\Actions\Fulfilment\Pallet\AttachPalletToReturnFromImport;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToReturn;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Imports\WithImport;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Upload;
use Exception;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PalletReturnItemImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected PalletReturn $scope;

    public function __construct(PalletReturn $palletReturn, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $palletReturn;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields = array_keys($this->rules());
        $rowData = $row->only($fields)->toArray();

        $modelData = $rowData;

        if (! Arr::get($modelData, 'type')) {
            data_set($modelData, 'type', PalletTypeEnum::PALLET->value);
        }

        data_set($modelData, 'data.bulk_import', [
            'id' => $this->upload->id,
            'type' => 'Upload',
        ]);

        $fail = false;

        if ($this->scope->type == PalletReturnTypeEnum::PALLET) {
            try {
                AttachPalletToReturnFromImport::make()->action(
                    $this->scope,
                    $modelData
                );

                $this->setRecordAsCompleted($uploadRecord);
            } catch (Exception $e) {
                $fail = $e->getMessage();
            } catch (\Throwable $e) {
                $fail = $e->getMessage();
            }
        } else {
            try {
                AttachStoredItemToReturn::run(
                    $this->scope,
                    $modelData
                );

                $this->setRecordAsCompleted($uploadRecord);
            } catch (Exception $e) {
                $fail = $e->getMessage();
            } catch (\Throwable $e) {
                $fail = $e->getMessage();
            }
        }

        if ($fail) {
            $this->setRecordAsFailed($uploadRecord, [$fail]);
        }
    }

    public function rules(): array
    {
        return [
            'reference' => ['required'],
        ];
    }
}
