<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithCustomerBalanceAuthorisation
{
    /**
     * Moving a customer's credit balance is an accounting act as much as a CRM one,
     * the accounts office does the refunds.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(
            [
                "crm.{$this->shop->id}.edit",
                "accounting.{$this->organisation->id}.edit",
            ]
        );
    }
}
