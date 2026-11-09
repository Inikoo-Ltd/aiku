<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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
 * The agent withdraws a money request nothing has been paid against, so its lines can be asked for again.
 */
class CancelAgentMoneyRequest extends OrgAction
{
    private DepositRequest $depositRequest;

    /**
     * @throws ValidationException
     */
    public function handle(DepositRequest $depositRequest): DepositRequest
    {
        return DB::transaction(function () use ($depositRequest) {
            $depositRequest = DepositRequest::whereKey($depositRequest->id)->lockForUpdate()->firstOrFail();
            if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED || $depositRequest->transfers()->exists()) {
                throw ValidationException::withMessages(['request' => __('Only a request nothing has been paid against can be cancelled.')]);
            }
            if ($depositRequest->isApproved()) {
                throw ValidationException::withMessages(['request' => __('The client already approved this request, so it can not be cancelled.')]);
            }

            $depositRequest->update([
                'state'        => DepositRequestStateEnum::CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $depositRequest;
        });
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

        $depositRequest = $this->handle($depositRequest);

        NotifyMoneyRequestCancelled::run($depositRequest, $request->user());

        return $depositRequest;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
