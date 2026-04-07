<?php

/*
 * Author: Wikan Wahyu <wikan@inikoo.com>
 * Created: Mon, 07 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Inventory\GroupStockHistory\Hydrators\GroupStockHistoryHydrateFromOrgStockHistories;
use App\Models\Inventory\GroupStockHistory;
use Illuminate\Support\Facades\DB;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group        = group();
    $this->organisation = createOrganisation();
});

it('aggregates number_locations, number_out_of_stock_org_stocks and grp_value_dormant_stock_1y from organisation stock histories', function () {
    $testDate = '2020-01-15';

    $groupStockHistory = GroupStockHistory::firstOrCreate(
        ['group_id' => $this->group->id, 'date' => $testDate],
    );

    DB::table('organisation_stock_histories')->updateOrInsert(
        ['organisation_id' => $this->organisation->id, 'date' => $testDate],
        [
            'group_id'                       => $this->group->id,
            'grp_stock_value'                => 1000,
            'number_org_stocks'              => 10,
            'number_locations'               => 5,
            'number_out_of_stock_org_stocks' => 2,
            'value_dormant_stock_1y'         => 300,
            'group_stock_history_id'         => $groupStockHistory->id,
            'updated_at'                     => now(),
        ]
    );

    $expected = DB::table('organisation_stock_histories')
        ->selectRaw('sum(grp_stock_value) as grp_stock_values')
        ->selectRaw('sum(number_locations) as number_locations')
        ->selectRaw('sum(number_out_of_stock_org_stocks) as number_out_of_stock_org_stocks')
        ->selectRaw('sum(value_dormant_stock_1y) as grp_value_dormant_stock_1y')
        ->where('group_stock_history_id', $groupStockHistory->id)
        ->first();

    $expectedPercentage = $expected->grp_stock_values > 0
        ? round($expected->grp_value_dormant_stock_1y / $expected->grp_stock_values * 100, 2)
        : 0;

    GroupStockHistoryHydrateFromOrgStockHistories::run($groupStockHistory->id);

    $groupStockHistory->refresh();

    expect($groupStockHistory->number_locations)->toBe((int) $expected->number_locations)
        ->and($groupStockHistory->number_out_of_stock_org_stocks)->toBe((int) $expected->number_out_of_stock_org_stocks)
        ->and((float) $groupStockHistory->grp_value_dormant_stock_1y)->toBe((float) $expected->grp_value_dormant_stock_1y)
        ->and((float) $groupStockHistory->grp_stock_value)->toBe((float) $expected->grp_stock_values)
        ->and($groupStockHistory->percentage_value_dormant_stock_1y)->toBe($expectedPercentage);
});

it('handles null group stock history id gracefully', function () {
    GroupStockHistoryHydrateFromOrgStockHistories::run(null);
})->throwsNoExceptions();

it('handles non-existent group stock history id gracefully', function () {
    GroupStockHistoryHydrateFromOrgStockHistories::run(32767);
})->throwsNoExceptions();
