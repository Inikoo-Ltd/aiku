<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit\Helpers;

use App\Actions\Accounting\Reports\Intrastat\ExportIntrastatAeat;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Helpers\IntrastatVatNumber;
use App\Models\Accounting\IntrastatExportTimeSeries;
use App\Models\Accounting\IntrastatExportTimeSeriesRecord;
use App\Models\Helpers\Country;
use Illuminate\Database\Eloquent\Collection;

test('aeat corrections from january validation are reproduced', function (string $input, string $destination, string $expected) {
    expect(IntrastatVatNumber::normalise($input, $destination))->toBe($expected);
})->with([
    ['GR041278099', 'GR', 'EL041278099'],
    ['GR801503021', 'GR', 'EL801503021'],
    ['GREL054116167', 'GR', 'EL054116167'],
    ['BE735221297', 'BE', 'BE0735221297'],
    ['MT2581-1514', 'MT', 'MT25811514'],
    ['MT01419631005', 'IT', 'IT01419631005'],
    ['SE5592052822', 'SE', 'SE559205282201'],
    ['pl 574-202-27-84', 'PL', 'PL5742022784'],
    ['5742022784', 'PL', 'PL5742022784'],
    ['041278099', 'GR', 'EL041278099'],
]);

test('aeat rejected values fall back to unknown', function (string $input, string $destination) {
    expect(IntrastatVatNumber::normalise($input, $destination))->toBeNull();
})->with([
    ['FR803152842', 'FR'],
    ['IT03751443570', 'IT'],
    ['IE3289758JH', 'IE'],
    ['MT1906142', 'MT'],
    ['IE07750219C', 'IE'],
    ['MT15254902MT', 'MT'],
    ['IER93N2N7', 'IE'],
    ['MS8676569000', 'MT'],
    ['LT9001584309', 'LT'],
    ['IE628497', 'IE'],
    ['IE0694350D', 'IE'],
    ['LT1075696', 'LT'],
    ['PN103752', 'PL'],
    ['DK280972', 'DK'],
    ['FRB16917007', 'FR'],
    ['DE13350352361', 'DE'],
    ['EE14592530', 'EE'],
    ['NOTAVAILABLE', 'DE'],
    ['IE8375105', 'IE'],
    ['CY818302', 'CY'],
    ['SE199604267741', 'SE'],
    ['NL863944760B01', 'IT'],
    ['SI55952348O', 'SI'],
    ['', 'DE'],
]);

function aeatRecord(array $seriesAttributes, array $recordAttributes): IntrastatExportTimeSeriesRecord
{
    $series = new IntrastatExportTimeSeries(array_merge(['tariff_code' => '69120081', 'partner_tax_number' => 'PL5742022784'], $seriesAttributes));
    $series->setRelation('country', (new Country())->forceFill(['code' => 'PL']));
    $series->setRelation('originCountry', (new Country())->forceFill(['code' => 'ES']));

    $record = new IntrastatExportTimeSeriesRecord(array_merge([
        'id'                 => 1,
        'from'               => '2026-01-05',
        'weight'             => 119762,
        'quantity'           => 449,
        'value_org_currency' => 1529.94,
    ], $recordAttributes));
    $record->setRelation('intrastatExportTimeSeries', $series);

    return $record;
}

test('aeat row matches the specification example', function () {
    $result = (new ExportIntrastatAeat())->build(new Collection([aeatRecord([], [])]));

    expect($result['errors'])->toBe([])
        ->and($result['lines'])->toBe(['PL;29;DAP;11;3;;69120081;ES;1;119,762;;1529,94;1529,94;PL5742022784'])
        ->and($result['log'])->toHaveCount(1);
});

test('aeat row without retained vat uses QV and replacement uses nature 21', function () {
    $record = aeatRecord(['partner_tax_number' => null], ['nature_of_transaction' => IntrastatNatureOfTransactionEnum::RETURN_REPLACEMENT]);
    $result = (new ExportIntrastatAeat())->build(new Collection([$record]));

    expect($result['lines'][0])->toBe('PL;29;DAP;21;3;;69120081;ES;1;119,762;;1529,94;1529,94;QV999999999999');
});

test('aeat supplementary units follow the combined nomenclature unit', function () {
    $result = (new ExportIntrastatAeat())->build(new Collection([
        aeatRecord(['tariff_code' => '7013919000'], []),
        aeatRecord(['tariff_code' => '7116201100'], []),
        aeatRecord(['tariff_code' => '5311009090'], []),
    ]));

    expect($result['errors'])->toBe([])
        ->and(explode(';', $result['lines'][0])[10])->toBe('449')
        ->and(explode(';', $result['lines'][1])[10])->toBe('119762')
        ->and(explode(';', $result['lines'][2])[10])->toBe('');
});

test('aeat export stops on seven digit code, code missing from nomenclature and zero mass', function () {
    $result = (new ExportIntrastatAeat())->build(new Collection([
        aeatRecord(['tariff_code' => '0902300'], []),
        aeatRecord(['tariff_code' => '2842908080'], []),
        aeatRecord([], ['weight' => 0]),
    ]));

    expect($result['lines'])->toBe([])
        ->and($result['errors'])->toHaveCount(3)
        ->and($result['errors'][0])->toContain("commodity code '0902300' is not eight digits")
        ->and($result['errors'][1])->toContain("commodity code '28429080' is not in the 2026 Combined Nomenclature")
        ->and($result['errors'][2])->toContain('net mass is zero');
});

test('aeat files split at the row limit', function () {
    $lines = array_fill(0, ExportIntrastatAeat::ROWS_PER_FILE + 1, 'x');
    $files = (new ExportIntrastatAeat())->splitFiles($lines);

    expect($files)->toHaveCount(2)
        ->and(substr_count($files[0], "\r\n"))->toBe(ExportIntrastatAeat::ROWS_PER_FILE)
        ->and($files[1])->toBe("x\r\n");
});
