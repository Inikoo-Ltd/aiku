<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * A payer takes back what was set as paid from one bank transfer: needs the right to pay every request it settled.
 */
class UndoAgentMoneyBankTransfer extends OrgAction
{
    private OrgAgent $orgAgent;

    private string $key;

    public function authorize(ActionRequest $request): bool
    {
        $requests = DepositRequest::whereIn('id', MatchAgentMoneyTransfers::recordedFromLine($this->orgAgent, $this->key)->select('deposit_request_id'))->with('agent')->get();

        return $requests->isNotEmpty() && $requests->every(fn (DepositRequest $depositRequest) => ApproveAgentMoneyRequest::canPay($depositRequest, $request->user()));
    }

    public function asController(OrgAgent $orgAgent, AgentPayment $agentPayment, ActionRequest $request): RedirectResponse
    {
        abort_unless($agentPayment->organisation_id === $orgAgent->organisation_id && $agentPayment->agent_id === $orgAgent->agent_id, 404);
        $key = Arr::get($agentPayment->data ?? [], MatchAgentMoneyTransfers::LINE_KEY);
        abort_unless($key !== null, 404);
        $this->orgAgent = $orgAgent;
        $this->key      = $key;
        $this->initialisation($orgAgent->organisation, $request);

        MatchAgentMoneyTransfers::make()->undo($orgAgent, $key, $request->user());

        return back();
    }
}
