<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\DepositRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent sends the money request email again to the client's payers and copied users, and remembers when.
 */
class RemindAgentMoneyRequest extends OrgAction
{
    private DepositRequest $depositRequest;

    /**
     * @throws ValidationException
     */
    public function handle(DepositRequest $depositRequest, ?int $userId = null): DepositRequest
    {
        if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED) {
            throw ValidationException::withMessages(['request' => __('Only an open money request can be reminded.')]);
        }

        NotifyMoneyRequestCreated::run($depositRequest, true);

        $depositRequest->update([
            'last_reminded_at' => now(),
            'last_reminded_by' => $userId,
            'number_reminders' => $depositRequest->number_reminders + 1,
        ]);

        return $depositRequest;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->depositRequest->agent->organisation_id}.edit");
    }

    /**
     * @throws ValidationException
     */
    public function asController(DepositRequest $depositRequest, ActionRequest $request): DepositRequest
    {
        $this->depositRequest = $depositRequest;
        $this->initialisation($depositRequest->agent->organisation, $request);

        return $this->handle($depositRequest, $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
