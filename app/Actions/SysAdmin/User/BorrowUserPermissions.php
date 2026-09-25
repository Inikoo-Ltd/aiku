<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia;

class BorrowUserPermissions
{
    use AsAction;

    public const string SESSION_KEY = 'borrowed_permissions_user_id';

    /**
     * Group admins and engineers can borrow anybody in the group. An organisation admin only
     * somebody whose permissions all sit inside the organisations they administer, so borrowing
     * can never give them more than they already manage.
     */
    public static function canBorrow(User $borrower, User $lender): bool
    {
        if ($borrower->id === $lender->id || $borrower->group_id !== $lender->group_id) {
            return false;
        }

        if (self::canBorrowAnybody($borrower)) {
            return true;
        }

        $administeredOrganisationIds = self::administeredOrganisationIds($borrower);

        if ($administeredOrganisationIds->isEmpty()
            || $lender->roles()->where('roles.scope_type', 'Group')->exists()
            || $lender->permissions()->where('permissions.scope_type', 'Group')->exists()) {
            return false;
        }

        return $lender->authorisedOrganisations()->whereNotIn('organisations.id', $administeredOrganisationIds)->doesntExist();
    }

    public static function canBorrowSomebody(User $borrower): bool
    {
        return self::canBorrowAnybody($borrower) || self::administeredOrganisationIds($borrower)->isNotEmpty();
    }

    private static function canBorrowAnybody(User $borrower): bool
    {
        return $borrower->roles()->whereIn('name', [
            RolesEnum::GROUP_ADMIN->value,
            RolesEnum::HELP_DESK_CLERK->value,
            RolesEnum::HELP_DESK_SUPERVISOR->value,
        ])->exists();
    }

    private static function administeredOrganisationIds(User $borrower): Collection
    {
        return $borrower->roles()
            ->where('roles.scope_type', 'Organisation')
            ->whereRaw("roles.name = '".RolesEnum::ORG_ADMIN->value."-' || roles.scope_id")
            ->pluck('roles.scope_id');
    }

    /**
     * ponytail: candidates are filtered one by one with canBorrow(), fine for a 50 row search box;
     * build it as a query if organisation admins start missing people they should see.
     */
    public function lenders(Request $request): JsonResponse
    {
        /** @var User $borrower */
        $borrower = $request->user();
        $search   = trim((string) $request->query('search'));

        return response()->json(User::where('group_id', $borrower->group_id)
            ->where('status', true)
            ->where('id', '!=', $borrower->id)
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereLike('username', "%$search%")->orWhereLike('contact_name', "%$search%")
            ))
            ->orderBy('username')
            ->limit(50)
            ->get()
            ->when(!self::canBorrowAnybody($borrower), fn ($lenders) => $lenders->filter(fn (User $lender) => self::canBorrow($borrower, $lender)))
            ->take(15)
            ->map(fn (User $lender) => $lender->only(['id', 'username', 'contact_name']))
            ->values());
    }

    public function handle(User $lender): void
    {
        session()->put(self::SESSION_KEY, $lender->id);
    }

    public function asController(User $user, ActionRequest $request): Response
    {
        abort_unless(self::canBorrow($request->user(), $user), 403);

        $this->handle($user);

        return Inertia::location(route('grp.dashboard.show'));
    }

    public function stop(Request $request): Response
    {
        $request->session()->forget(self::SESSION_KEY);

        return Inertia::location(route('grp.dashboard.show'));
    }
}
