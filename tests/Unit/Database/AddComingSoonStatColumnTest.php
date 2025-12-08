<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 11:16:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 11:18:00 Local Time
 */

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;

// Boot Laravel for this Unit test so facades are available
uses(TestCase::class);

it('adds number_products_state_coming_soon to all stats tables', function () {
    // Ensure this specific migration has been executed in the current env
    $migrationPath = base_path('database/migrations/2025_12_04_111500_add_number_products_state_coming_soon_to_stats_tables.php');
    if (file_exists($migrationPath)) {
        $migration = require $migrationPath; // returns the Migration instance
        // Run it idempotently; it internally checks for existing columns/tables
        $migration->up();
    }

    $tables = [
        'collection_stats',
        'collection_category_stats',
        'product_category_stats',
        'master_product_category_stats',
        'shop_stats',
        'organisation_catalogue_stats',
        'group_catalogue_stats',
        'platform_stats',
        'trade_unit_stats',
    ];

    foreach ($tables as $table) {
        if (! Schema::hasTable($table)) {
            // Some installs may not include all tables; skip gracefully
            continue;
        }

        expect(Schema::hasColumn($table, 'number_products_state_coming_soon'))
            ->toBeTrue();
    }
});
