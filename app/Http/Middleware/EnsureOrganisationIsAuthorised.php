<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($organisation instanceof Organisation && $organisation->type === OrganisationTypeEnum::AGENT) {
            $this->keepRecordsInsideTheAgent($request, $organisation);
        }

        return $next($request);
    }

    /**
     * An agent works on records of the organisations it buys for, so its pages take records whose organisation is
     * not the agent's own. Each one must belong to the agent's organisation or to the agent itself; any other is
     * not found, so changing a slug in the url never opens another agent's suppliers or orders (HELP-3654).
     * Agent labels open our SKOs, which carry no agent: that page keeps the agent to the SKOs it buys for us itself.
     */
    private function keepRecordsInsideTheAgent(Request $request, Organisation $organisation): void
    {
        if (str_starts_with((string) $request->route()->getName(), 'grp.org.procurement.agent_labels.')) {
            return;
        }

        $agentId = $organisation->agent?->id;

        foreach ($request->route()->parameters() as $record) {
            if (!$record instanceof Model || $record instanceof Organisation) {
                continue;
            }

            $attributes = $record->getAttributes();

            if (array_key_exists('organisation_id', $attributes) && $attributes['organisation_id'] === $organisation->id) {
                continue;
            }

            $recordAgentId = $this->getRecordAgentId($attributes);

            if ($recordAgentId === null && !array_key_exists('organisation_id', $attributes)) {
                continue;
            }

            if ($agentId === null || $recordAgentId !== $agentId) {
                abort(404);
            }
        }
    }

    private function getRecordAgentId(array $attributes): ?int
    {
        if (isset($attributes['agent_id'])) {
            return $attributes['agent_id'];
        }

        if (isset($attributes['org_agent_id'])) {
            return DB::table('org_agents')->where('id', $attributes['org_agent_id'])->value('agent_id');
        }

        if (isset($attributes['supplier_id']) && !array_key_exists('organisation_id', $attributes)) {
            return DB::table('suppliers')->where('id', $attributes['supplier_id'])->value('agent_id');
        }

        return null;
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
