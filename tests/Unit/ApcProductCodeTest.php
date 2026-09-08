<?php

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiApcGbShipping;

function apcParcel(float $length, float $width, float $height, float $weight): array
{
    return ['dimensions' => [$length, $width, $height], 'weight' => $weight];
}

beforeEach(function () {
    $this->apc = new CallApiApcGbShipping();
});

it('orders dimensions longest first, however the packer entered them', function () {
    expect($this->apc->sortedDimensions(apcParcel(60, 60, 65, 6)))->toBe([65.0, 60.0, 60.0])
        ->and($this->apc->sortedDimensions(apcParcel(22, 33, 142, 6)))->toBe([142.0, 33.0, 22.0])
        ->and($this->apc->sortedDimensions(['dimensions' => [], 'weight' => 1]))->toBe([0, 0, 0]);
});

it('picks lightweight only for a single small parcel', function () {
    expect($this->apc->fitsLightweightParcel([apcParcel(45, 35, 20, 5)]))->toBeTrue()
        ->and($this->apc->fitsLightweightParcel([apcParcel(20, 35, 45, 5)]))->toBeTrue()
        ->and($this->apc->fitsLightweightParcel([apcParcel(46, 35, 20, 5)]))->toBeFalse()
        ->and($this->apc->fitsLightweightParcel([apcParcel(45, 35, 20, 5.1)]))->toBeFalse()
        ->and($this->apc->fitsLightweightParcel([apcParcel(45, 35, 20, 5), apcParcel(10, 10, 10, 1)]))->toBeFalse();
});

it('accepts standard next day within 120x55x50 or a 60cm cube', function () {
    expect($this->apc->fitsStandardNextDayParcel([apcParcel(120, 55, 50, 30)]))->toBeTrue()
        ->and($this->apc->fitsStandardNextDayParcel([apcParcel(60, 60, 60, 30)]))->toBeTrue()
        ->and($this->apc->fitsStandardNextDayParcel([apcParcel(120, 56, 50, 30)]))->toBeFalse()
        ->and($this->apc->fitsStandardNextDayParcel([apcParcel(120, 55, 50, 30.1)]))->toBeFalse()
        ->and($this->apc->fitsStandardNextDayParcel(array_fill(0, 21, apcParcel(10, 10, 10, 1))))->toBeFalse();
});

it('sends the reported 160x60x60 box as non conveyable, not next day', function () {
    $box = [apcParcel(160, 60, 60, 25)];

    expect($this->apc->fitsLightweightParcel($box))->toBeFalse()
        ->and($this->apc->fitsStandardNextDayParcel($box))->toBeFalse()
        ->and($this->apc->fitsNonConveyableParcel($box))->toBeTrue();
});

it('sends a long thin parcel as excess, which no other service takes', function () {
    $pole = [apcParcel(205, 30, 30, 25)];

    expect($this->apc->fitsStandardNextDayParcel($pole))->toBeFalse()
        ->and($this->apc->fitsNonConveyableParcel($pole))->toBeFalse()
        ->and($this->apc->fitsExcessParcel($pole))->toBeTrue();
});

it('prefers non conveyable over excess for a wide box', function () {
    $box = [apcParcel(160, 60, 60, 25)];

    expect($this->apc->fitsNonConveyableParcel($box))->toBeTrue()
        ->and($this->apc->fitsExcessParcel($box))->toBeFalse();
});

it('forces every Scottish Highlands and Offshore postcode to TDAY for every parcel service', function (string $area, int $start, int $end) {
    foreach (range($start, $end) as $district) {
        $postcode = $area.$district.' 1AA';

        expect($this->apc->isTwoToFiveDayPostcode($postcode))->toBeTrue($postcode);

        foreach (['LW16', 'ND16', 'NC16', 'XS16', 'LQ16'] as $productCode) {
            expect($this->apc->productCodeForPostcode($postcode, $productCode))->toBe('TDAY', $postcode.' '.$productCode);
        }
    }
})->with([
    'AB30-AB39' => ['AB', 30, 39],
    'AB41-AB45' => ['AB', 41, 45],
    'AB51-AB56' => ['AB', 51, 56],
    'IV1-IV28' => ['IV', 1, 28],
    'IV30-IV32' => ['IV', 30, 32],
    'IV36' => ['IV', 36, 36],
    'IV40-IV49' => ['IV', 40, 49],
    'IV51-IV56' => ['IV', 51, 56],
    'IV63' => ['IV', 63, 63],
    'PH3-PH13' => ['PH', 3, 13],
    'PH15-PH26' => ['PH', 15, 26],
    'PH30-PH44' => ['PH', 30, 44],
    'PH49-PH50' => ['PH', 49, 50],
    'PA20-PA38' => ['PA', 20, 38],
    'PA41-PA49' => ['PA', 41, 49],
    'PA60-PA78' => ['PA', 60, 78],
    'PA80' => ['PA', 80, 80],
    'DD8-DD11' => ['DD', 8, 11],
    'FK7-FK21' => ['FK', 7, 21],
    'KY66' => ['KY', 66, 66],
    'KA27-KA28' => ['KA', 27, 28],
    'KW1-KW17' => ['KW', 1, 17],
    'HS1-HS9' => ['HS', 1, 9],
    'ZE1-ZE3' => ['ZE', 1, 3],
]);

it('does not force TDAY for neighbouring postcode districts outside the table', function (string $postcode) {
    expect($this->apc->requiresTdayProductCode($postcode))->toBeFalse()
        ->and($this->apc->productCodeForPostcode($postcode, 'LW16'))->toBe('LW16');
})->with([
    'AB before ranges' => 'AB29 1AA',
    'AB range gap' => 'AB40 1AA',
    'AB after ranges' => 'AB57 1AA',
    'IV first gap' => 'IV29 1AA',
    'IV middle gap' => 'IV50 1AA',
    'IV after ranges' => 'IV64 1AA',
    'PH first gap' => 'PH14 1AA',
    'PH middle gap' => 'PH27 1AA',
    'PH after ranges' => 'PH51 1AA',
    'PA first gap' => 'PA39 1AA',
    'PA middle gap' => 'PA50 1AA',
    'PA last gap' => 'PA79 1AA',
    'DD before range' => 'DD7 1AA',
    'FK after range' => 'FK22 1AA',
    'KY before district' => 'KY65 1AA',
    'KA after range' => 'KA29 1AA',
    'KW after range' => 'KW18 1AA',
    'HS after range' => 'HS10 1AA',
    'ZE after range' => 'ZE4 1AA',
    'different area' => 'SW1A 1AA',
]);

it('rejects parcels beyond every apc service', function () {
    expect($this->apc->fitsNonConveyableParcel([apcParcel(161, 60, 60, 25)]))->toBeFalse()
        ->and($this->apc->fitsNonConveyableParcel([apcParcel(160, 61, 60, 25)]))->toBeFalse()
        ->and($this->apc->fitsNonConveyableParcel([apcParcel(160, 60, 60, 31)]))->toBeFalse()
        ->and($this->apc->fitsNonConveyableParcel(array_fill(0, 3, apcParcel(160, 60, 60, 25))))->toBeFalse()
        ->and($this->apc->fitsExcessParcel([apcParcel(206, 30, 30, 25)]))->toBeFalse()
        ->and($this->apc->fitsExcessParcel([apcParcel(205, 31, 30, 25)]))->toBeFalse()
        ->and($this->apc->fitsExcessParcel([apcParcel(205, 30, 30, 31)]))->toBeFalse()
        ->and($this->apc->fitsExcessParcel(array_fill(0, 3, apcParcel(205, 30, 30, 25))))->toBeFalse();
});
