<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 11:20:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Dropshipping\Ebay\Product\CheckEbayPortfolio;
use App\Actions\Dropshipping\Ebay\Product\CheckIfProductExistInEbay;
use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;

test('sku search unwraps the offers envelope before checking published status', function () {
    $offer = ['offerId' => '255793398011', 'sku' => 'jcg-05', 'status' => 'PUBLISHED'];

    expect(CheckIfProductExistInEbay::publishedOffer(['offers' => [$offer], 'total' => 1]))->toBe($offer)
        ->and(CheckIfProductExistInEbay::publishedOffer(['offers' => [], 'total' => 0]))->toBe([])
        ->and(CheckIfProductExistInEbay::publishedOffer(['offers' => [['offerId' => '1', 'status' => 'UNPUBLISHED']]]))->toBe([])
        ->and(CheckIfProductExistInEbay::publishedOffer(['offers' => [['offerId' => '1', 'status' => 'UNPUBLISHED'], $offer]]))->toBe($offer)
        ->and(CheckIfProductExistInEbay::publishedOffer($offer))->toBe($offer)
        ->and(CheckIfProductExistInEbay::publishedOffer(['error' => 'boom']))->toBe([]);
});

test('bulk stock update errors are read from whichever response part carries them', function () {
    $endedItem = ['errorId' => 25002, 'message' => 'A user error has occurred. You are not allowed to revise an ended item "127582359707".'];
    $response = [
        'responses' => [
            ['statusCode' => 400, 'sku' => 'gemsp-01'],
            ['statusCode' => 400, 'sku' => 'gemsp-01', 'offerId' => '97875958011', 'errors' => [$endedItem]],
        ],
    ];

    expect(UpdateEbayPortfolio::bulkUpdateErrors($response))->toBe([$endedItem])
        ->and(UpdateEbayPortfolio::isListingEndedError(UpdateEbayPortfolio::bulkUpdateErrors($response)))->toBeTrue()
        ->and(UpdateEbayPortfolio::bulkUpdateErrors(['errors' => [$endedItem]]))->toBe([$endedItem])
        ->and(UpdateEbayPortfolio::isListingEndedError([['errorId' => 25001, 'message' => 'A system error has occurred.']]))->toBeFalse();
});

test('a matched offer is shaped as the single match the retina table and matcher expect', function () {
    $offer = ['offerId' => '255793398011', 'sku' => 'JCG-05', 'status' => 'PUBLISHED'];
    $inventoryItem = ['sku' => 'JCG-05', 'product' => ['title' => 'Jasmine Candle', 'imageUrls' => ['https://i.ebayimg.com/a.jpg']]];

    expect(CheckEbayPortfolio::matchFromOffer($offer, $inventoryItem))->toBe([
        'id'     => 'JCG-05',
        'name'   => 'Jasmine Candle',
        'images' => [['src' => 'https://i.ebayimg.com/a.jpg']],
    ])->and(CheckEbayPortfolio::matchFromOffer($offer, ['error' => 'not found']))->toBe([
        'id'     => 'JCG-05',
        'name'   => 'JCG-05',
        'images' => [],
    ]);
});
