<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\User\BorrowUserPermissions;
use App\Models\Chat\StaffHelpGrant;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ApplyBorrowedPermissions
{
    public const string AUDIT_CONTEXT_KEY = 'audit_on_behalf_of';

    /**
     * Checked on every request, so a borrower who loses their admin role, a lender who moves out of the organisations
     * the borrower administers, or a help grant that ended or ran out (INI-496) ends the borrowing at once. Whoever
     * the access comes from is put in the hidden context, so every audit written (queued jobs included) keeps both names.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        [$lender, $grant] = $this->helpGrantLender($request, $user) ?? [$this->adminLender($request, $user), null];

        if ($grant && $this->outlivesTheGrant($request, $grant)) {
            abort(403, __('Not while helping: this would last after the help ends'));
        }

        $user->borrowPermissionsFrom($lender);

        if ($lender) {
            Context::addHidden(self::AUDIT_CONTEXT_KEY, array_filter([
                'on_behalf_of_user_id' => $lender->id,
                'help_grant_id'        => $grant?->id,
            ]));
        }

        return $next($request);
    }

    /**
     * Handing over the keyboard lends the granter's access for a few minutes, never beyond: no access token (it keeps
     * working after the help ends), and no editing the sign-in, permissions or job positions of the granter or the
     * helper themselves.
     */
    private function outlivesTheGrant(Request $request, StaffHelpGrant $grant): bool
    {
        $routeName = (string) $request->route()?->getName();

        if ($routeName === 'grp.models.user.access-token.create') {
            return true;
        }

        return str_starts_with($routeName, 'grp.models.user.')
            && in_array((int) $request->route('user'), [$grant->granter_user_id, $grant->helper_user_id], true);
    }

    /**
     * @return array{0: User, 1: StaffHelpGrant}|null
     */
    private function helpGrantLender(Request $request, User $user): ?array
    {
        $grantId = $request->session()->get(StaffHelpGrant::SESSION_KEY);
        if (!$grantId) {
            return null;
        }

        $grant   = StaffHelpGrant::with('granter')->find($grantId);
        $refusal = $grant?->refusalFor($user);

        if (!$grant || $refusal !== null) {
            $request->session()->forget(StaffHelpGrant::SESSION_KEY);
            if ($grant && $grant->helper_user_id === $user->id) {
                $grant->end($refusal);
            }

            return null;
        }

        return [$grant->granter, $grant];
    }

    private function adminLender(Request $request, User $user): ?User
    {
        $lenderId = $request->session()->get(BorrowUserPermissions::SESSION_KEY);
        $lender   = $lenderId ? User::find($lenderId) : null;

        if ($lender && !BorrowUserPermissions::canBorrow($user, $lender)) {
            $request->session()->forget(BorrowUserPermissions::SESSION_KEY);

            return null;
        }

        return $lender;
    }
}
