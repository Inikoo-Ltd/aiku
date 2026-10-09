<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff of an agent see their own organisation and nothing of the group: only the sections listed here
 * are open to them, so a group page added later stays closed to agents until someone decides otherwise.
 */
class KeepAgentStaffInTheirOrganisation
{
    public const array OPEN_SECTIONS = [
        'grp.org.agent.',
        'grp.org.procurement.',
        'grp.org.dashboard.',
        'grp.org.hr.',
        'grp.org.tasks.',
        'grp.org.tickets.',
        'grp.org.fallback',
        'grp.json.',
        'grp.models.',
        'grp.dashboard.show',
        'grp.tickets.',
        'grp.tasks.',
        'grp.profile.',
        'grp.notifications',
        'grp.chat.staff.',
        'grp.clocking_employees.',
        'grp.clocking_scan',
        'grp.kiosk.',
        'grp.helpers.',
        'grp.search.index',
        'grp.search.suggestions',
        'grp.search.click',
        'grp.majordomo.redirect_purchase_order',
        'grp.majordomo.redirect_stock_delivery',
        'grp.majordomo.redirect_agent',
        'grp.majordomo.redirect_supplier',
        'grp.media.',
        'grp.pdfs.',
        'grp.gmail.',
        'grp.login.',
        'grp.logout',
        'grp.passkey.',
        'grp.password.',
        'grp.reset.',
        'grp.reset-password.',
        'grp.email.',
        'grp.one_click_unsubscribe',
        'grp.redirect_unsubscribe',
        'grp.unsubscribe-error',
        'grp.fallback',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user      = $request->user();
        $routeName = (string) $request->route()?->getName();

        if ($user instanceof User
            && str_starts_with($routeName, 'grp.')
            && $routeName !== 'grp.'
            && !$this->isOpen($routeName)
            && $user->worksOnlyForAgents()) {
            abort(403);
        }

        return $next($request);
    }

    public function isOpen(string $routeName): bool
    {
        foreach (self::OPEN_SECTIONS as $section) {
            if (str_starts_with($routeName, $section)) {
                return true;
            }
        }

        return false;
    }
}
