<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\CreditTransaction;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCreditTransactions;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCreditTransactions;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCreditTransactions;
use App\Models\Accounting\CreditTransaction;

class DeleteCreditTransaction extends OrgAction
{
    public function handle(CreditTransaction $creditTransaction): CreditTransaction
    {
        $customerId = $creditTransaction->customer_id;
        $creditTransaction->delete();

        CustomerHydrateCreditTransactions::run($customerId);
        ShopHydrateCreditTransactions::dispatch($creditTransaction->shop);
        OrganisationHydrateCreditTransactions::dispatch($creditTransaction->organisation);
        GroupHydrateCreditTransactions::dispatch($creditTransaction->group);

        return $creditTransaction;
    }

    public function action(CreditTransaction $creditTransaction): CreditTransaction
    {
        $this->asAction = true;
        $this->initialisationFromShop($creditTransaction->shop, []);
        return $this->handle($creditTransaction);
    }
}
