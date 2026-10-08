<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectAgentSupplierPurchaseOrderLink extends OrgAction
{
    use WithAgentOrganisationRedirect;

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ?User $user = null): RedirectResponse
    {
        if ($user && !$user->authTo('supply-chain.view') && $organisation = $this->getAgentOrganisationForUser($user, $agentSupplierPurchaseOrder->supplier?->agent_id)) {
            return Redirect::to(route('grp.org.procurement.agent_supplier_purchase_orders.show', [$organisation->slug, $agentSupplierPurchaseOrder->slug]));
        }

        return Redirect::to(route('grp.supply-chain.agent_supplier_purchase_orders.show', [$agentSupplierPurchaseOrder->slug]));
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($agentSupplierPurchaseOrder, $request->user());
    }
}
