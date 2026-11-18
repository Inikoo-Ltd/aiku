<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Support\AgentOrganisationSelection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agent pages of a supplier ({supplier}) or supplier product ({supplierProduct}) are addressed by the group
 * slug, one page whatever the number of buying organisations. The shared procurement actions behind them take
 * the org row, so the route is given the org row of the selected organisation (or of the first buying
 * organisation in All) under the parameter name those actions expect, in the same position.
 * An old per-organisation slug (wdi-es) redirects to the group slug with ?organisation=es. The agent organisation's
 * own org rows (suppliers it buys from itself) are pages of the agent too.
 */
class ResolveAgentOrganisationSelection
{
    /**
     * @var array<string, array{0: class-string, 1: string, 2: bool}>
     */
    private const array RECORDS = [
        'supplier'        => [OrgSupplier::class, 'orgSupplier', true],
        'supplierProduct' => [OrgSupplierProduct::class, 'orgSupplierProduct', false],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $route        = $request->route();
        $organisation = $route->parameter('organisation');
        $organisation = $organisation instanceof Organisation ? $organisation : Organisation::where('slug', $organisation)->first();
        $agent        = $organisation?->type === OrganisationTypeEnum::AGENT ? $organisation->agent : null;
        abort_unless($agent, 404);

        $orgAgentIds = OrgAgent::where('agent_id', $agent->id)->pluck('id');

        foreach (self::RECORDS as $parameter => [$orgModel, $orgParameter, $activeOnly]) {
            if (!$route->hasParameter($parameter)) {
                continue;
            }

            $slug = (string) $route->originalParameter($parameter);
            $ofAgent = fn ($query) => $query->where(fn ($query) => $query->whereIn('org_agent_id', $orgAgentIds)->orWhere('organisation_id', $organisation->id));
            $rows    = $orgModel::where($ofAgent)
                ->whereHas($parameter, fn ($query) => $query->where('slug', $slug))
                ->with(['organisation', $parameter])
                ->orderBy('organisation_id')
                ->get();

            if ($rows->isEmpty()) {
                $old = $orgModel::where($ofAgent)->where('slug', $slug)->with(['organisation', $parameter])->first();
                abort_unless($old?->{$parameter}, 404);

                return redirect()->to($this->url($request, $route, [$parameter => $old->{$parameter}->slug], $old->organisation->slug));
            }

            $listed   = $activeOnly ? $rows->where('status', true)->values() : $rows;
            $listed   = $listed->isEmpty() ? $rows : $listed;
            $selected = $listed->first(fn ($row) => $row->organisation->slug === $request->query('organisation'));

            $selection = new AgentOrganisationSelection(
                rows: $listed,
                pageRow: $selected ?? $listed->first(),
                selected: $selected?->organisation,
                slug: $slug,
            );
            $request->attributes->set(AgentOrganisationSelection::ATTRIBUTE, $selection);

            $this->replaceParameter($route, $parameter, $orgParameter, $selection->pageRow);
        }

        return $next($request);
    }

    private function replaceParameter(Route $route, string $parameter, string $orgParameter, mixed $value): void
    {
        $parameters = $route->parameters();
        foreach (array_keys($parameters) as $name) {
            $route->forgetParameter($name);
        }

        foreach ($parameters as $name => $current) {
            $route->setParameter($name === $parameter ? $orgParameter : $name, $name === $parameter ? $value : $current);
        }
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function url(Request $request, Route $route, array $replace, string $organisationSlug): string
    {
        $name = (string) $route->getName();
        if (str_starts_with($name, SeparateAgentRoutes::PROCUREMENT_PREFIX)) {
            $name = SeparateAgentRoutes::agentRouteName($name) ?? $name;
        }

        $query = array_merge($request->query(), ['organisation' => $organisationSlug]);

        return route($name, array_merge($route->originalParameters(), $replace)).'?'.http_build_query($query);
    }
}
