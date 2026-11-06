<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 15:02
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\DepositRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The client takes back its approval of a money request nothing has been paid against yet.
 */
class UnapproveAgentMoneyRequest extends OrgAction
{
    private DepositRequest $depositRequest;

    /**
     * @throws ValidationException
     */
    public function handle(DepositRequest $depositRequest): DepositRequest
    {
        return DB::transaction(function () use ($depositRequest) {
            $depositRequest = DepositRequest::whereKey($depositRequest->id)->lockForUpdate()->firstOrFail();
            if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED || !$depositRequest->isApproved()) {
                throw ValidationException::withMessages(['request' => __('This request is not approved.')]);
            }
            if ($depositRequest->transfers()->exists()) {
                throw ValidationException::withMessages(['request' => __('Money was already sent against this request, so its approval can not be cancelled.')]);
            }

            $depositRequest->update([
                'approved_at' => null,
                'approved_by' => null,
            ]);

            return $depositRequest;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return ApproveAgentMoneyRequest::canPay($this->depositRequest, $request->user());
    }

    /**
     * @throws ValidationException
     */
    public function asController(DepositRequest $depositRequest, ActionRequest $request): DepositRequest
    {
        $this->depositRequest = $depositRequest;
        abort_unless($depositRequest->organisation, 404);
        $this->initialisation($depositRequest->organisation, $request);

        $depositRequest = $this->handle($depositRequest);

        NotifyMoneyRequestUnapproved::run($depositRequest, $request->user());

        return $depositRequest;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
