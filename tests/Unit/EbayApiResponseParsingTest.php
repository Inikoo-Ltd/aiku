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
use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Models\Catalogue\Product;

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

test('an item specific eBay only takes its own values for is refilled from its list on the publish retry', function () {
    $ebayUser = new class () {
        use WithEbayApiRequest;
    };

    $refused = ['errors' => [['errorId' => 25018, 'message' => 'The product aspects for this category no longer support custom values for Size. Your listing was not published. Update your request to use our standard values for Size.']]];
    $missing = ['errors' => [['errorId' => 25002, 'message' => "The item specific\u{a0}Colour is missing. Add Colour to this listing."]]];

    $categoryAspects = ['aspects' => [
        ['localizedAspectName' => 'Size', 'aspectConstraint' => ['aspectMode' => 'FREE_TEXT', 'aspectRequired' => true], 'aspectValues' => [['localizedValue' => 'XS'], ['localizedValue' => 'S'], ['localizedValue' => 'M']]],
        ['localizedAspectName' => 'Colour', 'aspectConstraint' => ['aspectMode' => 'FREE_TEXT', 'aspectRequired' => true], 'aspectValues' => [['localizedValue' => 'Beige'], ['localizedValue' => 'Charcoal']]],
    ]];

    $product = new Product();
    $product->name = 'Nomad Sari Stonewashed Cotton T-Shirt - Rebel - Charcoal - Small';
    $product->code = 'SWTS-69';

    $aspects = ['Size' => ['Nomad Sari Stonewashed Cotton T-Shirts']];

    expect($ebayUser->parseStandardValueAspects($refused))->toBe(['Size'])
        ->and($ebayUser->parseMissingAspects($refused))->toBe(['Size'])
        ->and($ebayUser->parseStandardValueAspects($missing))->toBe([])
        ->and($ebayUser->fillMissingAspects($product, $categoryAspects, ['Size'], $aspects))->toBe(['Size' => [$product->name]])
        ->and($ebayUser->fillMissingAspects($product, $categoryAspects, ['Size'], $aspects, ['Size']))->toBe(['Size' => ['XS']])
        ->and($ebayUser->fillMissingAspects($product, $categoryAspects, ['Colour'], $aspects, ['Colour']))->toBe(['Size' => ['Nomad Sari Stonewashed Cotton T-Shirts'], 'Colour' => ['Charcoal']]);
});
