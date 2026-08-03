<?php

/*
 * Author Louis Perez
 * Created on 31-07-2026-17h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\SysAdmin\User;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class StoreUserFromEmployee extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Employee $employee, array $modelData): User
    {
        if ($employee->user) {
            abort(422, 'User exists for this employee');
        }

        if ($employee->email) {
            data_set($modelData, 'email', $employee->email);
        }

        if ($employee->contact_name) {
            data_set($modelData, 'contact_name', $employee->contact_name);
        }

        data_set($modelData, 'language_id', $employee->organisation->language_id);
        data_set($modelData, 'status', true);

        return StoreUser::make()->action($employee, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'username'          => [
                'required',
                'lowercase',
                $this->strict ? new AlphaDashDot() : 'string',
                new IUnique(
                    table: 'users',
                    column: 'username',
                ),
                Rule::notIn(['export', 'create'])
            ],
            'password'                  => ['required', app()->isLocal() || app()->environment('testing') || !$this->strict ? Password::min(3) : Password::min(8)],
            'password_confirmation'     => ['required', 'same:password']
        ];

        if (!$this->strict) {
            $rules['deleted_at']      = ['sometimes', 'date'];
            $rules['created_at']      = ['sometimes', 'date'];
            $rules['fetched_at']      = ['sometimes', 'date'];
            $rules['source_id']       = ['sometimes', 'string', 'max:255'];
            $rules['legacy_password'] = ['sometimes', 'string'];
        }

        return $rules;
    }

    public function asController(Employee $employee, ActionRequest $request): User
    {
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
