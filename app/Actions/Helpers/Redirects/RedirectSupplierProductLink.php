<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectSupplierProductLink extends OrgAction
{
    use WithAgentOrganisationRedirect;

    public function handle(SupplierProduct $supplierProduct, ?User $user = null): RedirectResponse
    {
        if ($user && !$user->authTo('supply-chain.view')) {
            $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->with('organisation')->get()
                ->first(fn (OrgSupplierProduct $orgSupplierProduct) => $user->authTo("procurement.$orgSupplierProduct->organisation_id.view"));

            if ($orgSupplierProduct) {
                return Redirect::to(route('grp.org.procurement.org_supplier_products.show', [$orgSupplierProduct->organisation->slug, $orgSupplierProduct->slug]));
            }

            $agentOrganisation  = $this->getAgentOrganisationForUser($user, $supplierProduct->agent_id);
            $orgSupplierProduct = $agentOrganisation
                ? $supplierProduct->orgSupplierProducts()->whereIn('org_agent_id', OrgAgent::where('agent_id', $supplierProduct->agent_id)->select('id'))->first()
                : null;

            if ($orgSupplierProduct) {
                return Redirect::to(route('grp.org.procurement.org_supplier_products.show', [$agentOrganisation->slug, $orgSupplierProduct->slug]));
            }
        }

        if ($supplierProduct->agent_id) {
            return Redirect::to(route('grp.supply-chain.agents.show.supplier_products.show', [$supplierProduct->agent->slug, $supplierProduct->slug]));
        }

        return Redirect::to(route('grp.supply-chain.supplier_products.show', [$supplierProduct->slug]));
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplierProduct, $request->user());
    }
}
