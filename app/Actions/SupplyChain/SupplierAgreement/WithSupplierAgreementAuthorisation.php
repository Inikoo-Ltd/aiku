<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Actions\Procurement\WithAgreedTermsAuthorisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Supplier Agreement is the group's, so drafting and publishing it is for procurement supervisors of any buying
 * organisation of the group.
 */
trait WithSupplierAgreementAuthorisation
{
    use WithAgreedTermsAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->isProcurementSupervisor($request->user(), $this->group);
    }
}
