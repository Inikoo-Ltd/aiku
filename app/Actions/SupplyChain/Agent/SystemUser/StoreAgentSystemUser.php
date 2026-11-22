<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\SystemUser;

use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\OrgAction;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\HumanResources\JobPosition;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class StoreAgentSystemUser extends OrgAction
{
    public const array POSITIONS = ['org-admin', 'agt-m', 'agt-c'];

    /**
     * An agent's login is an employee of the agent organisation holding one of the agent
     * positions, so its roles come from the position like every other user's.
     */
    public function handle(Agent $agent, array $modelData): User
    {
        $position = JobPosition::where('organisation_id', $agent->organisation_id)
            ->where('code', Arr::pull($modelData, 'position'))
            ->firstOrFail();

        $employee = StoreEmployee::make()->action($agent->organisation, [
            'worker_number'   => $modelData['username'],
            'alias'           => $modelData['username'],
            'contact_name'    => $modelData['contact_name'],
            'work_email'      => Arr::get($modelData, 'email'),
            'state'           => EmployeeStateEnum::WORKING,
            'type'            => EmployeeTypeEnum::EMPLOYEE,
            'employment_type' => EmploymentTypeEnum::FULL_TIME,
            'positions'       => [['slug' => $position->slug, 'scopes' => []]],
            'username'        => $modelData['username'],
            'password'        => $modelData['password'],
            'reset_password'  => false,
        ]);

        return $employee->getUser();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('supply-chain');
    }

    public function rules(): array
    {
        return [
            'username'     => ['required', 'string', 'lowercase', 'max:64', 'alpha_dash'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email'        => ['sometimes', 'nullable', 'email'],
            'password'     => ['required', 'string', app()->isLocal() || app()->environment('testing') ? 'min:3' : 'min:8'],
            'position'     => ['required', Rule::in(self::POSITIONS)],
        ];
    }

    public function asController(Agent $agent, ActionRequest $request): User
    {
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent, $this->validatedData);
    }

    public function action(Agent $agent, array $modelData): User
    {
        $this->asAction = true;
        $this->initialisationFromGroup($agent->group, $modelData);

        return $this->handle($agent, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
