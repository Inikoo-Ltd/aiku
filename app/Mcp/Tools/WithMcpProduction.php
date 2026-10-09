<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ProductionPermissionsEnum;
use App\Models\Production\Production;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Production write tools are gated by can_use_mcp_production on the user's account, on top of the
 * R&D permission the same change needs in the UI.
 */
trait WithMcpProduction
{
    protected function resolveProduction(Request $request): Production|Response
    {
        if (!$request->user()?->can_use_mcp_production) {
            return Response::error('Setting up production is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $identifier = strtolower((string) $request->string('production'));
        $production = Production::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();

        if (!$production || !$this->canInProduction($request, $production, ProductionPermissionsEnum::PRODUCTION_RD_VIEW)) {
            $options = Production::orderBy('id')->get(['id', 'slug', 'code', 'name', 'organisation_id'])
                ->filter(fn (Production $production) => $this->canInProduction($request, $production, ProductionPermissionsEnum::PRODUCTION_RD_VIEW))
                ->map(fn (Production $production) => ['slug' => $production->slug, 'code' => $production->code, 'name' => $production->name])
                ->values();

            return $this->notFoundError('production', (string) $request->string('production'), $options, $request);
        }

        return $production;
    }

    protected function canInProduction(Request $request, Production $production, ProductionPermissionsEnum $permission): bool
    {
        return (bool) $request->user()?->can_use_mcp_production
            && $production->canBeSetUpBy($request->user(), $permission === ProductionPermissionsEnum::PRODUCTION_RD_EDIT);
    }

    protected function cannotEditError(Production $production): Response
    {
        return Response::error("This user cannot set up production {$production->code}: that needs group admin, organisation admin, R&D edit on it, or shop admin or shopkeeper in one of its organisation's shops. Nothing was changed.");
    }

    /**
     * Changes that are easy to get wrong by mistake are refused until the user has seen the
     * warning: the assistant must call again naming each warning in accept, so a typo or an
     * oversized selection never lands without a human reading what it does.
     *
     * @param array<string, string> $warnings warning code => what will happen
     */
    protected function unacceptedWarnings(Request $request, array $warnings): ?Response
    {
        $pending = array_diff_key($warnings, array_flip((array) $request->get('accept', [])));
        if (!$pending) {
            return null;
        }

        return Response::error('Nothing was changed. Show the user these warnings in plain words: '
            .collect($pending)->map(fn (string $message, string $code) => "[{$code}] {$message}")->implode(' ')
            .' Only if the user, having read them, says to go ahead, call again with the same arguments plus accept: ['.collect(array_keys($warnings))->map(fn ($code) => "\"{$code}\"")->implode(', ').'].');
    }

    protected function validationError(ValidationException $exception): Response
    {
        return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
    }
}
