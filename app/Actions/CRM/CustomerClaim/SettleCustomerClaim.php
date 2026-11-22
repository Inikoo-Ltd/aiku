<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Chat\ChatSession\PlanChatClaim;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Settles a customer's claim with the staff Claim panel's actions. The lines are planned again
 * first, so a claim approved late can never credit or send more than is still left to claim.
 */
class SettleCustomerClaim
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerClaim $claim, ?User $reviewer = null): CustomerClaim
    {
        return DB::transaction(function () use ($claim, $reviewer) {
            $claim = CustomerClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($claim->state !== CustomerClaimStateEnum::IN_REVIEW) {
                return $claim;
            }

            $percentage = (float) data_get($claim->result, 'percentage', 100);
            $plan       = PlanChatClaim::make()->planOrder(
                $claim->order,
                $claim->resolution,
                $claim->lines->map(fn ($line) => ['code' => $line->code, 'quantity' => (float) $line->quantity, 'reason' => $line->reason->value])->all(),
                null,
                $percentage
            );

            if ($plan['error']) {
                throw ValidationException::withMessages(['claim' => $plan['error']]);
            }

            $result = PlanChatClaim::make()->settle($claim->order, $claim->resolution, $plan['claimed'], $claim->lines->first()->reason->value, $plan['percentage'], $claim->comment ?: __('Claim :reference', ['reference' => $claim->reference]));

            $claim->update([
                'state'               => CustomerClaimStateEnum::SETTLED,
                'amount'              => $claim->resolution === 'refund_to_balance' ? ($result['credited'] ?? $plan['amount']) : 0,
                'result'              => array_merge($claim->result ?? [], $result),
                'reviewed_by_user_id' => $reviewer?->id,
                'reviewed_at'         => $reviewer ? now() : null,
                'settled_at'          => now(),
            ]);

            return $claim;
        });
    }
}
