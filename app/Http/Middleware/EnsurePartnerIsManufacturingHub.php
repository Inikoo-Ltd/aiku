<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\Procurement\OrgPartner;
use Closure;
use Illuminate\Http\Request;

/**
 * Only a manufacturing hub (aroma) sells to its partners through the shopping list; every other
 * partner is bought from with a normal purchase order.
 */
class EnsurePartnerIsManufacturingHub
{
    public function handle(Request $request, Closure $next)
    {
        $orgPartner = $request->route('orgPartner');

        abort_unless($orgPartner instanceof OrgPartner && $orgPartner->partner->is_manufacturing_hub, 404);

        return $next($request);
    }
}
