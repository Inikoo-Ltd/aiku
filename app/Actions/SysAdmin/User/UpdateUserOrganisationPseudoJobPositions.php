<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 Jan 2025 16:31:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithPreparePositionsForValidation;
use App\Actions\Traits\WithReorganisePositions;
use App\Http\Resources\SysAdmin\UserResource;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateUserOrganisationPseudoJobPositions extends OrgAction
{
    use WithActionUpdate;
    use WithPreparePositionsForValidation;
    use WithReorganisePositions;

    private User $user;

    public function handle(User $user, Organisation $organisation, array $modelData): User
    {
        $jobPositions = Arr::pull($modelData, 'job_positions', []);
        $jobPositions = $this->reorganisePositionsSlugsToIds($jobPositions);

        SyncUserPseudoOrganisationJobPositions::run($user, $organisation, $jobPositions);

        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    public function rules(): array
    {
        return [
            'job_positions' => ['sometimes', 'array'],
            'job_positions.*.slug' => ['sometimes', 'string'],
            'job_positions.*.scopes' => ['sometimes', 'array'],
            'job_positions.*.scopes.warehouses.slug.*' => ['sometimes', Rule::exists('warehouses', 'slug')->where('organisation_id', $this->organisation->id)],
            'job_positions.*.scopes.fulfilments.slug.*' => ['sometimes', Rule::exists('fulfilments', 'slug')->where('organisation_id', $this->organisation->id)],
            'job_positions.*.scopes.shops.slug.*' => ['sometimes', Rule::exists('shops', 'slug')->where('organisation_id', $this->organisation->id)],

        ];
    }

    public function action(User $user, Organisation $organisation, $modelData): User
    {

        $this->asAction = true;
        $this->organisation = $organisation;
        $this->user = $user;
        $this->initialisation($organisation, $modelData);

        return $this->handle($user, $organisation, $this->validatedData);
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {

        if ($this->asAction) {
            $userToUpdate = $this->user;
        } else {
            $userToUpdate = $request->route()->parameter('user');
        }

        $employee = $userToUpdate->employees->where('organisation_id', $this->organisation->id)->first();

        if ($employee) {
            $validator->errors()->add('permissions', 'User is an employee of the organisation');
        }

    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->prepareJobPositionsForValidation();
    }

    public function asController(User $user, Organisation $organisation, ActionRequest $request): User
    {

        $this->initialisation($organisation, $request);

        return $this->handle($user, $organisation, $this->validatedData);
    }

    public function jsonResponse(User $user): UserResource
    {
        return new UserResource($user);
    }
}
