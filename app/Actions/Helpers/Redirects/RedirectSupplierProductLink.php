<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectSupplierProductLink extends OrgAction
{
    public function handle(SupplierProduct $supplierProduct): RedirectResponse
    {
        if ($supplierProduct->agent_id) {
            return Redirect::to(route('grp.supply-chain.agents.show.supplier_products.show', [$supplierProduct->agent->slug, $supplierProduct->slug]));
        }

        return Redirect::to(route('grp.supply-chain.supplier_products.show', [$supplierProduct->slug]));
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplierProduct);
    }
}
