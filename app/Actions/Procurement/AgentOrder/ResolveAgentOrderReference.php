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
 * like the agent orders before it. A new number is only previewed until its first supplier order is
 * created, which reserves it.
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
            ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
            ->whereNotNull('agent_order_reference')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('purchase_orders as sent')
                ->whereColumn('sent.organisation_id', 'purchase_orders.organisation_id')
                ->whereColumn('sent.agent_id', 'purchase_orders.agent_id')
                ->whereColumn('sent.agent_order_reference', 'purchase_orders.agent_order_reference')
                ->where('sent.state', '!=', PurchaseOrderStateEnum::IN_PROCESS->value)
                ->whereNull('sent.deleted_at'))
            ->orderByDesc('id')
            ->value('agent_order_reference');
    }

    public function newAgentOrderReference(OrgAgent $orgAgent): string
    {
        do {
            $reference = $this->newProcurementReference($orgAgent, SerialReferenceModelEnum::PURCHASE_ORDER);
        } while ($this->agentOrderReferenceIsUsed($orgAgent, $reference));

        return $reference;
    }

    public function upcomingAgentOrderReference(OrgAgent $orgAgent): string
    {
        $serialReference = $this->parentSerialReference($orgAgent, SerialReferenceModelEnum::PURCHASE_ORDER)
            ?? $this->organisationSerialReference($orgAgent, SerialReferenceModelEnum::PURCHASE_ORDER);

        $serial = $serialReference->serial;
        do {
            $reference = sprintf($serialReference->format, ++$serial);
        } while ($this->procurementReferenceIsUsed($orgAgent, SerialReferenceModelEnum::PURCHASE_ORDER, $reference) || $this->agentOrderReferenceIsUsed($orgAgent, $reference));

        return $reference;
    }

    public function previewAgentOrderReference(OrgAgent $orgAgent): string
    {
        return $this->openAgentOrderReference($orgAgent) ?? $this->upcomingAgentOrderReference($orgAgent);
    }

    public function reserveAgentOrderReference(OrgAgent $orgAgent, string $reference): bool
    {
        return $this->previewAgentOrderReference($orgAgent) === $reference && $this->newAgentOrderReference($orgAgent) === $reference;
    }

    private function agentOrderReferenceIsUsed(OrgAgent $orgAgent, string $reference): bool
    {
        return PurchaseOrder::withTrashed()->where('organisation_id', $orgAgent->organisation_id)->where('agent_order_reference', $reference)->exists();
    }
}
