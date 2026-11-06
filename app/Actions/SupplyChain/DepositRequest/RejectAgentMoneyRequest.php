<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:48
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
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The client refuses a money request nothing has been paid against, saying why. The agent can ask again.
 */
class RejectAgentMoneyRequest extends OrgAction
{
    private DepositRequest $depositRequest;

    /**
     * @param  array{reason: string}  $modelData
     *
     * @throws ValidationException
     */
    public function handle(DepositRequest $depositRequest, array $modelData, ?User $rejecter): DepositRequest
    {
        return DB::transaction(function () use ($depositRequest, $modelData, $rejecter) {
            $depositRequest = DepositRequest::whereKey($depositRequest->id)->lockForUpdate()->firstOrFail();
            if ($error = self::rejectionError($depositRequest)) {
                throw ValidationException::withMessages(['request' => $error]);
            }

            $depositRequest->update([
                'state'            => DepositRequestStateEnum::REJECTED,
                'rejected_at'      => now(),
                'rejected_by'      => $rejecter?->id,
                'rejection_reason' => trim($modelData['reason']),
            ]);

            return $depositRequest;
        });
    }

    public static function rejectionError(DepositRequest $depositRequest): ?string
    {
        if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED) {
            return __('This request is no longer open.');
        }

        if ($depositRequest->transfers()->exists()) {
            return __('Money was already sent against this request, so it can not be rejected.');
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($error = self::rejectionError($this->depositRequest)) {
            $validator->errors()->add('request', $error);
        }
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

        $depositRequest = $this->handle($depositRequest, $this->validatedData, $request->user());

        NotifyMoneyRequestRejected::run($depositRequest, $request->user());

        return $depositRequest;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
