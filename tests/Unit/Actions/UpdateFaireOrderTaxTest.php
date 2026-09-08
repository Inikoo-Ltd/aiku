<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\External\Faire\UpdateFaireOrder;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;

function fakeGbpShop(): Shop
{
    $shop = new Shop();
    $shop->setRelation('currency', new Currency(['code' => 'GBP']));

    return $shop;
}

test('sums faire payout taxes into shop currency amount', function () {
    $orderFaireData = [
        'payout_costs' => [
            'taxes' => [
                ['tax_type' => 'VAT', 'value' => ['amount_minor' => 2148, 'currency' => 'GBP']],
            ],
        ],
    ];

    expect(UpdateFaireOrder::make()->getFaireTaxAmount($orderFaireData, fakeGbpShop()))->toBe(21.48);
});

test('sums multiple tax items', function () {
    $orderFaireData = [
        'payout_costs' => [
            'taxes' => [
                ['tax_type' => 'VAT', 'value' => ['amount_minor' => 1000, 'currency' => 'GBP']],
                ['tax_type' => 'RECARGO', 'value' => ['amount_minor' => 250, 'currency' => 'GBP']],
            ],
        ],
    ];

    expect(UpdateFaireOrder::make()->getFaireTaxAmount($orderFaireData, fakeGbpShop()))->toBe(12.50);
});

test('returns zero when faire order has no taxes', function () {
    expect(UpdateFaireOrder::make()->getFaireTaxAmount(['payout_costs' => []], fakeGbpShop()))->toBe(0.0);
});
