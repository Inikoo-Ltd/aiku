<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 15:32:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (AI Assistant)
 * Created: Thu, 04 Dec 2025 15:32:00 Local Time
 */

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateHasBeenInWarehouse;

it('exposes a valid command signature for the hydrator action', function () {
    $action = new OrgStockHydrateHasBeenInWarehouse;

    expect(method_exists($action, 'getCommandSignature'))
        ->toBeTrue();

    $signature = $action->getCommandSignature();

    expect($signature)
        ->toBeString()
        ->not->toBeEmpty()
        ->toStartWith('org_stock:hydrate_has_been_in_warehouse');

    // It should accept an optional argument to enable bulk mode when omitted
    expect($signature)->toContain('{orgStock?');
});
