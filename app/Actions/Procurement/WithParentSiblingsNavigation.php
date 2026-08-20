<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\ActionRequest;

trait WithParentSiblingsNavigation
{
    protected function getParentSiblingsNavigation(mixed $parent, ActionRequest $request): ?array
    {
        $showAction = match (true) {
            $parent instanceof OrgSupplier => ShowOrgSupplier::make(),
            $parent instanceof OrgAgent    => ShowOrgAgent::make(),
            $parent instanceof Supplier    => ShowSupplier::make(),
            $parent instanceof Agent       => ShowAgent::make(),
            default                        => null,
        };

        if (!$showAction) {
            return null;
        }

        return [
            'previous' => $showAction->getPrevious($parent, $request),
            'next'     => $showAction->getNext($parent, $request),
        ];
    }
}
