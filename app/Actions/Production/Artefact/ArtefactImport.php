<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Sep 2023 01:20:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Production\Production;
use Exception;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ArtefactImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected Production $scope;

    public function __construct(Production $production, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $production;
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
            StoreArtefact::run(
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
            'code' => [
                'required',
                'max:64',
            ],
            'name' => ['required', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', Rule::enum(ArtefactStateEnum::class)],
            'source_id' => ['sometimes', 'nullable', 'string'],
            'created_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
