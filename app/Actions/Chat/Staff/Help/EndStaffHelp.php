<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Help;

use App\Models\Chat\StaffHelpGrant;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class EndStaffHelp
{
    use AsAction;

    public function handle(StaffHelpGrant $grant, User $user): void
    {
        $grant->end($user->id === $grant->granter_user_id ? StaffHelpGrant::ENDED_BY_GRANTER : StaffHelpGrant::STOPPED_BY_HELPER);
    }

    public function authorize(ActionRequest $request): bool
    {
        $grant = $request->route('staffHelpGrant');

        return in_array($request->user()->id, [$grant->granter_user_id, $grant->helper_user_id], true);
    }

    public function asController(StaffHelpGrant $staffHelpGrant, ActionRequest $request): Response|RedirectResponse
    {
        $this->handle($staffHelpGrant, $request->user());

        if ($request->session()->get(StaffHelpGrant::SESSION_KEY) === $staffHelpGrant->id) {
            $request->session()->forget(StaffHelpGrant::SESSION_KEY);

            return Inertia::location(route('grp.dashboard.show'));
        }

        return back();
    }
}
