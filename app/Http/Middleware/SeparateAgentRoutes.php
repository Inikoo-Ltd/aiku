<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * An agent organisation is a closed garden: it is served only by the grp.org.agent.* routes, and those routes serve
 * nothing else. A shared procurement page asked for an agent organisation is redirected to its agent twin when there
 * is one and refused when there is not, so a partner or shop page can never be reached through an agent.
 *
 * The actions behind the agent routes are the procurement ones and pick breadcrumbs and links by route name, so while
 * an agent route is handled it answers to its procurement twin's name; the browser then maps those links back to the
 * agent routes (useAgentRoutes.ts).
 */
class SeparateAgentRoutes
{
    public const string PROCUREMENT_PREFIX = 'grp.org.procurement.';

    public const string AGENT_PREFIX = 'grp.org.agent.';

    public function handle(Request $request, Closure $next): Response
    {
        $route        = $request->route();
        $routeName    = (string) $route?->getName();
        $organisation = $route?->parameter('organisation');
        $organisationType = $organisation instanceof Organisation ? $organisation->type : Organisation::where('slug', $organisation)->value('type');
        $isAgent          = $organisationType === OrganisationTypeEnum::AGENT || $organisationType === OrganisationTypeEnum::AGENT->value;

        if (str_starts_with($routeName, self::AGENT_PREFIX)) {
            abort_unless($isAgent, 404);

            $procurementRouteName = self::PROCUREMENT_PREFIX.substr($routeName, strlen(self::AGENT_PREFIX));
            if (!Route::has($procurementRouteName)) {
                return $next($request);
            }

            $action = $route->getAction();
            $route->setAction(array_merge($action, ['as' => $procurementRouteName]));

            try {
                return $next($request);
            } finally {
                $route->setAction($action);
            }
        }

        if ($isAgent && str_starts_with($routeName, self::PROCUREMENT_PREFIX)) {
            $agentRouteName = self::agentRouteName($routeName);

            abort_unless($request->isMethod('GET') && $agentRouteName, 404);

            return redirect()->to(route($agentRouteName, $route->originalParameters()).($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        return $next($request);
    }

    public static function agentRouteName(string $procurementRouteName): ?string
    {
        if ($procurementRouteName === self::PROCUREMENT_PREFIX.'dashboard') {
            return 'grp.org.dashboard.show';
        }

        $agentRouteName = self::AGENT_PREFIX.substr($procurementRouteName, strlen(self::PROCUREMENT_PREFIX));

        return Route::has($agentRouteName) ? $agentRouteName : null;
    }
}
