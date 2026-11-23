<?php

/*
 * Author Louis Perez
 * Created on 23-09-2026-11h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Barcode\StoreBarcode;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Actions\Goods\Barcode\Json\GetNextFreeBarcode;
use App\Actions\Inventory\OrgStock\UpdateOrgStockUnitBarcode;
use Illuminate\Support\Facades\DB;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelData;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockUnifiedLabelData;
use App\Actions\Inventory\OrgStock\Json\ScanSkoBarcode;
use App\Actions\Inventory\OrgStock\Json\FetchOrgStockLabelOptions;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Models\Helpers\Country;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
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

    storeGpsrResponsiblePersons($this->organisation);
});

function storeGpsrResponsiblePersons(\App\Models\SysAdmin\Organisation $organisation): void
{
    $organisation->update(['settings' => array_merge($organisation->settings ?? [], ['gpsr' => ['operator' => [
        'code'     => 'SK',
        'position' => 3,
        'role'     => 'Dovozca / EU Responsible Person:',
        'name'     => 'Ancient Wisdom s.r.o.',
        'short'    => 'SK & EU RP Ancient Wisdom s.r.o.',
        'address'  => 'Prílohy 583/57, 919 26 Zavar, Slovensko',
        'email'    => 'contact@awgifts.eu',
    ]]])]);

    $organisation->group->update(['settings' => array_merge($organisation->group->settings ?? [], ['gpsr' => [
        'eu_responsible_organisation_id' => $organisation->id,
        'uk_responsible_organisation_id' => $organisation->id,
    ]])]);
}

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

test('the carton label offers materials only once the trade unit has them, saying why not', function () {
    $carton = fn ($orgStock) => collect(GetOrgStockLabelOptions::run($orgStock)['fields']['carton'])->keyBy('key')['with_ingredients'];

    $withoutMaterials = $carton(($this->makeOrgStock)([['marketing_ingredients' => null]]));
    $withMaterials    = $carton(($this->makeOrgStock)([['marketing_ingredients' => 'Cotton, glass']]));

    expect($withoutMaterials['available'])->toBeFalse()
        ->and($withoutMaterials['reason'])->toBe('This item has no materials')
        ->and($withMaterials['available'])->toBeTrue()
        ->and($withMaterials['checked'])->toBeTrue();
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
        ->toBe(['with_image', 'with_made_in', 'with_manufactured_by', 'with_weight', 'with_custom_text', 'with_account_signature', 'with_eu_responsible_person', 'with_es_importer'])
        ->and(collect($options['sizes']['sko'])->pluck('key')->all())
        ->toBe(['63x29.6', '63.5x29.6', '70x29.7', '130x60'])
        ->and($options['sizes']['unit'])->toHaveCount(8);
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

test('a single trade unit SKO without a unit EAN takes the next free one from the pool', function () {
    $orgStock = ($this->makeOrgStock)([['barcode' => null, 'barcode_id' => null]], null);

    StoreBarcode::make()->action($this->group, [
        'number' => '50'.substr((string) hrtime(true), -11),
        'type'   => BarcodeTypeEnum::EAN->value,
        'status' => BarcodeStatusEnum::AVAILABLE->value,
    ]);
    $expected = GetNextFreeBarcode::make()->handle($this->group);

    UpdateOrgStockUnitBarcode::make()->handle($orgStock, ['from_pool' => true]);

    expect($orgStock->tradeUnits->first()->refresh()->barcode)->toBe($expected->number);
});

test('a unit EAN held by another trade unit is refused naming that item by code and name', function () {
    $number = '50'.substr((string) hrtime(true), -11);

    $holderOrgStock = ($this->makeOrgStock)([['name' => 'Loose Massage Soap']], null);
    UpdateOrgStockUnitBarcode::make()->handle($holderOrgStock, ['unit_barcode' => $number]);
    $holder = $holderOrgStock->tradeUnits->first()->refresh();

    $orgStock = ($this->makeOrgStock)([['barcode' => null, 'barcode_id' => null]], null);

    expect(fn () => UpdateOrgStockUnitBarcode::make()->handle($orgStock, ['unit_barcode' => $number]))
        ->toThrow(ValidationException::class, $holder->code.' (Loose Massage Soap)');

    expect($orgStock->tradeUnits->first()->refresh()->barcode)->toBeNull()
        ->and($holder->refresh()->barcode)->toBe($number);
});

test('HELP-4891 a unit EAN held by a trade unit nothing uses moves to the SKO being edited', function () {
    $number = '50'.substr((string) hrtime(true), -11);

    $zombieOrgStock = ($this->makeOrgStock)([[]], null);
    UpdateOrgStockUnitBarcode::make()->handle($zombieOrgStock, ['unit_barcode' => $number]);
    $zombie = $zombieOrgStock->tradeUnits->first()->refresh();
    DB::table('model_has_trade_units')->where('trade_unit_id', $zombie->id)->delete();

    $orgStock = ($this->makeOrgStock)([['barcode' => null, 'barcode_id' => null]], null);
    UpdateOrgStockUnitBarcode::make()->handle($orgStock, ['unit_barcode' => $number]);

    expect($orgStock->tradeUnits->first()->refresh()->barcode)->toBe($number)
        ->and($zombie->refresh()->barcode)->toBeNull();
});

test('a pack and a carton get a GTIN-14 of their own from the unit EAN, and a scan of it reads back the unit and the batch', function () {
    expect(GetOrgStockUnifiedLabelData::gtin14(1, '5055796570355'))->toBe('15055796570352')
        ->and(GetOrgStockUnifiedLabelData::gtin14(2, '5055796570553'))->toBe('25055796570557');

    foreach ([']C1011505579657035210PKS202610', '(01) 15055796570352 (10) PKS202610'] as $scan) {
        expect(GetOrgStockUnifiedLabelData::parseGs1($scan))->toBe([
            'gtin14'     => '15055796570352',
            'indicator'  => 1,
            'unit_ean13' => '5055796570355',
            'batch'      => 'PKS202610',
        ]);
    }

    expect(GetOrgStockUnifiedLabelData::parseGs1('011505579657035910PKS202610'))->toBeNull()
        ->and(GetOrgStockUnifiedLabelData::parseGs1('5055796570355'))->toBeNull();
});

test('the unified label refuses to print with a required value missing and prints once it is there', function () {
    $orgStock = ($this->makeOrgStock)([[
        'name'              => 'Bali Dreamcatcher',
        'name_i8n'          => ['en' => 'Bali Dreamcatcher', 'es' => 'Atrapasueños Bali'],
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
    ]]);
    $orgStock->update(['packed_in' => 6]);

    $blocked = GetOrgStockUnifiedLabelData::run($orgStock, 'unit', null, 'PKS202610');

    expect($blocked['blockers'])->toBe(['The name in SK is missing'])
        ->and(fn () => PdfOrgStockLabel::run($orgStock, 'unit', ['standard' => 'unified', 'batch' => 'PKS202610']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'The name in SK is missing');

    $orgStock->tradeUnits->first()->update(['name_i8n' => ['en' => 'Bali Dreamcatcher', 'es' => 'Atrapasueños Bali', 'sk' => 'Lapač Snov Bali']]);
    $orgStock = $orgStock->refresh()->load('tradeUnits');

    $sko = GetOrgStockUnifiedLabelData::run($orgStock, 'sko', null, 'pks202610');

    expect($sko['blockers'])->toBe([])
        ->and($sko['gtin14'])->toBe(GetOrgStockUnifiedLabelData::gtin14(1, $orgStock->unit_barcode))
        ->and($sko['hri'])->toBe('(01) '.$sko['gtin14'].' (10) PKS202610')
        ->and($sko['gs1_128_data'])->toBe(chr(241).'01'.$sko['gtin14'].'10PKS202610')
        ->and(array_keys($sko['names']))->toBe(['es', 'sk'])
        ->and(GetOrgStockUnifiedLabelData::run($orgStock, 'unit', null, 'TOO LONG A BATCH CODE!')['blockers'])->toHaveCount(1)
        ->and(GetOrgStockUnifiedLabelData::run($orgStock, 'carton', null, 'PKS202610')['blockers'])->toBe(['The supplier product has no units per carton']);

    foreach (['unit', 'sko'] as $level) {
        $response = PdfOrgStockLabel::run($orgStock, $level, ['standard' => 'unified', 'batch' => 'PKS202610']);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toStartWith('%PDF');
    }

    $unified = GetOrgStockLabelOptions::run($orgStock)['unified'];

    expect(array_keys($unified['levels']))->toBe(['unit', 'sko', 'carton'])
        ->and($unified['levels']['unit'])->toBe(['size' => '100 x 60', 'blockers' => []]);

    $orgStock->update(['packed_in' => 1]);

    expect(array_keys(GetOrgStockLabelOptions::run($orgStock->refresh())['unified']['levels']))->toBe(['unit', 'carton'])
        ->and(GetOrgStockUnifiedLabelData::run($orgStock, 'sko', null, 'PKS202610')['blockers'])->toContain('A pack of one has no SKO label: the unit label is its label');
});

test('scanning the GS1-128 of a unified SKO label finds the SKO of that unit', function () {
    $unitEan  = GetOrgStockUnifiedLabelData::parseGs1('01'.GetOrgStockUnifiedLabelData::gtin14(1, '50'.substr((string) hrtime(true), -10).'0'))['unit_ean13'];
    $orgStock = ($this->makeOrgStock)([[]], $unitEan);

    $gtin14        = GetOrgStockUnifiedLabelData::gtin14(1, $unitEan);
    $brokenGtin14  = substr($gtin14, 0, 13).(((int) $gtin14[13] + 1) % 10);

    expect(ScanSkoBarcode::make()->handle($this->organisation, '(01) '.$gtin14.' (10) PKS202610')?->id)->toBe($orgStock->id)
        ->and(ScanSkoBarcode::make()->handle($this->organisation, '(01) '.$brokenGtin14.' (10) PKS202610'))->toBeNull();
});

test('HELP-3997 a unified label missing the ES or SK name offers to translate it and stops asking once it is there', function () {
    \App\Actions\Helpers\Translations\Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (string $text) => $text);

    $orgStock  = ($this->makeOrgStock)([['name' => 'Small Stone Washed T-shirt', 'name_i8n' => []]]);
    $tradeUnit = $orgStock->tradeUnits->first();

    $translateNames = GetOrgStockLabelOptions::run($orgStock)['unified']['translate_names'];

    expect($translateNames['route']['parameters'])->toBe(['tradeUnit' => $tradeUnit->id])
        ->and(array_keys($translateNames['blockers']))->toBe(['es', 'sk']);

    $tradeUnit->update(['name_i8n' => ['es' => 'Camiseta', 'sk' => 'Tričko']]);

    expect(GetOrgStockLabelOptions::run($orgStock->fresh()->load('tradeUnits'))['unified']['translate_names'])->toBeNull();
});

test('the label route answers only inventory staff of the organisation, INI-076', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $orgStock  = ($this->makeOrgStock)([[]]);
    $url       = route('grp.org.warehouses.show.inventory.org_stocks.label', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug,
        'orgStock'     => $orgStock->slug,
        'level'        => 'sko',
    ]);

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["human-resources.{$this->organisation->id}.view"]);
    get($url)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["inventory.{$this->organisation->id}.view"]);
    get($url)->assertOk();
});

test('the EU responsible person is stored once on the organisation and a trade unit can override it', function () {
    $orgStock  = ($this->makeOrgStock)([['gpsr_eu_responsible' => null]]);
    $tradeUnit = $orgStock->tradeUnits->first();

    $stored = "Ancient Wisdom s.r.o.\nPrílohy 583/57, 919 26 Zavar, Slovensko\ncontact@awgifts.eu";

    expect($tradeUnit->getEffectiveGpsrEuResponsible())->toBe($stored)
        ->and(GetOrgStockLabelData::run($orgStock)['eu_responsible_person'])->toBe('RP in EU: '.$stored)
        ->and(\App\Actions\Goods\TradeUnit\UI\GetTradeUnitCompliance::run($tradeUnit)['gpsr'][1]['value'])->toBe($stored);

    $tradeUnit->update(['gpsr_eu_responsible' => 'Other RP s.r.o., Bratislava']);
    $orgStock = $orgStock->refresh()->load('tradeUnits');

    expect($orgStock->tradeUnits->first()->getEffectiveGpsrEuResponsible())->toBe('Other RP s.r.o., Bratislava')
        ->and(GetOrgStockLabelData::run($orgStock)['eu_responsible_person'])->toBe('RP in EU: Other RP s.r.o., Bratislava');
});

test('the unified label prints the importers stored on the organisations and refuses to print without them', function () {
    $orgStock = ($this->makeOrgStock)([[
        'name_i8n'          => ['en' => 'Bali Dreamcatcher', 'es' => 'Atrapasueños Bali', 'sk' => 'Lapač Snov Bali'],
        'country_of_origin' => $this->country->code,
        'origin_country_id' => $this->country->id,
    ]]);

    $label = GetOrgStockUnifiedLabelData::run($orgStock, 'unit', null, 'PKS202610');

    expect($label['operators'])->toContain([
        'code'    => 'SK',
        'role'    => 'Dovozca / EU Responsible Person:',
        'name'    => 'Ancient Wisdom s.r.o.',
        'short'   => 'SK & EU RP Ancient Wisdom s.r.o.',
        'address' => 'Prílohy 583/57, 919 26 Zavar, Slovensko · contact@awgifts.eu',
    ])
        ->and($label['blockers'])->not->toContain('No organisation has its importer details set');

    $withOperators = \App\Models\SysAdmin\Organisation::where('group_id', $this->group->id)->get()->filter(fn ($organisation) => $organisation->getGpsrOperator());
    $savedSettings = $withOperators->mapWithKeys(fn ($organisation) => [$organisation->id => $organisation->settings]);
    foreach ($withOperators as $organisation) {
        $organisation->update(['settings' => array_merge($organisation->settings, ['gpsr' => []])]);
    }

    $blockers = GetOrgStockUnifiedLabelData::run($orgStock, 'unit', null, 'PKS202610')['blockers'];

    foreach ($withOperators as $organisation) {
        $organisation->update(['settings' => $savedSettings[$organisation->id]]);
    }

    expect($blockers)->toContain('No organisation has its importer details set');
});

test('the GPSR section of the organisation settings saves the importer and the responsible organisation the labels print', function () {
    \App\Actions\SysAdmin\Organisation\UpdateOrganisation::make()->action($this->organisation, [
        'gpsr_operator_email'                 => 'eu-rp@example.com',
        'gpsr_eu_responsible_organisation_id' => $this->organisation->id,
    ]);
    $organisation = $this->organisation->refresh();

    expect($organisation->getGpsrOperator())->toMatchArray(['code' => 'SK', 'name' => 'Ancient Wisdom s.r.o.', 'email' => 'eu-rp@example.com'])
        ->and(data_get($organisation->settings, 'gpsr.eu_responsible_organisation_id'))->toBe($organisation->id)
        ->and(GetOrgStockLabelData::run(($this->makeOrgStock)([[]]))['eu_responsible_person'])->toEndWith('eu-rp@example.com');

    $this->withoutVite();
    get(route('grp.org.settings.edit', $organisation->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->contains(
            fn ($section) => data_get($section, 'fields.gpsr_operator_email.value') === 'eu-rp@example.com'
        )));

    storeGpsrResponsiblePersons($organisation);
    expect(fn () => \App\Actions\SysAdmin\Organisation\UpdateOrganisation::make()->action($organisation, ['gpsr_operator_name' => null]))->toThrow(ValidationException::class);
});

test('only a group admin sees and saves the GPSR settings of an organisation', function () {
    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $url  = route('grp.models.org.settings.update', $this->organisation->id);
    $this->withoutVite();

    actingAsUserWithOnlyPermissions($user, ["org-admin.{$this->organisation->id}"]);
    \Pest\Laravel\patch($url, ['gpsr_operator_email' => 'not-allowed@example.com'])->assertForbidden();
    \Pest\Laravel\patch($url, ['compliance_buffer_days' => 14])->assertRedirect();
    get(route('grp.org.settings.edit', $this->organisation->slug))
        ->assertInertia(fn ($page) => $page->where('formData.blueprint', fn ($blueprint) => !collect($blueprint)->contains(fn ($section) => data_get($section, 'fields.gpsr_operator_email'))));

    actingAsUserWithOnlyPermissions($user, ["org-admin.{$this->organisation->id}", 'sysadmin.edit']);
    \Pest\Laravel\patch($url, ['gpsr_operator_email' => 'contact@awgifts.eu'])->assertRedirect();

    expect($this->organisation->refresh()->getGpsrOperator()['email'])->toBe('contact@awgifts.eu');
});
