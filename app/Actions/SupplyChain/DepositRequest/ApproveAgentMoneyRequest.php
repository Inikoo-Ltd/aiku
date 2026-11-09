<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

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
     * The client's users who may approve and pay a request: its procurement or accounting editors, and the users
     * set to approve and pay on the client's agent page.
     */
    public static function canPay(DepositRequest $depositRequest, ?User $user): bool
    {
        $clientId = $depositRequest->organisation_id;
        if ($user === null || $clientId === null) {
            return false;
        }

        return $user->authTo(["procurement.$clientId.edit", "accounting.$clientId.edit"])
            || (bool) NotifyMoneyRequestCreated::clientOrgAgent($depositRequest)?->isMoneyRequestPayer($user);
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

        return $depositRequest;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
