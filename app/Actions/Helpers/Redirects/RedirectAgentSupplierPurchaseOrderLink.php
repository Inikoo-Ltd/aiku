<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\ShowAgentSupplierPurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
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

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): RedirectResponse
    {
        return ShowAgentSupplierPurchaseOrder::make()->handle($agentSupplierPurchaseOrder, $request);
    }
}
