<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Comms\Email\SendCustomerClaimEmail;
use App\Actions\CRM\CustomerClaim\UI\ShowCustomerClaim;
use App\Actions\OrgAction;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Customer services approve a customer's claim from its page: it is settled with the Claim panel's
 * actions, by whoever may settle that outcome on the shop (orders edit for a replacement, CRM edit
 * otherwise).
 */
class ApproveCustomerClaim extends OrgAction
{
    private CustomerClaim $customerClaim;

    public function authorize(ActionRequest $request): bool
    {
        return ShowCustomerClaim::canSettle($this->customerClaim, $request->user());
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerClaim $customerClaim, ActionRequest $request): CustomerClaim
    {
        $this->customerClaim = $customerClaim;
        $this->initialisationFromShop($customerClaim->shop, $request);

        $claim = SettleCustomerClaim::run($customerClaim, $request->user());
        if ($claim->wasChanged('state')) {
            SendCustomerClaimEmail::run($claim);
        }

        return $claim;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
