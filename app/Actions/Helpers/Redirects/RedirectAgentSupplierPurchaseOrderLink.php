<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectAgentSupplierPurchaseOrderLink extends OrgAction
{
    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder): RedirectResponse
    {
        return Redirect::to(route('grp.supply-chain.agent_supplier_purchase_orders.show', [$agentSupplierPurchaseOrder->slug]));
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($agentSupplierPurchaseOrder);
    }
}
