<?php

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiApcGbShipping;

function apcParcel(float $length, float $width, float $height, float $weight): array
{
    return ['dimensions' => [$length, $width, $height], 'weight' => $weight];
}

beforeEach(function () {
    $this->apc = new CallApiApcGbShipping();
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
