<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * The discontinue tools are gated by an explicit enrolment on the user's account, set in the
 * user's edit page, never by a user id in code. The tools stay registered so an assistant that
 * is not enrolled gets a clear refusal instead of an unknown tool error.
 */
abstract class AikuOrgStockDiscontinueTool extends Tool
{
    use WithMcpPermissions;

    protected function denied(Request $request): ?Response
    {
        if ($request->user()?->can_use_mcp_discontinue) {
            return null;
        }

        return Response::error('Discontinuing SKOs is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
    }

    protected function organisationByCode(Request $request): ?Organisation
    {
        $identifier = strtolower((string) $request->string('organisation'));

        return Organisation::whereRaw('lower(code) = ?', [$identifier])
            ->orWhereRaw('lower(slug) = ?', [$identifier])
            ->first();
    }

    protected function organisationNotFoundError(Request $request): Response
    {
        $codes = Organisation::orderBy('id')->pluck('code')->implode(', ');

        return Response::error("'{$request->string('organisation')}' is not an organisation. Use one of: {$codes}.");
    }

    /**
     * @return array{0: Collection<int, OrgStock>, 1: array<int, string>}
     */
    protected function orgStocksByCodes(Organisation $organisation, array $codes): array
    {
        $codes     = array_values(array_unique(array_map('trim', $codes)));
        $orgStocks = OrgStock::where('organisation_id', $organisation->id)
            ->whereIn('code', $codes)
            ->orderBy('code')
            ->get();

        $missing = array_values(array_diff($codes, $orgStocks->pluck('code')->all()));

        return [$orgStocks, $missing];
    }
}
