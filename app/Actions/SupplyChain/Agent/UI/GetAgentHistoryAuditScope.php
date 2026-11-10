<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentHistoryAuditScope
{
    use AsObject;

    /**
     * @param array{type: string, id: int, label: string} $owner
     * @return array<int, array{type: string, labels: array<int, string>, shops: null}>
     */
    public function handle(Agent $agent, array $owner, ?int $organisationId = null): array
    {
        $typeLabels = AgentPaymentTypeEnum::labels();

        return [
            ['type' => $owner['type'], 'labels' => [$owner['id'] => $owner['label']], 'shops' => null],
            [
                'type'   => 'AgentPayment',
                'labels' => AgentPayment::withTrashed()
                    ->where('agent_id', $agent->id)
                    ->when($organisationId, fn ($query) => $query->where('organisation_id', $organisationId))
                    ->get(['id', 'type', 'date', 'reference'])
                    ->mapWithKeys(fn (AgentPayment $payment) => [
                        $payment->id => trim(($typeLabels[$payment->type->value] ?? $payment->type->value).' '.($payment->reference ?? $payment->date?->format('j M Y'))),
                    ])
                    ->all(),
                'shops'  => null,
            ],
            [
                'type'   => 'DepositRequest',
                'labels' => DepositRequest::where('agent_id', $agent->id)
                    ->when($organisationId, fn ($query) => $query->where('organisation_id', $organisationId))
                    ->get(['id', 'requested_at'])
                    ->mapWithKeys(fn (DepositRequest $depositRequest) => [$depositRequest->id => __('Money request').' '.($depositRequest->requested_at?->format('j M Y') ?? '#'.$depositRequest->id)])
                    ->all(),
                'shops'  => null,
            ],
        ];
    }
}
