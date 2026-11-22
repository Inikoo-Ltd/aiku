<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\SystemUser;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\SupplyChain\Agent\UI\IndexAgentSystemUsers;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateAgentSystemUser extends OrgAction
{
    private ?string $previousSlug = null;

    /**
     * Supply chain managers look after the agents' staff only: a user who can also open an
     * organisation that is not an agent is group staff and is changed from Sysadmin.
     */
    public function handle(User $user, array $modelData): User
    {
        return UpdateUser::make()->action($user, $modelData);
    }

    public function isAgentStaff(Agent $agent, User $user): bool
    {
        return IndexAgentSystemUsers::agentStaff($agent, User::query())->whereKey($user->id)->exists();
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
            'username'     => ['sometimes', 'required', 'string', 'lowercase', 'max:64', 'alpha_dash'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email'        => ['sometimes', 'nullable', 'email'],
            'status'       => ['sometimes', 'boolean'],
            'password'     => ['sometimes', 'required', 'string', app()->isLocal() || app()->environment('testing') ? 'min:3' : 'min:8'],
        ];
    }

    public function asController(Agent $agent, User $user, ActionRequest $request): User
    {
        abort_unless($this->isAgentStaff($agent, $user), 403);
        $this->previousSlug = $user->slug;
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($user, $this->validatedData);
    }

    public function htmlResponse(User $user): RedirectResponse
    {
        if ($this->previousSlug && $this->previousSlug !== $user->slug) {
            return redirect()->to(str_replace("/system-users/$this->previousSlug", "/system-users/$user->slug", url()->previous()));
        }

        return back();
    }
}
