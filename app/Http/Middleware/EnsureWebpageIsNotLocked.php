<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\Web\Webpage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Guards every write route of a webpage and of its web blocks: a locked webpage only accepts
 * changes from its lock owner or from a user holding a live edit grant.
 * The message travels as a validation error so both Inertia forms and axios callers show it.
 */
class EnsureWebpageIsNotLocked
{
    public function handle(Request $request, Closure $next)
    {
        $webpage = $request->route('webpage') ?? $request->route('modelHasWebBlocks')?->webpage;

        if ($webpage instanceof Webpage && !$webpage->canBeEditedBy($request->user())) {
            throw ValidationException::withMessages(['message' => $webpage->lockMessage()]);
        }

        return $next($request);
    }
}
