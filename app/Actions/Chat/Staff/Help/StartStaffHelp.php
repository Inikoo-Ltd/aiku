<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Help;

use App\Actions\SysAdmin\User\BorrowUserPermissions;
use App\Models\Chat\StaffHelpGrant;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class StartStaffHelp
{
    use AsAction;

    public function handle(StaffHelpGrant $grant, User $helper): void
    {
        abort_if($grant->refusalFor($helper) !== null, 403);

        if ($grant->started_at === null) {
            $grant->update(['started_at' => now()]);
        }

        session()->forget(BorrowUserPermissions::SESSION_KEY);
        session()->put(StaffHelpGrant::SESSION_KEY, $grant->id);
    }

    /**
     * No chains: nobody already working with someone else's access (helping, or an admin borrowing) starts helping.
     */
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->permissionsLender() === null;
    }

    public function asController(StaffHelpGrant $staffHelpGrant, ActionRequest $request): Response
    {
        $this->handle($staffHelpGrant, $request->user());

        return Inertia::location(route('grp.dashboard.show'));
    }
}
