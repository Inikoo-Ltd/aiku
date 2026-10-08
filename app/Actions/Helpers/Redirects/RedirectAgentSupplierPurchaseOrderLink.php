<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectAgentSupplierPurchaseOrderLink
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ?User $user = null): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::where('agent_supplier_purchase_order_id', $agentSupplierPurchaseOrder->id)->first();

        abort_if(!$purchaseOrder, 404);

        return RedirectPurchaseOrderLink::make()->handle($purchaseOrder, $user);
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        return $this->handle($agentSupplierPurchaseOrder, $request->user());
    }
}
