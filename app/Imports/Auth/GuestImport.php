<?php

namespace App\Imports\Auth;

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Enums\SysAdmin\Guest\GuestTypeEnum;
use App\Imports\WithImport;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class GuestImport implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    public function storeModel($row, $uploadRecord): void
    {
        $row->put('contact_name', $row->get('name'));

        $fields =
            array_merge(
                array_keys(
                    Arr::except(
                        $this->rules(),
                        ['name']
                    )
                ),
                [
                    'contact_name',
                ]
            );

        try {
            $modelData = $row->only($fields)->all();
            $group = $this->upload->user->group ?? Group::first();

            data_set($modelData, 'data.bulk_import', [
                'id' => $this->upload->id,
                'type' => 'Upload',
            ]);

            StoreGuest::make()->action($group, $modelData);
            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function prepareForValidation($data)
    {
        if (Arr::exists($data, 'username')) {
            $data['username'] = Str::lower($data['username']);
        }

        $data['positions'] = explode(',', Arr::get($data, 'positions'));

        return $data;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(GuestTypeEnum::class)],
            'username' => ['required',  'alpha_dash:ascii',
                new IUnique(
                    table: 'users'
                ),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'max:64'],
            'reset_password' => ['sometimes', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable'],
            'email' => ['nullable', 'email'],
            'positions' => ['required', 'array'],
            'positions.*' => ['exists:job_positions,slug'],
            'alias' => ['required', 'string', 'max:16',
                new IUnique(
                    table: 'guests',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => app('group')->id],
                    ]
                ),
            ],
        ];
    }
}
