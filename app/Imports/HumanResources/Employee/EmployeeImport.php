<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Sep 2023 23:37:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Imports\HumanResources\Employee;

use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Imports\WithImport;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EmployeeImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected Organisation|Workplace $parent;

    public function __construct(Organisation|Workplace $parent, $upload)
    {
        $this->upload = $upload;
        $this->parent = $parent;
    }

    public function storeModel($row, $uploadRecord): void
    {
        // $parent = organisation();
        // if ($row['workplace'] and $workplace = Workplace::where('slug', $row['workplace'])->first()) {
        //     $parent = $workplace;
        // }

        $row->put('contact_name', $row->get('name'));
        $row->put('employment_start_at', $row->get('starting_date'));

        $fields =
            array_merge(
                array_keys(
                    Arr::except(
                        $this->rules(),
                        ['name', 'starting_date', 'workplace']
                    )
                ),
                [
                    'contact_name', 'employment_start_at',
                ]
            );

        try {
            $modelData = $row->only($fields)->all();
            data_set($modelData, 'work_email', null, overwrite: false);
            data_set($modelData, 'email', null, overwrite: false);

            data_set($modelData, 'data.bulk_import', [
                'id' => $this->upload->id,
                'type' => 'Upload',
            ]);

            StoreEmployee::make()->action(
                $this->parent,
                $modelData
            );
            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function prepareForValidation($data)
    {
        $data['starting_date'] = Carbon::createFromFormat('d-M-y', $data['starting_date'])->format('Y-m-d');

        if (Arr::exists($data, 'username')) {
            $data['username'] = Str::lower($data['username']);
        }
        if (! Arr::exists($data, 'state')) {
            $data['state'] = EmployeeStateEnum::WORKING->value;
        }

        $data['positions'] = explode(',', Arr::get($data, 'positions'));

        return $data;
    }

    public function rules(): array
    {
        return [
            'worker_number' => ['required', 'max:64', 'unique:employees', 'alpha_dash:ascii'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'work_email' => ['sometimes', 'required', 'email'],
            'alias' => ['required', 'unique:employees', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:256'],
            'job_title' => ['required', 'string', 'max:256'],
            'positions' => ['required', 'array'],
            'starting_date' => ['required', 'date'],
            'username' => ['sometimes', 'unique:users', 'alpha_dash:ascii'],
            'password' => ['sometimes', 'string', 'min:8', 'max:64'],
            'reset_password' => ['sometimes', 'boolean'],
            'state' => ['required', new Enum(EmployeeStateEnum::class)],
        ];
    }
}
