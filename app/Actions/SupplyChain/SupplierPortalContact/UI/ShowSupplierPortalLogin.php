<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact\UI;

use App\Actions\SupplyChain\SupplierPortalContact\SendSupplierPortalLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class ShowSupplierPortalLogin
{
    use AsAction;

    public function asController(Request $request): Response
    {
        return Inertia::render('SupplyChain/SupplierPortalLogin', [
            'title'          => __('Supplier sign in'),
            'link_requested' => (bool) session('link_requested'),
            'days'           => SendSupplierPortalLink::DAYS,
            'routes'         => ['send' => route('grp.supplier_portal.send_link')],
        ])->toResponse($request)->withHeaders([
            'Cache-Control'   => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag'    => 'noindex, nofollow',
        ]);
    }
}
