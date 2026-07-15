<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectSupplierLink extends GrpAction
{
    public function handle(Supplier $supplier): RedirectResponse
    {
        return Redirect::to(route('grp.supply-chain.suppliers.show', [$supplier->slug]));
    }

    public function asController(Supplier $supplier, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($supplier);
    }
}
