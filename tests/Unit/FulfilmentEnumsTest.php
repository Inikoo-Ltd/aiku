<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemNoSetReasonStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditScopeEnum;
use App\Models\Fulfilment\FulfilmentCustomer;

test('fulfilment enum labels and icons are keyed by the case values, INI-292', function (string $enum) {
    $values = array_column($enum::cases(), 'value');

    expect(array_keys($enum::labels()))->toEqualCanonicalizing($values)
        ->and(array_keys($enum::stateIcon()))->toEqualCanonicalizing($values);
})->with([
    StoredItemAuditScopeEnum::class,
    PalletReturnItemNoSetReasonStateEnum::class,
]);

test('pallet state count reads the stats column of every state, INI-292', function () {
    $fulfilmentCustomer = new FulfilmentCustomer();
    foreach (PalletStateEnum::cases() as $index => $case) {
        $fulfilmentCustomer->setAttribute('number_pallets_state_'.$case->value, $index + 1);
    }

    $counts = PalletStateEnum::count($fulfilmentCustomer);

    expect(array_keys($counts))->toBe(array_column(PalletStateEnum::cases(), 'value'))
        ->and($counts[PalletStateEnum::DISPATCHED->value])->toBe(array_search(PalletStateEnum::DISPATCHED, PalletStateEnum::cases()) + 1)
        ->and($counts[PalletStateEnum::OTHER_INCIDENT->value])->toBe(array_search(PalletStateEnum::OTHER_INCIDENT, PalletStateEnum::cases()) + 1);
});
