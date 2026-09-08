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

it('routes scottish 2-5 day postcodes off next day service', function () {
    foreach (['AB37 1AA', 'AB43 1AA', 'AB45 1AA', 'AB55 1AA', 'IV21 1AA', 'IV27 1AA', 'IV40 1AA', 'IV49 1AA', 'IV51 1AA', 'IV56 1AA', 'PA20 1AA', 'PA41 1AA', 'PA78 1AA', 'PH42 1AA', 'PH44 1AA', 'KA27 1AA', 'KA28 1AA', 'KW1 1AA', 'KW17 1AA', 'HS1 1AA', 'HS9 1AA', 'ZE1 1AA', 'ZE3 1AA', 'JE2 1AA', 'GG1 1AA', 'IM1 1AA'] as $postcode) {
        expect($this->apc->isTwoToFiveDayPostcode($postcode))->toBeTrue($postcode);
    }
});

it('keeps scottish next day postcodes on next day service', function () {
    foreach (['AB10 1AA', 'AB25 1AA', 'AB30 1AA', 'AB36 1AA', 'AB39 1AA', 'AB41 1AA', 'AB42 1AA', 'AB51 1AA', 'AB54 1AA', 'IV1 1AA', 'IV2 1AA', 'IV20 1AA', 'IV23 1AA', 'IV25 1AA', 'IV30 1AA', 'IV32 1AA', 'IV36 1AA', 'IV63 1AA', 'PH3 1AA', 'PH13 1AA', 'PH15 1AA', 'PH26 1AA', 'PH30 1AA', 'PH41 1AA', 'PH49 1AA', 'PH50 1AA', 'PA21 1AA', 'PA38 1AA', 'PA80 1AA', 'DD8 1AA', 'DD11 1AA', 'FK7 1AA', 'FK21 1AA', 'KY6 1AA', 'G1 1AA', 'EH1 1AA', 'SW1A 1AA'] as $postcode) {
        expect($this->apc->isTwoToFiveDayPostcode($postcode))->toBeFalse($postcode);
    }
});

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
