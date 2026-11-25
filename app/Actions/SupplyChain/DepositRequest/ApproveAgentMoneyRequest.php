<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Actions\OrgAction;
use App\Enums\SupplyChain\DepositRequest\DepositRequestStateEnum;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Sentry;
use Throwable;

/**
 * The client agrees to send the money an agent asked for. Only an approved request can be paid. A request the
 * client's money already with the agent covers in full has nothing to send, so approving it settles it.
 */
class ApproveAgentMoneyRequest extends OrgAction
{
    private DepositRequest $depositRequest;

    /**
     * @throws ValidationException
     */
    public function handle(DepositRequest $depositRequest, ?User $approver): DepositRequest
    {
        return DB::transaction(function () use ($depositRequest, $approver) {
            $depositRequest = DepositRequest::whereKey($depositRequest->id)->lockForUpdate()->firstOrFail();
            if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED) {
                throw ValidationException::withMessages(['request' => __('This request is no longer open.')]);
            }
            if ($depositRequest->isApproved()) {
                throw ValidationException::withMessages(['request' => __('This request is already approved.')]);
            }

            $depositRequest->update([
                'approved_at' => now(),
                'approved_by' => $approver?->id,
            ]);

            if (PayAgentMoneyRequest::outstanding($depositRequest) <= 0.005) {
                $depositRequest->update(['state' => DepositRequestStateEnum::SETTLED, 'settled_at' => now()]);
                $depositRequest->items()->whereNull('paid_at')->update(['paid_at' => now()]);
            }

            return $depositRequest;
        });
    }

    /**
     * The client's users who may approve and pay a request, never the user who asked for it nor an employee of the
     * agent's organisation: its procurement or accounting editors, and the users
     * set to approve and pay on the client's agent page while they may still be (see UpdateOrgAgent::moneyRequestPayerCandidates).
     */
    public static function canPay(DepositRequest $depositRequest, ?User $user): bool
    {
        $clientId = $depositRequest->organisation_id;
        if ($user === null || $clientId === null) {
            return false;
        }

        $agentOrganisationId = $depositRequest->agent?->organisation_id;
        if ($user->id === $depositRequest->created_by || ($agentOrganisationId !== null && $user->employees()->where('employees.organisation_id', $agentOrganisationId)->exists())) {
            return false;
        }

        if ($user->authTo(["procurement.$clientId.edit", "accounting.$clientId.edit"])) {
            return true;
        }

        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);

        return $orgAgent !== null
            && $orgAgent->isMoneyRequestPayer($user)
            && UpdateOrgAgent::moneyRequestPayerCandidates($orgAgent)->whereKey($user->id)->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canPay($this->depositRequest, $request->user());
    }

    /**
     * @throws ValidationException
     */
    public function asController(DepositRequest $depositRequest, ActionRequest $request): DepositRequest
    {
        $this->depositRequest = $depositRequest;
        abort_unless($depositRequest->organisation, 404);
        $this->initialisation($depositRequest->organisation, $request);

        $depositRequest = $this->handle($depositRequest, $request->user());

        NotifyMoneyRequestApproved::run($depositRequest, $request->user());

        $orgAgent = NotifyMoneyRequestCreated::clientOrgAgent($depositRequest);
        if ($orgAgent) {
            try {
                MatchAgentMoneyTransfers::make()->autoRecord($orgAgent);
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
            }
        }

        return $depositRequest;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
