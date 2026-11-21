<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Models\Accounting\BankTransaction;
use App\Models\Procurement\OrgAgent;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The client's payer confirms that a transfer its bank sent the agent pays the approved money requests.
 */
class PayAgentMoneyRequestsFromBank extends OrgAction
{
    private OrgAgent $orgAgent;

    public function rules(): array
    {
        return ['amount' => ['required', 'numeric', 'gt:0']];
    }

    public function authorize(ActionRequest $request): bool
    {
        return PayAgentMoneyRequests::canPayAll($this->orgAgent, $request->user());
    }

    public function asController(OrgAgent $orgAgent, BankTransaction $bankTransaction, ActionRequest $request): RedirectResponse
    {
        $this->orgAgent = $orgAgent;
        $this->initialisation($orgAgent->organisation, $request);
        abort_unless($bankTransaction->organisation_id === $orgAgent->organisation_id, 404);

        MatchAgentMoneyTransfers::make()->payFromLine($orgAgent, $bankTransaction, $request->user(), (float) $this->validatedData['amount']);

        return back();
    }
}
