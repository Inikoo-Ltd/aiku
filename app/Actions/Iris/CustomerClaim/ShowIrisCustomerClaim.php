<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CustomerClaim;

use App\Actions\IrisAction;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisCustomerClaim extends IrisAction
{
    use WithIrisClaimCustomer;

    /**
     * What the customer sees of their claim: its reference, where it is and what was settled. The
     * amount is sent with its currency so the page formats it.
     *
     * @return array<string, mixed>
     */
    public function summary(CustomerClaim $claim): array
    {
        $locale = $claim->shop->language?->code;

        return [
            'ulid'          => $claim->ulid,
            'reference'     => $claim->reference,
            'state'         => $claim->state->value,
            'state_label'   => __($claim->state->label(), [], $locale),
            'resolution'    => $claim->resolution,
            'amount'        => $claim->state === CustomerClaimStateEnum::SETTLED && $claim->resolution === 'refund_to_balance' ? (float) $claim->amount : null,
            'currency_code' => $claim->currency_code,
            'replacement'   => data_get($claim->result, 'replacement'),
            'lines'         => $claim->lines->map(fn ($line) => ['code' => $line->code, 'name' => $line->name, 'quantity' => (float) $line->quantity])->all(),
            'created_at'    => $claim->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(string $ulid, ActionRequest $request): array
    {
        $this->initialisation($request);
        $claim = CustomerClaim::where('customer_id', $this->claimCustomer()->id)->where('ulid', $ulid)->first();
        abort_unless($claim, 404);

        return $this->summary($claim);
    }
}
