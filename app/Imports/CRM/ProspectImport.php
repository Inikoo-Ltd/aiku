<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 08:26:02 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Imports\CRM;

use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\Prospect\UpdateProspect;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Catalogue\Shop;
use App\Rules\Phone;
use Exception;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProspectImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    protected Shop $scope;
    public function __construct(Shop $scope, Upload $upload)
    {
        $this->scope  = $scope;
        $this->upload = $upload;
    }


    public function storeModel($row, $uploadRecord): void
    {
        $sanitizedData = $this->processExcelData([$row]);
        $validatedData = array_intersect_key($sanitizedData, array_flip(array_keys($this->rules())));

        $modelData = [
            'company_name' => $validatedData['company'],
            'contact_name' => $validatedData['contact_name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['telephone'],
        ];



        try {
            $prospectKey = $validatedData['id_prospect_key'];
            $existingProspect = null;
            if (is_numeric($prospectKey)) {
                $prospectKey = (int) $prospectKey;
                $existingProspect = $this->scope->prospects()
                ->where('id', $prospectKey)
                ->first();
            }

            $isNew = is_string($validatedData['id_prospect_key'])
                && strtolower($validatedData['id_prospect_key']) === 'new';

            if ($existingProspect) {
                UpdateProspect::run($existingProspect, $modelData);
            } elseif ($isNew) {
                StoreProspect::run($this->scope, $modelData);
            } else {
                throw new Exception("Part key not found");
            }



            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
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
            'id_prospect_key' => [
                'sometimes',
                'nullable',
            ],
            'company'         => ['nullable', 'nullable', 'string', 'max:255'],
            'contact_name'    => ['nullable', 'nullable', 'string', 'max:255'],
            'email'           => [
                'present','nullable',
                'email',
                'max:500',


            ],
            'telephone'           => [
                'nullable',
                new Phone(),
            ],
            // 'contact_website' => ['nullable'],
            // 'tags'            => ['nullable', 'array'],
            // 'tags.*'          => ['nullable', 'string'],
        ];
    }
}
