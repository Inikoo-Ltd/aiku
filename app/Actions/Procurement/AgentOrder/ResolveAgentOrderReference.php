<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder;

use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The agent order a new supplier order through the agent joins: the one still being prepared (all
 * its supplier orders in process), as the single org-agent order used to be, or a new one numbered
 * like the agent orders before it.
 */
class ResolveAgentOrderReference
{
    use AsAction;
    use WithProcurementSerialReferences;

    public function handle(OrgAgent $orgAgent): string
    {
        return $this->openAgentOrderReference($orgAgent) ?? $this->newAgentOrderReference($orgAgent);
    }

    public function openAgentOrderReference(OrgAgent $orgAgent): ?string
    {
        return PurchaseOrder::query()
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('parent_type', 'OrgSupplier')
            ->whereNotNull('agent_order_reference')
            ->groupBy('agent_order_reference')
            ->havingRaw('bool_and(state = ?)', [PurchaseOrderStateEnum::IN_PROCESS->value])
            ->orderByRaw('max(id) desc')
            ->value('agent_order_reference');
    }

    public function newAgentOrderReference(OrgAgent $orgAgent): string
    {
        do {
            $reference = $this->newProcurementReference($orgAgent, SerialReferenceModelEnum::PURCHASE_ORDER);
        } while (PurchaseOrder::withTrashed()->where('organisation_id', $orgAgent->organisation_id)->where('agent_order_reference', $reference)->exists());

        return $reference;
    }
}
