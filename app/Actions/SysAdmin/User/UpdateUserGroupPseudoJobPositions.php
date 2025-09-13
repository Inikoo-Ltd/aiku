<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Sept 2024 18:51:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateEmployees;
use App\Actions\OrgAction;
use App\Actions\Traits\WithPreparePositionsForValidation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithReorganisePositions;
use App\Http\Resources\SysAdmin\UserResource;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateUserGroupPseudoJobPositions extends OrgAction
{
    use WithActionUpdate;
    use WithPreparePositionsForValidation;
    use WithReorganisePositions;

    protected bool $asAction = false;


    public function handle(User $user, array $modelData): User
    {
        $jobPositionsIds = $this->getJobPositionsFromCodes($this->group, Arr::get($modelData, 'job_position_codes', []));

        $currentJobPositions = $user->pseudoJobPositions()->where('scope', 'group')->pluck('job_positions.id')->all();
        $newJobPositionsIds  = array_diff($jobPositionsIds, $currentJobPositions);
        $removeJobPositions  = array_diff($currentJobPositions, $jobPositionsIds);

        $user->pseudoJobPositions()->detach($removeJobPositions);
        foreach ($newJobPositionsIds as $jobPositionId) {
            $user->pseudoJobPositions()->attach(
                [
                    $jobPositionId => [
                        'group_id' => $user->group_id,
                    ]
                ],
            );
        }
        SyncRolesFromJobPositions::run($user);
        if (count($newJobPositionsIds) || count($removeJobPositions)) {
            foreach ($removeJobPositions as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateEmployees::dispatch($jobPosition);
            }

            foreach ($newJobPositionsIds as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateEmployees::dispatch($jobPosition);
            }
        }

        return $user;
    }


    public function getJobPositionsFromCodes(Group $group, array $jobPositionsCodes): array
    {
        $jobPositions = [];
        foreach ($jobPositionsCodes as $positionCode) {
            /** @var JobPosition $jobPosition */
            $jobPosition                     = JobPosition::where('group_id', $group->id)->where('code', $positionCode)->where('scope', 'group')->first();
            $jobPositions [$jobPosition->id] = $jobPosition->id;
        }

        return $jobPositions;
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
            'job_position_codes'   => ['sometimes', 'array'],
            'job_position_codes.*' => ['string', Rule::exists('job_positions', 'code')->where('group_id', $this->group->id)->where('scope', 'group')],
        ];
    }

    public function action(User $user, $modelData): User
    {
        $this->asAction = true;

        $this->initialisationFromGroup($user->group, $modelData);

        return $this->handle($user, $this->validatedData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('job_position_codes', $request->get('permissions', []));
    }

    public function asController(User $user, ActionRequest $request): User
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($user, $this->validatedData);
    }

    public function jsonResponse(User $user): UserResource
    {
        return new UserResource($user);
    }
}
