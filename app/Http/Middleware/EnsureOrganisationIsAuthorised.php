<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An organisation page is only open to users authorised in that organisation, whatever the page's own
 * permission check says: many pages only checked a permission of the user's own organisation, so an
 * indo employee could read aw's invoices by changing the slug in the url (HELP-3457).
 */
class EnsureOrganisationIsAuthorised
{
    public function handle(Request $request, Closure $next): Response
    {
        $user         = $request->user();
        $organisation = $request->route()?->parameter('organisation');
        $routeName    = (string) $request->route()?->getName();

        if ($user instanceof User
            && $organisation instanceof Organisation
            && (str_starts_with($routeName, 'grp.org.') || str_starts_with($routeName, 'grp.json.'))
            && !$this->isAuthorised($user, $organisation)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * The authorised organisations are a copy of what the user's roles allow, refreshed when roles change.
     * A copy that fell behind is rebuilt before refusing, so nobody is locked out of their own organisation.
     */
    private function isAuthorised(User $user, Organisation $organisation): bool
    {
        if ($user->authorisedOrganisations()->where('organisations.id', $organisation->id)->exists()) {
            return true;
        }

        SetUserAuthorisedModels::run($user);

        return $user->authorisedOrganisations()->where('organisations.id', $organisation->id)->exists();
    }
}
