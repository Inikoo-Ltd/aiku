<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Maintenance\Masters\RepairMasterProductSpoiledRRP;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;

function spoiledRRPCandidate(float $price, float $rrp): MasterAsset
{
    $masterAsset                = new MasterAsset();
    $masterAsset->type          = MasterAssetTypeEnum::PRODUCT;
    $masterAsset->code          = 'TEST-01';
    $masterAsset->master_prices = ['EUR' => ['value' => $price], 'GBP' => ['value' => $price]];
    $masterAsset->master_rrps   = ['EUR' => ['value' => $rrp]];

    return $masterAsset;
}

it('leaves a healthy rrp alone', function () {
    expect(RepairMasterProductSpoiledRRP::make()->handle(spoiledRRPCandidate(6, 15)))->toBeNull();
});

it('rebuilds an rrp that sits below the band', function () {
    expect(RepairMasterProductSpoiledRRP::make()->handle(spoiledRRPCandidate(6, 7)))
        ->toMatchArray(['price' => 6.0, 'was' => 7.0, 'now' => 14.4]);
});

it('rebuilds an rrp that sits above the band', function () {
    expect(RepairMasterProductSpoiledRRP::make()->handle(spoiledRRPCandidate(6, 90)))
        ->toMatchArray(['was' => 90.0, 'now' => 14.4]);
});

it('skips a master with no usable price', function () {
    expect(RepairMasterProductSpoiledRRP::make()->handle(spoiledRRPCandidate(0, 15)))->toBeNull();
});
