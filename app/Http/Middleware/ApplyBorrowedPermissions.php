<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\User\BorrowUserPermissions;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyBorrowedPermissions
{
    /**
     * Checked on every request, so a borrower who loses their admin role, or a lender who moves
     * out of the organisations the borrower administers, ends the borrowing at once.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        $lenderId = $request->session()->get(BorrowUserPermissions::SESSION_KEY);
        $lender   = $lenderId ? User::find($lenderId) : null;

        if ($lender && !BorrowUserPermissions::canBorrow($user, $lender)) {
            $lender = null;
            $request->session()->forget(BorrowUserPermissions::SESSION_KEY);
        }

        $user->borrowPermissionsFrom($lender);

        return $next($request);
    }
}
