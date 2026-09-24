<?php

/*
 * Author Louis Perez
 * Created on 23-09-2026-11h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelData;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\Json\FetchOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Models\Helpers\Country;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use ReflectionClass;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    setPermissionsTeamId($this->group->id);
    $this->guest = createAdminGuest($this->group);
    $this->user  = $this->guest->getUser();
    actingAs($this->user);

    $this->artisan('warehouse:seed-permissions')->assertExitCode(0);

    $this->country = Country::first();

    $this->makeOrgStock = function (array $tradeUnitAttributes = [], ?string $unitBarcode = '5050000000017') {
        $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), [
            'state' => StockStateEnum::ACTIVE,
        ]));

        $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

        $tradeUnits = [];

        foreach (Arr::wrap($tradeUnitAttributes) as $attributes) {
            $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
            $tradeUnit->update($attributes);
            $tradeUnits[$tradeUnit->id] = ['quantity' => 1];
        }

        $orgStock->tradeUnits()->sync($tradeUnits);
        $orgStock->update([
            'unit_barcode'         => $unitBarcode,
            'is_single_trade_unit' => count($tradeUnits) === 1,
        ]);

        return $orgStock->refresh()->load('tradeUnits');
    };
});

test('label wording puts the country of origin and the organisation into one sentence', function () {
    $orgStock = ($this->makeOrgStock)([[
        'name'              => 'Rainbow Moonstone',
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
    ]]);

    $label = GetOrgStockLabelData::run($orgStock, 'unit');

    expect($label['made_in'])->toBe('Imported from '.$this->country->name.' by '.$this->organisation->name)
        ->and($label['name'])->toBe('Rainbow Moonstone')
        ->and($label['code'])->toBe($orgStock->code);
});

test('manufactured by comes from the gpsr field and is left off when it is empty', function () {
    $withManufacturer = ($this->makeOrgStock)([[
        'gpsr_manufacturer' => "AW Aromatics Ltd\n\nParkwood Road, Sheffield",
    ]]);

    $withoutManufacturer = ($this->makeOrgStock)([[
        'gpsr_manufacturer' => null,
    ]]);

    expect(GetOrgStockLabelData::run($withManufacturer, 'unit')['manufactured_by'])
        ->toBe('Manufactured by AW Aromatics Ltd Parkwood Road, Sheffield')
        ->and(GetOrgStockLabelData::run($withoutManufacturer, 'unit')['manufactured_by'])->toBeNull();
});

test('a field several trade units disagree on is left off rather than guessed', function () {
    $otherCountry = Country::where('id', '!=', $this->country->id)->first();

    $agreeing = ($this->makeOrgStock)([
        ['country_of_origin' => $this->country->code, 'origin_country_id' => $this->country->id],
        ['country_of_origin' => $this->country->code, 'origin_country_id' => $this->country->id],
    ]);

    $disagreeing = ($this->makeOrgStock)([
        ['country_of_origin' => $this->country->code, 'origin_country_id' => $this->country->id],
        ['country_of_origin' => $otherCountry->code, 'origin_country_id' => $otherCountry->id],
    ]);

    expect(GetOrgStockLabelData::run($agreeing, 'unit')['made_in'])->toContain($this->country->name)
        ->and(GetOrgStockLabelData::run($disagreeing, 'unit')['made_in'])->toBeNull();
});

test('a barcode with a broken check digit is printed as CODE128 instead of EAN13', function () {
    $valid   = ($this->makeOrgStock)([[]], '5050000000017');
    $invalid = ($this->makeOrgStock)([[]], '5050000000011');

    expect(GetOrgStockLabelData::run($valid, 'unit')['barcode']['type'])->toBe('EAN13')
        ->and(GetOrgStockLabelData::run($invalid, 'unit')['barcode']['type'])->toBe('C128B');
});

test('weight is shown in the unit that keeps it short', function () {
    $orgStock = ($this->makeOrgStock)([['marketing_weight' => 250]]);
    expect(GetOrgStockLabelData::run($orgStock, 'unit')['weight'])->toBe('250 g');

    $heavy = ($this->makeOrgStock)([['marketing_weight' => 2500]]);
    expect(GetOrgStockLabelData::run($heavy, 'unit')['weight'])->toBe('2.5 kg');
});

test('a field the org stock has no value for is offered unavailable and unticked', function () {
    $orgStock = ($this->makeOrgStock)([[
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
        'gpsr_manufacturer' => null,
    ]]);

    $fields = collect(GetOrgStockLabelOptions::run($orgStock)['fields']['unit'])->keyBy('key');

    expect($fields['with_made_in']['available'])->toBeTrue()
        ->and($fields['with_made_in']['checked'])->toBeTrue()
        ->and($fields['with_manufactured_by']['available'])->toBeFalse()
        ->and($fields['with_manufactured_by']['checked'])->toBeFalse()
        ->and($fields['with_manufactured_by']['reason'])->not->toBeNull()
        ->and($fields['with_account_signature']['checked'])->toBeFalse();
});

test('every Aurora label size and both layouts render a pdf', function () {
    $orgStock = ($this->makeOrgStock)([[
        'name'              => 'Rainbow Moonstone',
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
        'marketing_weight'  => 250,
    ]]);

    foreach (array_keys(PdfOrgStockLabel::SIZES) as $size) {
        $response = PdfOrgStockLabel::run($orgStock, 'unit', [
            'size'                   => $size,
            'layout'                 => 'single',
            'with_account_signature' => true,
            'with_custom_text'       => true,
            'custom_text'            => 'test',
        ]);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toStartWith('%PDF');
    }

    $sheet = PdfOrgStockLabel::run($orgStock, 'unit', ['layout' => 'sheet']);

    expect($sheet->getStatusCode())->toBe(200)
        ->and($sheet->getContent())->toStartWith('%PDF');
});

test('an org stock with no barcode at that level cannot be printed', function () {
    $orgStock = ($this->makeOrgStock)([[]], null);

    PdfOrgStockLabel::run($orgStock, 'unit', []);
})->throws(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

test('the label route accepts the query the modal builds', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());

    $orgStock = ($this->makeOrgStock)([[
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
        'marketing_weight'  => 250,
    ]]);

    $this->withoutExceptionHandling();

    $response = get(route('grp.org.warehouses.show.inventory.org_stocks.label', [
        'organisation'           => $this->organisation->slug,
        'warehouse'              => $warehouse->slug,
        'orgStock'               => $orgStock->slug,
        'level'                  => 'unit',
        'layout'                 => 'single',
        'size'                   => '130x60',
        'with_image'             => '0',
        'with_made_in'           => '1',
        'with_manufactured_by'   => '0',
        'with_weight'            => '1',
        'with_custom_text'       => '1',
        'with_account_signature' => '1',
        'custom_text'            => 'test',
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('the label route rejects a size it does not print', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $orgStock  = ($this->makeOrgStock)([[]]);

    get(route('grp.org.warehouses.show.inventory.org_stocks.label', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug,
        'orgStock'     => $orgStock->slug,
        'size'         => '999x999',
    ]))->assertSessionHasErrors('size');
});

test('the unit label needs a barcode to be printable and the SKO label does not', function () {
    $unitOnly = ($this->makeOrgStock)([[]], '5050000000017');
    $levels   = collect(GetOrgStockLabelOptions::run($unitOnly)['levels'])->keyBy('key');

    expect($levels['unit']['printable'])->toBeTrue()
        ->and($levels['sko']['printable'])->toBeTrue();

    $noBarcode = collect(GetOrgStockLabelOptions::run(($this->makeOrgStock)([[]], null))['levels'])->keyBy('key');

    expect($noBarcode['unit']['printable'])->toBeFalse()
        ->and($noBarcode['sko']['printable'])->toBeTrue();
});

test('the SKO label offers only its own fields and its own four sizes', function () {
    $orgStock = ($this->makeOrgStock)([[
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
        'marketing_weight'  => 250,
    ]]);

    $options = GetOrgStockLabelOptions::run($orgStock);

    expect(collect($options['fields']['sko'])->pluck('key')->all())
        ->toBe(['with_image', 'with_custom_text'])
        ->and(collect($options['fields']['unit'])->pluck('key')->all())
        ->toBe(['with_image', 'with_made_in', 'with_manufactured_by', 'with_weight', 'with_custom_text', 'with_account_signature'])
        ->and(collect($options['sizes']['sko'])->pluck('key')->all())
        ->toBe(['63x29.6', '63.5x29.6', '70x29.7', '130x60'])
        ->and($options['sizes']['unit'])->toHaveCount(7);
});

test('an SKO label prints for a box that has no barcode at all', function () {
    $orgStock = ($this->makeOrgStock)([[]], null);
    $orgStock->update(['packed_in' => 6]);

    foreach (PdfOrgStockLabel::LEVEL_SIZES['sko'] as $size) {
        $response = PdfOrgStockLabel::run($orgStock->refresh(), 'sko', [
            'size'             => $size,
            'with_image'       => '1',
            'with_custom_text' => '1',
            'custom_text'      => 'test',
        ]);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toStartWith('%PDF');
    }
});

test('a field the SKO label does not carry is ignored even when it is asked for', function () {
    $orgStock = ($this->makeOrgStock)([[
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
        'marketing_weight'  => 250,
    ]], null);

    $show = (new ReflectionClass(PdfOrgStockLabel::class))->getMethod('getVisibleFields');
    $show->setAccessible(true);

    $visible = $show->invoke(
        new PdfOrgStockLabel(),
        ['with_made_in' => '1', 'with_weight' => '1', 'with_account_signature' => '1'],
        GetOrgStockLabelData::run($orgStock, 'sko'),
        'sko'
    );

    expect($visible['made_in'])->toBeFalse()
        ->and($visible['weight'])->toBeFalse()
        ->and($visible['signature'])->toBeFalse();
});

test('the json endpoint hands the modal its options and the pdf route', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $orgStock  = ($this->makeOrgStock)([[
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
    ]]);

    $this->withoutExceptionHandling();

    $payload = FetchOrgStockLabelOptions::run($warehouse, $orgStock);

    expect($payload['label_route']['name'])->toBe('grp.org.warehouses.show.inventory.org_stocks.label')
        ->and($payload['label_route']['parameters']['orgStock'])->toBe($orgStock->slug)
        ->and($payload['label_route']['parameters']['warehouse'])->toBe($warehouse->slug)
        ->and($payload['options']['sizes'])->toHaveKeys(['sko', 'unit'])
        ->and($payload['options']['fields'])->toHaveKeys(['sko', 'unit']);
});
