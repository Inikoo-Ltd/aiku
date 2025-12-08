<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 09:14:15 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Supplier;

class StoreOrgSupplierFromSupplierInAgent extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, array $modelData = []): void
    {
        if (! $supplier->agent_id) {
            return;
        }

        foreach ($supplier->agent->orgAgents as $orgAgent) {
            StoreOrgSupplier::make()->action(
                $orgAgent,
                $supplier,
                $modelData,
                hydratorsDelay: $this->hydratorsDelay,
                strict: $this->strict
            );
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Supplier $supplier, array $modelData = [], $hydratorsDelay = 0, bool $strict = true): void
    {

        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->handle($supplier, $modelData);
    }
}
