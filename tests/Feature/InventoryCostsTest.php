<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use Config;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/InventoryHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(
    function () {
        $this->organisation = createOrganisation();
        $this->group        = group();
        setPermissionsTeamId($this->group->id);
        $this->guest = createAdminGuest($this->group);

        $this->artisan('warehouse:seed-permissions')->assertExitCode(0);

        Config::set("inertia.pages.paths", [resource_path("js/Pages/Grp")]);
        $this->user = $this->guest->getUser();
        actingAs($this->user);
    }
);

describe('aurora provisional cost fix', function () {
    test('repair command fixes provisional purchase costs from delivery items', function () {
        [$orgStock, $location]   = costFixStockInLocation($this->group, $this->organisation, 'CFA');
        [$orgStock2, $location2] = costFixStockInLocation($this->group, $this->organisation, 'CFB');

        $badMovement = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 100,
        ]);
        $badMovement->update([
            'org_amount'   => 790500,
            'cost_per_sku' => 7905,
            'note'         => 'received from <span onClick="change_view(\'delivery/15501\')">CF15501</span>',
            'date'         => now()->subDays(30),
        ]);
        costFixStoreDelivery($this->group, $this->organisation, 15501, [['org_stock_id' => $orgStock->id, 'unit_quantity' => 100, 'net_amount' => 550]]);

        $unitsMismatchMovement = StoreOrgStockMovement::make()->action($orgStock2, $location2, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $unitsMismatchMovement->update([
            'org_amount'   => 10000,
            'cost_per_sku' => 1000,
            'note'         => 'received from <span onClick="change_view(\'delivery/15502\')">CF15502</span>',
            'date'         => now()->subDays(30),
        ]);
        costFixStoreDelivery($this->group, $this->organisation, 15502, [['org_stock_id' => $orgStock2->id, 'unit_quantity' => 10000, 'net_amount' => 1000]]);

        $this->artisan('org_stock_movement:repair_cost_from_stock_delivery_items', ['--dry-run' => true])->assertExitCode(0);
        $badMovement->refresh();
        expect((float) $badMovement->cost_per_sku)->toBe(7905.0)
            ->and($badMovement->cost_status)->toBeNull();

        $this->artisan('org_stock_movement:repair_cost_from_stock_delivery_items')->assertExitCode(0);

        $badMovement->refresh();
        $unitsMismatchMovement->refresh();
        expect((float) $badMovement->cost_per_sku)->toBe(5.5)
            ->and((float) $badMovement->org_amount)->toBe(550.0)
            ->and($badMovement->cost_status)->toBe(\App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum::DELIVERY)
            ->and((float) $unitsMismatchMovement->cost_per_sku)->toBe(1000.0)
            ->and($unitsMismatchMovement->cost_status)->toBeNull();

        $snapshot = DB::table('org_stock_movements_pre_costfix')->where('id', $badMovement->id)->first();
        expect($snapshot)->not->toBeNull()
            ->and((float) $snapshot->org_amount)->toBe(790500.0);
    });

    test('recompute keeps pre-cutoff lpp untouched while wac and fifo change', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFC');

        $this->organisation->update(['wac_calculations_start_date' => '2025-08-01']);
        $orgStock->refresh()->unsetRelation('organisation');

        $movement = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $movement->update([
            'org_amount'   => 79050,
            'cost_per_sku' => 7905,
            'date'         => '2026-07-01 10:00:00',
        ]);

        $preCutoffDate  = \Illuminate\Support\Carbon::parse('2026-05-15');
        $postCutoffDate = \Illuminate\Support\Carbon::parse('2026-07-15');
        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories::run($orgStock, $preCutoffDate);
        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories::run($orgStock, $postCutoffDate);

        $preRow = DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('date', '2026-05-15')->first();
        expect((float) $preRow->lpp_per_sku)->toBe(7905.0)
            ->and((float) $preRow->wac_per_sku)->toBe(7905.0);

        $movement->update([
            'cost_per_sku' => 5.5,
            'org_amount'   => 55,
            'cost_status'  => 'delivery',
        ]);

        $this->artisan('org_stock_movement:recalculate_histories_post_costfix', ['organisation' => $this->organisation->slug, '--sync' => true])->assertExitCode(0);

        $preRow  = DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('date', '2026-05-15')->first();
        $postRow = DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('date', '2026-07-15')->first();

        expect((float) $preRow->lpp_per_sku)->toBe(7905.0)
            ->and((float) $preRow->wac_per_sku)->toBe(5.5)
            ->and((float) $preRow->fifo_per_sku)->toBe(5.5)
            ->and((float) $postRow->lpp_per_sku)->toBe(5.5)
            ->and((float) $postRow->wac_per_sku)->toBe(5.5);

        expect((float) $orgStock->refresh()->sku_value)->toBe(5.5);
    });

    test('fifo ignores the counted level an Aurora audit carries', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFAA');

        $this->organisation->update(['wac_calculations_start_date' => '2025-08-01']);
        $orgStock->refresh()->unsetRelation('organisation');

        $movements = [
            [OrgStockMovementTypeEnum::PURCHASE, 10, 1, '2026-07-01 10:00:00', null],
            [OrgStockMovementTypeEnum::AUDIT, 10, null, '2026-07-02 10:00:00', $this->organisation->id.':990001'],
            [OrgStockMovementTypeEnum::PICKED, -10, null, '2026-07-03 10:00:00', null],
            [OrgStockMovementTypeEnum::PURCHASE, 10, 3, '2026-07-04 10:00:00', null],
        ];
        foreach ($movements as [$type, $quantity, $costPerSku, $date, $sourceId]) {
            $movement = StoreOrgStockMovement::make()->action($orgStock, $location, [
                'type'     => $type->value,
                'quantity' => $quantity,
            ]);
            $movement->update([
                'cost_per_sku' => $costPerSku,
                'org_amount'   => $costPerSku === null ? 0 : $costPerSku * $quantity,
                'date'         => $date,
                'source_id'    => $sourceId,
            ]);
        }

        expect(StoreOrgStockMovement::make()->getFifoPerSku($orgStock->refresh(), \Illuminate\Support\Carbon::parse('2026-07-05')))->toBe(3.0);
    });

    test('recompute leaves rows before the first repaired movement alone', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFD');

        $this->organisation->update(['wac_calculations_start_date' => '2025-08-01']);
        $orgStock->refresh()->unsetRelation('organisation');

        $oldPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $oldPurchase->update(['cost_per_sku' => 3, 'org_amount' => 30, 'date' => '2025-07-01 10:00:00']);

        $repaired = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $repaired->update(['cost_per_sku' => 7905, 'org_amount' => 79050, 'date' => '2026-07-01 10:00:00']);

        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories::run($orgStock, \Illuminate\Support\Carbon::parse('2026-05-15'));
        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories::run($orgStock, \Illuminate\Support\Carbon::parse('2026-07-15'));

        $repaired->update(['cost_per_sku' => 5.5, 'org_amount' => 55, 'cost_status' => 'delivery']);

        $this->artisan('org_stock_movement:recalculate_histories_post_costfix', ['organisation' => $this->organisation->slug, '--sync' => true])->assertExitCode(0);

        $preRow  = DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('date', '2026-05-15')->first();
        $postRow = DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('date', '2026-07-15')->first();

        expect((float) $preRow->lpp_per_sku)->toBe(3.0)
            ->and((float) $preRow->wac_per_sku)->toBe(3.0)
            ->and((float) $postRow->wac_per_sku)->toBe(4.25)
            ->and((float) $postRow->lpp_per_sku)->toBe(5.5);
    });

    test('recompute picks up purchases costed from aurora', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFCOSTED');

        $this->organisation->update(['wac_calculations_start_date' => '2025-08-01']);
        $orgStock->refresh()->unsetRelation('organisation');

        $purchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $purchase->update(['cost_per_sku' => 20.44, 'org_amount' => 204.4, 'date' => '2026-06-10 10:00:00']);

        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories::run($orgStock, \Illuminate\Support\Carbon::parse('2026-06-17'));

        $purchase->update(['cost_per_sku' => 0.11, 'org_amount' => 1.1, 'cost_status' => 'costed']);

        $this->artisan('org_stock_movement:recalculate_histories_post_costfix', ['organisation' => $this->organisation->slug, '--sync' => true])->assertExitCode(0);

        $row = DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('date', '2026-06-17')->first();

        expect((float) $row->lpp_per_sku)->toBe(0.11)
            ->and((float) $row->wac_per_sku)->toBe(0.11);
    });

    test('update does not clobber a supplied org_amount', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFE');

        $this->organisation->update(['wac_calculations_start_date' => '2025-08-01']);
        $orgStock->refresh()->unsetRelation('organisation');

        $movement = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 100,
        ]);

        $movement = UpdateOrgStockMovement::make()->action($movement, ['quantity' => 100, 'org_amount' => 500], strict: false);
        expect((float) $movement->org_amount)->toBe(500.0);

        $orgStock->update(['value_in_locations' => 7905, 'current_supplier_sku_cost' => 7]);
        $movement = UpdateOrgStockMovement::make()->action($movement->fresh(), ['quantity' => 200], strict: false);
        expect((float) $movement->org_amount)->toBe(1000.0);
    });

    test('restore command reverts pre corruption-window movements to snapshot values', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFF');

        $movement = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 20,
        ]);
        $movement->update(['org_amount' => 269.76, 'cost_per_sku' => 13.488, 'date' => '2017-02-27 14:24:49']);

        DB::statement('create table if not exists org_stock_movements_pre_costfix (like org_stock_movements)');
        DB::statement('create table if not exists org_stock_histories_pre_costfix (like org_stock_histories)');
        DB::statement('insert into org_stock_movements_pre_costfix select * from org_stock_movements where id = '.$movement->id);

        $movement->update(['org_amount' => 0.60, 'cost_per_sku' => 0.03, 'cost_status' => 'delivery']);

        $this->artisan('org_stock_movement:restore_pre_corruption_movements', ['--dry-run' => true])->assertExitCode(0);
        expect((float) $movement->fresh()->org_amount)->toBe(0.6);

        $this->artisan('org_stock_movement:restore_pre_corruption_movements')->assertExitCode(0);

        $movement->refresh();
        expect((float) $movement->org_amount)->toBe(269.76)
            ->and((float) $movement->cost_per_sku)->toBe(13.488)
            ->and($movement->cost_status)->toBeNull();
    });

    test('aurora amount repair decision', function () {
        $shouldRepair = fn ($movement, $aurora) => \App\Actions\Maintenance\Inventory\OrgStockMovement\RepairOrgStockMovementAmountFromAurora::shouldRepair((object) $movement, $aurora ? (object) $aurora : null);

        expect($shouldRepair(['quantity' => 1600, 'org_amount' => 11200368], ['quantity' => 1600, 'amount' => 425.60]))->toBeTrue()
            ->and($shouldRepair(['quantity' => 100, 'org_amount' => 500], ['quantity' => 100, 'amount' => 500]))->toBeFalse()
            ->and($shouldRepair(['quantity' => 100, 'org_amount' => 790500], ['quantity' => 120, 'amount' => 500]))->toBeFalse()
            ->and($shouldRepair(['quantity' => 100, 'org_amount' => 790500], null))->toBeFalse();
    });

    test('sku_value follows fifo as the official valuation', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFG');

        $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
        $orgStock->refresh()->unsetRelation('organisation');

        $firstPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $firstPurchase->update(['cost_per_sku' => 2, 'date' => now()->subDays(4)]);

        $secondPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $secondPurchase->update(['cost_per_sku' => 4, 'date' => now()->subDays(3)]);

        $picked = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PICKED->value,
            'quantity' => -5,
        ]);
        $picked->update(['date' => now()->subDays(2)]);

        $calculator = \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::make();
        expect(round($calculator->getFifoPerSku($orgStock, now()), 4))->toBe(round(10 / 3, 4))
            ->and((float) $calculator->getWacPerSku($orgStock, now()))->toBe(3.0)
            ->and((float) $calculator->getLppPerSku($orgStock, now()))->toBe(4.0);

        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::run($orgStock->id);
        expect(round((float) $orgStock->refresh()->sku_value, 2))->toBe(3.33);

        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue::run($orgStock);
        expect(round((float) $orgStock->refresh()->sku_value, 2))->toBe(3.33);
    });

    test('recalculating a sko history keeps its organisation day totals until they are hydrated again', function () {
        [$orgStock] = costFixStockInLocation($this->group, $this->organisation, 'CFK');
        \Illuminate\Support\Facades\Queue::fake();

        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::run($orgStock->id);
        $organisationStockHistory = \App\Models\Inventory\OrganisationStockHistory::where('organisation_id', $this->organisation->id)->where('date', today()->toDateString())->firstOrFail();
        $organisationStockHistory->update(['number_org_stocks' => 50, 'number_out_of_stock_org_stocks' => 5, 'org_stock_lpp_value' => 100]);

        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::run($orgStock->id);

        $organisationStockHistory->refresh();
        expect($organisationStockHistory->number_org_stocks)->toBe(50)
            ->and($organisationStockHistory->number_out_of_stock_org_stocks)->toBe(5)
            ->and((float) $organisationStockHistory->org_stock_lpp_value)->toBe(100.0);
    });

    test('stock history exports carry the three valuations with legends', function () {
        [$orgStock] = costFixStockInLocation($this->group, $this->organisation, 'CFH');
        $headings   = (new \App\Exports\Inventory\OrgStockHistoryExport($orgStock))->headings();
        expect($headings)->toContain(__('Stock Value').' FIFO ('.__('official').')')
            ->toContain(__('Stock Value').' WAC')
            ->toContain(__('Stock Value').' LPP')
            ->toContain(__('Unit Value').' FIFO ('.__('official').')');
    });

    test('movements carry running stock values in all three methods', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFI');
        $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
        $orgStock->refresh()->unsetRelation('organisation');

        $first = StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::PURCHASE->value, 'quantity' => 10]);
        $first->update(['cost_per_sku' => 2, 'date' => now()->subDays(4)]);
        $second = StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::PURCHASE->value, 'quantity' => 10]);
        $second->update(['cost_per_sku' => 4, 'date' => now()->subDays(3)]);
        $picked = StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::PICKED->value, 'quantity' => -5]);
        $picked->update(['date' => now()->subDays(2)]);

        \App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues::run($orgStock->id);

        $picked->refresh();
        expect((float) $picked->running_quantity_org_stock)->toBe(15.0)
            ->and((float) $picked->running_lpp_value)->toBe(60.0)
            ->and((float) $picked->running_wac_value)->toBe(45.0)
            ->and((float) $picked->running_fifo_value)->toBe(50.0);
    });

    test('running values replay the quantity when it was never stored', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFJ');
        $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
        $orgStock->refresh()->unsetRelation('organisation');

        $purchase = StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::PURCHASE->value, 'quantity' => 100]);
        $purchase->update(['cost_per_sku' => 2, 'date' => now()->subDays(3)]);
        $picked = StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::PICKED->value, 'quantity' => -40]);
        $picked->update(['date' => now()->subDays(2)]);

        // Aurora fetched movements arrive without a running quantity
        DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->update(['running_quantity_org_stock' => null]);

        \App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues::run($orgStock->id);

        expect((float) $picked->fresh()->running_fifo_value)->toBe(120.0);
    });

    test('running values are written in batches with the same values and only where they changed', function () {
        $this->organisation->update(['wac_calculations_start_date' => now()->subDays(20)->toDateString()]);
        [$orgStock] = runningValuesStockWithMovements($this->group, $this->organisation, 'CFRV');
        $readValues = fn () => DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->orderBy('id')->get(['running_lpp_value', 'running_wac_value', 'running_fifo_value'])->map(fn ($row) => array_values((array) $row))->all();
        $readVersions = fn () => DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->orderBy('id')->pluck('xmin', 'id')->all();

        \App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues::run($orgStock->id);

        $expected = [
            ['6.22', '6.22', '6.22'], ['0.00', '0.00', '0.00'], ['6.85', null, null], ['23.29', null, null], ['17.81', null, null],
            ['30.14', '43.91', '43.91'], ['34.25', '49.90', '49.90'], ['-6.85', '-9.98', null], ['-4.11', '-5.99', null], ['-2.74', '-3.99', null],
            ['15.55', '15.55', '15.55'], ['34.21', '34.21', '34.21'], ['27.99', '27.99', '27.99'], ['40.43', '40.43', '40.43'], ['37.32', '37.32', '37.32'],
        ];
        expect($readValues())->toBe($expected);

        $staleId = DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->orderBy('id')->skip(5)->value('id');
        DB::table('org_stock_movements')->where('id', $staleId)->update(['running_lpp_value' => 1, 'running_wac_value' => null, 'running_fifo_value' => 2]);
        $versionsBefore = $readVersions();

        \App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues::run($orgStock->id);

        $versionsAfter = $readVersions();
        expect($readValues())->toBe($expected)
            ->and(array_keys(array_diff_assoc($versionsAfter, $versionsBefore)))->toBe([$staleId]);
    });

    test('running values take the same number of queries however many movements a stock has', function () {
        $this->organisation->update(['wac_calculations_start_date' => now()->subDays(20)->toDateString()]);
        [$fewMovements]  = runningValuesStockWithMovements($this->group, $this->organisation, 'CFRQ1');
        [$manyMovements] = runningValuesStockWithMovements($this->group, $this->organisation, 'CFRQ2', 6);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $countQueries = function (int $orgStockId) use (&$queries) {
            $queries = 0;
            \App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues::run($orgStockId);

            return $queries;
        };

        expect(DB::table('org_stock_movements')->where('org_stock_id', $manyMovements->id)->count())->toBe(80)
            ->and($countQueries($manyMovements->id))->toBe($countQueries($fewMovements->id));
    });

    test('the batched current stock history run writes the same rows as the one stock at a time calculation', function () {
        \Illuminate\Support\Facades\Queue::fake();
        $this->organisation->update(['wac_calculations_start_date' => now()->subDays(8)->toDateString()]);

        [$withPurchases, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFBA');
        $secondLocation             = \App\Actions\Inventory\Location\StoreLocation::make()->action($location->warehouse, array_merge(\App\Models\Inventory\Location::factory()->definition(), ['code' => 'CF-CFBA2']));
        \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($withPurchases, $secondLocation, ['type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::STORING]);
        [$withoutPurchases, $otherLocation] = costFixStockInLocation($this->group, $this->organisation, 'CFBB');
        $withoutPurchases->update(['current_supplier_sku_cost' => 7.25]);
        [$withoutLocations, $formerLocation] = costFixStockInLocation($this->group, $this->organisation, 'CFBC');

        $movements = [
            [$withPurchases, $location, OrgStockMovementTypeEnum::PURCHASE, 8, 1.5, 0, 400],
            [$withPurchases, $location, OrgStockMovementTypeEnum::PICKED, -3, null, 0, 200],
            [$withPurchases, $location, OrgStockMovementTypeEnum::PURCHASE, 10, 2, 0, 9],
            [$withPurchases, $location, OrgStockMovementTypeEnum::PICKED, -15, null, 0, 8],
            [$withPurchases, $location, OrgStockMovementTypeEnum::FOUND, 4, null, 0, 7],
            [$withPurchases, $secondLocation, OrgStockMovementTypeEnum::FOUND, 3, null, 0, 7],
            [$withPurchases, $location, OrgStockMovementTypeEnum::PURCHASE, 20, null, 90, 5],
            [$withPurchases, $location, OrgStockMovementTypeEnum::AUDIT, 7, null, 0, 4],
            [$withPurchases, $secondLocation, OrgStockMovementTypeEnum::PICKED, -6, null, 0, 3],
            [$withPurchases, $location, OrgStockMovementTypeEnum::PURCHASE, 5, 6, 0, 2],
            [$withPurchases, $location, OrgStockMovementTypeEnum::PURCHASE, 4, 0, 0, -3],
            [$withoutPurchases, $otherLocation, OrgStockMovementTypeEnum::FOUND, 12, null, 0, 6],
            [$withoutPurchases, $otherLocation, OrgStockMovementTypeEnum::PICKED, -2, null, 0, 1],
            [$withoutLocations, $formerLocation, OrgStockMovementTypeEnum::PURCHASE, 6, 3, 0, 30],
            [$withoutLocations, $formerLocation, OrgStockMovementTypeEnum::PICKED, -6, null, 0, 20],
        ];
        foreach ($movements as [$orgStock, $movementLocation, $type, $quantity, $costPerSku, $orgAmount, $daysAgo]) {
            $movement = StoreOrgStockMovement::make()->action($orgStock, $movementLocation, ['type' => $type->value, 'quantity' => $quantity]);
            $movement->update([
                'cost_per_sku' => $costPerSku,
                'org_amount'   => $orgAmount,
                'date'         => now()->subDays($daysAgo),
                'source_id'    => $type === OrgStockMovementTypeEnum::AUDIT ? $this->organisation->id.':990002' : null,
            ]);
        }
        DB::table('org_stock_movements')->where('org_stock_id', $withPurchases->id)->where('type', OrgStockMovementTypeEnum::ASSOCIATE->value)->update(['date' => now()->subDays(500)]);
        $formerLocationOrgStock = (array) DB::table('location_org_stocks')->where('org_stock_id', $withoutLocations->id)->first();
        DB::table('location_org_stocks')->where('org_stock_id', $withoutLocations->id)->delete();

        $orgStocks   = [$withPurchases, $withoutPurchases, $withoutLocations];
        $orgStockIds = array_map(fn ($orgStock) => $orgStock->id, $orgStocks);
        $readRows    = fn () => [
            DB::table('org_stock_histories')->whereIn('org_stock_id', $orgStockIds)->where('date', today()->toDateString())->orderBy('org_stock_id')
                ->get()->map(fn ($row) => collect((array) $row)->except(['id', 'created_at', 'updated_at'])->all())->all(),
            DB::table('location_org_stock_histories')->whereIn('org_stock_id', $orgStockIds)->where('date', today()->toDateString())->orderBy('org_stock_id')->orderBy('location_id')
                ->get()->map(fn ($row) => collect((array) $row)->except(['id', 'created_at', 'updated_at', 'org_stock_history_id'])->all())->all(),
            DB::table('org_stocks')->whereIn('id', $orgStockIds)->orderBy('id')->get(['id', 'sku_value', 'lpp_per_sku'])->map(fn ($row) => (array) $row)->all(),
        ];
        $resetRows = function () use ($orgStockIds, $withoutLocations, $formerLocationOrgStock) {
            DB::table('location_org_stock_histories')->whereIn('org_stock_id', $orgStockIds)->delete();
            DB::table('org_stock_histories')->whereIn('org_stock_id', $orgStockIds)->delete();
            DB::table('location_org_stocks')->insert($formerLocationOrgStock);
            \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::run($withoutLocations->id);
            DB::table('location_org_stocks')->where('org_stock_id', $withoutLocations->id)->delete();
            DB::table('org_stocks')->whereIn('id', $orgStockIds)->update(['sku_value' => null, 'lpp_per_sku' => null]);
        };

        $runPreviousCalculation = function (\App\Models\Inventory\OrgStock $orgStock) {
            $calculator   = \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::make();
            $date         = \Illuminate\Support\Carbon::now();
            $exchangeRate = \App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange::run($orgStock->organisation->currency, $orgStock->group->currency);
            $costPerSku   = $calculator->getLppPerSku($orgStock, $date);
            $valuation    = $calculator->getValuationPerSku($orgStock, $date);
            $locations    = [];
            foreach (\App\Models\Inventory\LocationOrgStock::where('org_stock_id', $orgStock->id)->get() as $locationOrgStock) {
                $quantity    = $locationOrgStock->quantity;
                $locations[] = [
                    'location_id'          => $locationOrgStock->location_id,
                    'quantity'             => $quantity,
                    'org_stock_lpp_value'  => $quantity * $costPerSku,
                    'grp_stock_lpp_value'  => $quantity * $costPerSku * $exchangeRate,
                    'org_stock_wac_value'  => $valuation['wac'] === null ? null : $quantity * $valuation['wac'],
                    'grp_stock_wac_value'  => $valuation['wac'] === null ? null : $quantity * $valuation['wac'] * $exchangeRate,
                    'org_stock_fifo_value' => $valuation['fifo'] === null ? null : $quantity * $valuation['fifo'],
                    'grp_stock_fifo_value' => $valuation['fifo'] === null ? null : $quantity * $valuation['fifo'] * $exchangeRate,
                ];
            }
            $lastSoldDate = $calculator->lastSoldDate($orgStock, $date);
            (fn () => $this->persistOrgStockHistories($orgStock, $date, $locations, $costPerSku, $lastSoldDate, 60, $valuation['wac'], $valuation['fifo']))->call($calculator);

            $officialPerSku = $valuation['fifo'] > 0 ? $valuation['fifo'] : $costPerSku;
            $orgStock->update(array_filter(['sku_value' => $officialPerSku > 0 ? $officialPerSku : null, 'lpp_per_sku' => $costPerSku > 0 ? $costPerSku : null]));
        };

        $resetRows();
        foreach ($orgStocks as $orgStock) {
            $runPreviousCalculation(\App\Models\Inventory\OrgStock::find($orgStock->id));
        }
        $previous = $readRows();

        $resetRows();
        \App\Actions\Inventory\OrgStock\Stock\RunCurrentStockHistory::run();
        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::run($withoutLocations->id);
        $batched = $readRows();

        $resetRows();
        foreach ($orgStockIds as $orgStockId) {
            \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::run($orgStockId);
        }
        $oneAtATime = $readRows();

        expect($batched)->toEqual($previous)
            ->and($oneAtATime)->toEqual($previous)
            ->and($previous[0])->toHaveCount(3)
            ->and($previous[1])->toHaveCount(3);

        $withPurchasesRow = collect($previous[0])->firstWhere('org_stock_id', $withPurchases->id);
        expect((float) $withPurchasesRow['lpp_per_sku'])->toBe(6.0)
            ->and($withPurchasesRow['last_sold_date'])->toBe(now()->subDays(3)->toDateString())
            ->and((float) collect($previous[0])->firstWhere('org_stock_id', $withoutPurchases->id)['lpp_per_sku'])->toBe(7.25)
            ->and(collect($previous[0])->firstWhere('org_stock_id', $withoutLocations->id)['number_locations'])->toBe(0);
    });
});

test('repair org stock movement cost prices per sko in organisation currency', function () {
    [$orgStock, $location]   = costFixStockInLocation($this->group, $this->organisation, 'CFCUR');
    [$skipStock, $skipLoc]   = costFixStockInLocation($this->group, $this->organisation, 'CFNOX');

    $orgStock->update(['packed_in' => 4]);

    $movement = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 60,
    ]);
    $movement->update([
        'org_amount'   => 3000,
        'cost_per_sku' => null,
        'note'         => 'received from <span onClick="change_view(\'delivery/15601\')">CF15601</span>',
        'date'         => now()->subDays(20),
    ]);
    costFixStoreDelivery($this->group, $this->organisation, 15601, [[
        'org_stock_id'   => $orgStock->id,
        'unit_quantity'  => 300,
        'net_amount'     => 6000,
        'org_net_amount' => 60,
    ]]);

    $noOrgAmountMovement = StoreOrgStockMovement::make()->action($skipStock, $skipLoc, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    $noOrgAmountMovement->update([
        'org_amount'   => 5000,
        'cost_per_sku' => null,
        'note'         => 'received from <span onClick="change_view(\'delivery/15602\')">CF15602</span>',
        'date'         => now()->subDays(20),
    ]);
    costFixStoreDelivery($this->group, $this->organisation, 15602, [[
        'org_stock_id'   => $skipStock->id,
        'unit_quantity'  => 10,
        'net_amount'     => 900,
        'org_net_amount' => 0,
    ]]);

    [$nearParityStock, $nearParityLoc] = costFixStockInLocation($this->group, $this->organisation, 'CFPAR');

    $nearParityMovement = StoreOrgStockMovement::make()->action($nearParityStock, $nearParityLoc, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 20,
    ]);
    $nearParityMovement->update([
        'org_amount'   => 25,
        'cost_per_sku' => 1.25,
        'note'         => 'received from <span onClick="change_view(\'delivery/15603\')">CF15603</span>',
        'date'         => now()->subDays(20),
    ]);
    costFixStoreDelivery($this->group, $this->organisation, 15603, [[
        'org_stock_id'   => $nearParityStock->id,
        'unit_quantity'  => 20,
        'net_amount'     => 25,
        'org_net_amount' => 20,
    ]]);

    [$partialStock, $partialLoc] = costFixStockInLocation($this->group, $this->organisation, 'CFPUT');

    $partialMovement = StoreOrgStockMovement::make()->action($partialStock, $partialLoc, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 40,
    ]);
    $partialMovement->update([
        'org_amount'   => 320,
        'cost_per_sku' => 8,
        'note'         => 'received from <span onClick="change_view(\'delivery/15604\')">CF15604</span>',
        'date'         => now()->subDays(20),
    ]);
    costFixStoreDelivery($this->group, $this->organisation, 15604, [[
        'org_stock_id'         => $partialStock->id,
        'unit_quantity'        => 100,
        'unit_quantity_placed' => 40,
        'net_amount'           => 800,
        'org_net_amount'       => 80,
    ]]);

    $this->artisan('org_stock_movement:repair_cost_from_stock_delivery_items', ['organisation' => $this->organisation->slug])
        ->assertExitCode(0);

    $movement->refresh();
    $noOrgAmountMovement->refresh();
    $nearParityMovement->refresh();
    $partialMovement->refresh();

    expect((float) $movement->cost_per_sku)->toBe(0.8)
        ->and((float) $movement->org_amount)->toBe(48.0)
        ->and((float) $movement->grp_amount)->toBe(48.0)
        ->and($movement->cost_status)->toBe(\App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum::DELIVERY)
        ->and($noOrgAmountMovement->cost_per_sku)->toBeNull()
        ->and((float) $noOrgAmountMovement->org_amount)->toBe(5000.0)
        ->and((float) $nearParityMovement->cost_per_sku)->toBe(1.0)
        ->and((float) $nearParityMovement->org_amount)->toBe(20.0)
        ->and((float) $partialMovement->cost_per_sku)->toBe(0.8)
        ->and((float) $partialMovement->org_amount)->toBe(32.0);
});

describe('picking cost basis', function () {
    test('a purchase priced only by org_amount still prices the picking', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFLPP');

        $purchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 100,
        ]);
        $purchase->update([
            'org_amount'   => 250,
            'cost_per_sku' => null,
            'date'         => now()->subDays(10),
        ]);

        $picked = StoreOrgStockMovement::make()->action($orgStock->refresh(), $location, [
            'type'     => OrgStockMovementTypeEnum::PICKED->value,
            'quantity' => -4,
        ]);

        expect((float) $picked->org_amount)->toBe(-10.0);
    });

    test('a stock that was never purchased has no cost basis and the picking stays at zero', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFNOCOST');

        StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::FOUND->value,
            'quantity' => 50,
        ]);

        $picked = StoreOrgStockMovement::make()->action($orgStock->refresh(), $location, [
            'type'     => OrgStockMovementTypeEnum::PICKED->value,
            'quantity' => -4,
        ]);

        expect((float) $picked->org_amount)->toBe(0.0);
    });
});

describe('seed purchase cost from sku value', function () {
    test('unpriced purchases are seeded from sku_value and the picking gets a cost', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFSEED');

        $purchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 100,
        ]);
        $purchase->update(['org_amount' => 0, 'cost_per_sku' => null, 'date' => now()->subDays(10)]);
        $orgStock->update(['sku_value' => 2.5]);

        $this->artisan('org_stock_movement:seed_purchase_cost_from_sku_value', ['--dry-run' => true])->assertExitCode(0);
        expect($purchase->refresh()->cost_per_sku)->toBeNull();

        $this->artisan('org_stock_movement:seed_purchase_cost_from_sku_value')->assertExitCode(0);

        $purchase->refresh();
        expect((float) $purchase->cost_per_sku)->toBe(2.5)
            ->and((float) $purchase->org_amount)->toBe(250.0)
            ->and($purchase->cost_status)->toBe(\App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum::PROVISIONAL);

        $picked = StoreOrgStockMovement::make()->action($orgStock->refresh(), $location, [
            'type'     => OrgStockMovementTypeEnum::PICKED->value,
            'quantity' => -4,
        ]);

        expect((float) $picked->org_amount)->toBe(-10.0);
    });

    test('a stock that already has one priced purchase is left alone', function () {
        [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFSEEDOK');

        $priced = StoreOrgStockMovement::make()->action($orgStock, $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $priced->update(['org_amount' => 30, 'cost_per_sku' => 3, 'date' => now()->subDays(20)]);

        $unpriced = StoreOrgStockMovement::make()->action($orgStock->refresh(), $location, [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]);
        $unpriced->update(['org_amount' => 0, 'cost_per_sku' => null, 'date' => now()->subDays(10)]);
        $orgStock->update(['sku_value' => 99]);

        $this->artisan('org_stock_movement:seed_purchase_cost_from_sku_value')->assertExitCode(0);

        expect((float) $priced->refresh()->cost_per_sku)->toBe(3.0)
            ->and($unpriced->refresh()->cost_per_sku)->toBeNull();
    });
});

test('the post costfix rollup window reaches back to the oldest provisional purchase', function () {
    [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'CFROLL');

    $provisional = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    $provisional->update([
        'cost_per_sku' => 3,
        'org_amount'   => 30,
        'cost_status'  => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum::PROVISIONAL,
        'date'         => '2024-01-10 10:00:00',
    ]);

    $delivery = StoreOrgStockMovement::make()->action($orgStock->refresh(), $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    $delivery->update([
        'cost_per_sku' => 4,
        'org_amount'   => 40,
        'cost_status'  => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum::DELIVERY,
        'date'         => '2026-01-10 10:00:00',
    ]);

    foreach (['2024-02-01', '2026-02-01'] as $date) {
        \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockHistoricStockHistories::run($orgStock, \Illuminate\Support\Carbon::parse($date));
    }

    $rolledUp = \App\Actions\Maintenance\Inventory\OrgStockMovement\RollUpOrgStockHistoriesPostCostFix::run($this->organisation);

    $earlyDay = DB::table('organisation_stock_histories')
        ->where('organisation_id', $this->organisation->id)
        ->where('date', '2024-02-01')
        ->exists();

    expect($earlyDay)->toBeTrue()
        ->and($rolledUp)->toBeGreaterThanOrEqual(2);
});
