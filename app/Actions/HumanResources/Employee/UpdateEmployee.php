<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 00:49:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\HumanResources\Employee\Search\EmployeeRecordSearch;
use App\Actions\HumanResources\JobPosition\SyncEmployeeJobPositions;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateEmployees;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateEmployees;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithPreparePositionsForValidation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithReorganisePositions;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Employee;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Rules\PinRule;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateEmployee extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;
    use WithActionUpdate;
    use WithPreparePositionsForValidation;
    use WithReorganisePositions;
    use WithNoStrictRules;
    use WithModelAddressActions;

    protected bool $asAction = false;

    private Employee $employee;

    public function handle(Employee $employee, array $modelData): Employee
    {
        if (Arr::has($modelData, 'contact_address')) {
            $contactAddressData = Arr::get($modelData, 'contact_address');
            Arr::forget($modelData, 'contact_address');


            if (!blank($contactAddressData)) {
                if ($employee->address) {
                    UpdateAddress::run($employee->address, $contactAddressData);
                } else {
                    $this->addAddressToModelFromArray(
                        model: $employee,
                        addressData: $contactAddressData,
                        updateLocation: false,
                    );
                }
            }

            $employee->updateQuietly(
                [
                    'location' => $employee->address->getLocation()
                ]
            );
        }

        if (Arr::has($modelData, 'job_positions')) {
            $jobPositions = Arr::pull($modelData, 'job_positions', []);
            $jobPositions = $this->reorganisePositionsSlugsToIds($jobPositions);
            SyncEmployeeJobPositions::run($employee, $jobPositions);
        }

        $credentials = Arr::only($modelData, ['username', 'password', 'auth_type', 'user_model_status']);

        data_forget($modelData, 'username');
        data_forget($modelData, 'password');
        data_forget($modelData, 'auth_type');
        data_forget($modelData, 'user_model_status');

        $employee = $this->update($employee, $modelData, ['data', 'salary']);

        if (Arr::hasAny($employee->getChanges(), ['worker_number', 'worker_number', 'contact_name', 'work_email', 'job_title', 'email'])) {
            EmployeeRecordSearch::dispatch($employee);
        }

        if (Arr::hasAny($employee->getChanges(), ['state'])) {
            GroupHydrateEmployees::dispatch($employee->group)->delay($this->hydratorsDelay);
            OrganisationHydrateEmployees::dispatch($employee->organisation)->delay($this->hydratorsDelay);
        }

        if ($user = $employee->getUser()) {
            if (Arr::exists($credentials, 'user_model_status')) {
                $employee->users()->updateExistingPivot($user->id, ['status' => $credentials['user_model_status']]);
                data_forget($credentials, 'user_model_status');
            }

            UpdateUser::run($user, $credentials);
        }

        return $employee;
    }

    public function rules(): array
    {
        $rules = [
            'worker_number'                             => [
                'sometimes',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'employees',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->employee->id
                        ],
                    ]
                ),

            ],
            'state'                                     => ['sometimes', 'required', new Enum(EmployeeStateEnum::class)],
            'employment_start_at'                       => ['sometimes', 'nullable', 'date'],
            'employment_end_at'                         => ['sometimes', 'nullable', 'date'],
            'work_email'                                => [
                'sometimes',
                'nullable',
                'email',
                new IUnique(
                    table: 'employees',
                    extraConditions: [

                        [
                            'column'   => 'group_id',
                            'operator' => '=',
                            'value'    => $this->employee->group_id
                        ],
                    ]
                ),
            ],
            'alias'                                     => [
                'sometimes',
                'string',
                $this->strict ? 'max:24' : 'max:255',
                new IUnique(
                    table: 'employees',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->employee->id
                        ],
                    ]
                ),
            ],
            'pin'                                       => ['sometimes', new PinRule($this->employee->organisation_id)],
            'contact_name'                              => ['sometimes', 'string', 'max:256'],
            'date_of_birth'                             => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'job_title'                                 => ['sometimes', 'nullable', 'string', 'max:256'],
            'job_positions'                             => ['sometimes', 'array'],
            'job_positions.*.slug'                      => ['sometimes', 'string'],
            'job_positions.*.scopes'                    => ['sometimes', 'array'],
            'job_positions.*.scopes.warehouses.slug.*'  => ['sometimes', Rule::exists('warehouses', 'slug')->where('organisation_id', $this->organisation->id)],
            'job_positions.*.scopes.fulfilments.slug.*' => ['sometimes', Rule::exists('fulfilments', 'slug')->where('organisation_id', $this->organisation->id)],
            'job_positions.*.scopes.shops.slug.*'       => ['sometimes', Rule::exists('shops', 'slug')->where('organisation_id', $this->organisation->id)],
            'email'                                     => ['sometimes', 'nullable', 'email'],
            'emergency_contact'                         => ['sometimes', 'nullable', 'string', 'max:1024'],
            'type'                                      => ['sometimes', Rule::enum(EmployeeTypeEnum::class)],
            'contact_address'                           => ['sometimes', 'nullable', new ValidAddress()],
            'notes'                                     => ['sometimes', 'nullable', 'string', 'max:4000'],
            'identity_document_type'                    => ['sometimes', 'nullable', 'string', 'max:256'],
            'identity_document_number'                  => ['sometimes', 'nullable', 'string', 'max:256'],


        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        if ($user = $this->employee->getUser()) {
            $rules['username']          = [
                'sometimes',
                'required',
                'lowercase',
                new AlphaDashDot(),

                Rule::notIn(['export', 'create']),
                new IUnique(
                    table: 'users',
                    extraConditions: [

                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $user->id
                        ],
                    ]
                ),


            ];
            $rules['password']          = ['sometimes', 'required', app()->isLocal() || app()->environment('testing') ? null : Password::min(8)];
            $rules['user_model_status'] = ['sometimes', 'boolean'];
        }


        return $rules;
    }

    public function action(Employee $employee, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Employee
    {
        $this->strict = $strict;
        if (!$audit) {
            Employee::disableAuditing();
        }
        $this->asAction       = true;
        $this->employee       = $employee;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($employee->organisation, $modelData);

        return $this->handle($employee, $this->validatedData);
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        $this->prepareJobPositionsForValidation();
        if ($this->has('password')) {
            {
                $this->set('auth_type', UserAuthTypeEnum::DEFAULT);
            }
        }

        if ($this->has('cluster.state')) {
            {
                $this->set('state', $this->get('cluster.state'));
            }
        }

        if ($this->has('cluster.employment_start_at')) {
            {
                $this->set('employment_start_at', $this->get('cluster.employment_start_at'));
            }
        }

        if ($this->has('cluster.employment_end_at')) {
            {
                $this->set('employment_end_at', $this->get('cluster.employment_end_at'));
            }
        }
    }

    public function asController(Employee $employee, ActionRequest $request): Employee
    {
        $this->employee = $employee;
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee, $this->validatedData);
    }

    public function jsonResponse(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee);
    }
}
