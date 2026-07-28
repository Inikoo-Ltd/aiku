<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2026 13:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Database\Seeders;

use App\Models\Masters\MasterShop;
use Illuminate\Database\Seeder;

class MasterShopPriceExchangesSeeder extends Seeder
{
    /** @var array<string, array<string, array{is_major: bool, major?: string, exchange?: float, fraction_digits?: int}>> */
    protected array $priceExchanges = [
        'aw'    => [
            'GBP' => ['is_major' => true],
            'EUR' => ['is_major' => true],
            'PLN' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 4.3],
            'CZK' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 25.5, 'fraction_digits' => 0],
            'HUF' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 375, 'fraction_digits' => 0],
            'RON' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 5],
            'SEK' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 11, 'fraction_digits' => 0],
            'UAH' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 51, 'fraction_digits' => 0],
        ],
        'ds'    => [
            'GBP' => ['is_major' => true],
            'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.25],
        ],
        'ac'    => [
            'GBP' => ['is_major' => true],
            'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.18],
        ],
        'ful'   => [
            'GBP' => ['is_major' => true],
            'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.18],
        ],
        'aroma' => [
            'GBP' => ['is_major' => true],
        ],
    ];

    public function run(): void
    {
        foreach ($this->priceExchanges as $masterShopSlug => $priceExchanges) {
            MasterShop::where('slug', $masterShopSlug)->update([
                'price_exchanges' => json_encode($priceExchanges)
            ]);
        }
    }
}
