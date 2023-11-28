<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 14 Aug 2022 20:33:38 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WebBlockSeeder::class,
            CountrySeeder::class,
            CurrencySeeder::class,
            TimezoneSeeder::class,
            LanguageSeeder::class,
            PermissionSeeder::class,
            JobPositionSeeder::class
        ]);
        Artisan::call('import:tariff_codes harmonized-system.csv');

    }
}
