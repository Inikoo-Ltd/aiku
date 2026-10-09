<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI;

use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowAgentSupplierPurchaseOrder
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::where('agent_supplier_purchase_order_id', $agentSupplierPurchaseOrder->id)->first();

        abort_if(
            !$purchaseOrder
            || !($request->user()->authTo("procurement.$purchaseOrder->organisation_id.view") || $request->user()->authTo('supply-chain.view')),
            404
        );

        return Redirect::route('grp.org.procurement.purchase_orders.show', [
            'organisation'  => $purchaseOrder->organisation->slug,
            'purchaseOrder' => $purchaseOrder->slug,
        ]);
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        return $this->handle($agentSupplierPurchaseOrder, $request);
    }

    public function inOrganisation(Organisation $organisation, AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        return $this->handle($agentSupplierPurchaseOrder, $request);
    }
}
