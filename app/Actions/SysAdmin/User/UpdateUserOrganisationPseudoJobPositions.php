<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 Jan 2025 16:31:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\HumanResources\JobPosition\SyncEmployeeJobPositions;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\CleanUserCaches;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithPreparePositionsForValidation;
use App\Actions\Traits\WithReorganisePositions;
use App\Actions\UI\Grp\BreakUserUiProps;
use App\Http\Resources\SysAdmin\User\UserResource;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateUserOrganisationPseudoJobPositions extends OrgAction
{
    use WithActionUpdate;
    use WithPreparePositionsForValidation;
    use WithReorganisePositions;

    public function handle(User $user, Organisation $organisation, array $modelData): User
    {
        setPermissionsTeamId($user->group->id);
        $jobPositions = Arr::pull($modelData, 'job_positions', []);
        $jobPositions = $this->reorganisePositionsSlugsToIds($jobPositions);

        $employee = $user->employees()->where('employees.organisation_id', $organisation->id)->first();
        if ($employee) {
            SyncEmployeeJobPositions::run($employee, $jobPositions);
        } else {
            SyncUserPseudoOrganisationJobPositions::run($user, $organisation, $jobPositions);
        }
        CleanUserCaches::run($user);
        BreakUserUiProps::run($user);

        return $user;
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("sysadmin.edit");
    }

    public function rules(): array
    {
        return [
            'job_positions'                             => ['sometimes', 'array'],
            'job_positions.*.slug'                      => ['sometimes', 'string'],
            'job_positions.*.scopes'                    => ['sometimes', 'array'],
            'job_positions.*.scopes.warehouses.slug.*'  => ['sometimes', Rule::exists('warehouses', 'slug')->where('organisation_id', $this->organisation->id)],
            'job_positions.*.scopes.fulfilments.slug.*' => ['sometimes', Rule::exists('fulfilments', 'slug')->where('organisation_id', $this->organisation->id)],
            'job_positions.*.scopes.shops.slug.*'       => ['sometimes', Rule::exists('shops', 'slug')->where('organisation_id', $this->organisation->id)],

        ];
    }

    public function action(User $user, Organisation $organisation, $modelData): User
    {
        $this->asAction     = true;
        $this->organisation = $organisation;
        $this->initialisation($organisation, $modelData);

        return $this->handle($user, $organisation, $this->validatedData);
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
