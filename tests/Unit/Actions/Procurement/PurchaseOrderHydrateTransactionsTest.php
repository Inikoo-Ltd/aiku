<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Actions\Procurement;

use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Decorators\UniqueJobDecorator;

test('the transactions hydrator locks per purchase order rather than globally', function () {
    $first  = new PurchaseOrder();
    $second = new PurchaseOrder();

    $first->id  = 11;
    $second->id = 22;

    $firstLock  = (new UniqueJobDecorator(PurchaseOrderHydrateTransactions::class, $first))->uniqueId();
    $secondLock = (new UniqueJobDecorator(PurchaseOrderHydrateTransactions::class, $second))->uniqueId();

    expect($firstLock)->not->toBe($secondLock)
        ->and($firstLock)->toContain('11')
        ->and($secondLock)->toContain('22');
});
