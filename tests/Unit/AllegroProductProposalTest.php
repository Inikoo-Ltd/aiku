<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 10:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Dropshipping\Allegro\Product\ProposeAllegroProduct;
use App\Actions\Dropshipping\Allegro\Product\StoreProductToAllegro;
use App\Actions\Dropshipping\Allegro\User\ProcessShippingRates;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use App\Actions\Dropshipping\Allegro\Product\CheckAllegroPortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;
use App\Actions\Dropshipping\Allegro\Product\UpdateInventoryAllegro;
use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Models\Dropshipping\PlatformPortfolioLogs;
use Mockery;

function allegroTestProduct(array $attributes): Product
{
    $product = Mockery::mock(Product::class)->makePartial();
    $product->fill($attributes);
    $product->shouldReceive('orderedImages')->andReturn(new Collection());

    return $product;
}

test('a product proposal sends the shipping weight and real dimensions in the units of each Allegro parameter', function () {
    $product = allegroTestProduct([
        'code'                 => 'jcg-05',
        'gross_weight'         => 1371,
        'marketing_weight'     => 430,
        'marketing_dimensions' => ['l' => 0.26, 'w' => 0.32, 'h' => 0.125, 'type' => 'rectangular', 'units' => 'cm'],
    ]);
    $product->setRelation('family', null);

    $portfolio = new Portfolio(['customer_product_name' => 'Jasmine Candle', 'customer_description' => 'Candle']);
    $portfolio->setRelation('item', $product);

    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldReceive('proposeProduct')->once()->andReturnUsing(fn (array $productData) => $productData);

    $categoryParameters = ['parameters' => [
        ['id' => '1', 'name' => 'Waga produktu z opakowaniem jednostkowym', 'type' => 'float', 'required' => true, 'unit' => 'kg', 'restrictions' => ['precision' => 3]],
        ['id' => '2', 'name' => 'Szerokość produktu', 'type' => 'integer', 'required' => true, 'unit' => 'cm', 'restrictions' => []],
        ['id' => '3', 'name' => 'Wysokość produktu', 'type' => 'float', 'required' => true, 'unit' => 'mm', 'restrictions' => ['precision' => 1]],
        ['id' => '4', 'name' => 'Długość produktu', 'type' => 'float', 'required' => true, 'unit' => 'cm', 'restrictions' => ['precision' => 2]],
        ['id' => '5', 'name' => 'Waga netto', 'type' => 'float', 'required' => false, 'unit' => 'g', 'restrictions' => []],
    ]];

    $proposal = ProposeAllegroProduct::run($allegroUser, $portfolio, ['category_id' => '99', 'parameters' => $categoryParameters]);

    expect(Arr::pluck($proposal['parameters'], 'values', 'id'))->toBe([
        '1' => ['1.371'],
        '2' => ['32'],
        '3' => ['125'],
        '4' => ['26'],
    ]);
});

function proposeAllegroProductWithParameters(Product $product, array $parameters): array
{
    $portfolio = new Portfolio(['customer_product_name' => 'Wall Lamp', 'customer_description' => 'Lamp']);
    $portfolio->setRelation('item', $product);

    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldReceive('proposeProduct')->once()->andReturnUsing(fn (array $productData) => $productData);

    return ProposeAllegroProduct::run($allegroUser, $portfolio, ['category_id' => '99', 'parameters' => ['parameters' => $parameters]])['parameters'];
}

test('a dictionary parameter with no product value picks the ambiguous value with a "Not specified" custom value', function () {
    $product = allegroTestProduct(['code' => 'NWaveL-01']);
    $product->setRelation('family', new ProductCategory(['name' => 'Wave Lamps']));

    $parameters = proposeAllegroProductWithParameters($product, [
        [
            'id'         => '10',
            'name'       => 'Thread type',
            'type'       => 'dictionary',
            'required'   => true,
            'dictionary' => [['id' => '10_1', 'value' => 'E27'], ['id' => '10_2', 'value' => 'E14'], ['id' => '10_99', 'value' => 'other']],
            'options'    => ['ambiguousValueId' => '10_99', 'customValuesEnabled' => true],
        ],
    ]);

    expect($parameters)->toBe([['id' => '10', 'valuesIds' => ['10_99'], 'values' => ['Not specified']]]);
});

test('a dictionary parameter that takes no custom value never picks the ambiguous value', function () {
    $product = allegroTestProduct(['code' => 'NWaveL-01']);
    $product->setRelation('family', null);

    $parameters = proposeAllegroProductWithParameters($product, [
        [
            'id'         => '11',
            'name'       => 'Bulb threads',
            'type'       => 'dictionary',
            'required'   => true,
            'dictionary' => [['id' => '11_99', 'value' => 'other'], ['id' => '11_1', 'value' => 'E27']],
            'options'    => ['ambiguousValueId' => '11_99', 'customValuesEnabled' => false],
        ],
    ]);

    expect($parameters)->toBe([['id' => '11', 'valuesIds' => ['11_1']]]);
});

test('a product value that matches the ambiguous value is still sent as its custom value', function () {
    $product = allegroTestProduct(['code' => 'NWaveL-01']);
    $product->setRelation('family', new ProductCategory(['name' => 'Other']));

    $parameters = proposeAllegroProductWithParameters($product, [
        [
            'id'         => '12',
            'name'       => 'Typ',
            'type'       => 'dictionary',
            'required'   => true,
            'dictionary' => [['id' => '12_1', 'value' => 'Desk lamp'], ['id' => '12_99', 'value' => 'other']],
            'options'    => ['ambiguousValueId' => '12_99', 'customValuesEnabled' => true],
        ],
    ]);

    expect($parameters)->toBe([['id' => '12', 'valuesIds' => ['12_99'], 'values' => ['Other']]]);
});

test('a custom value on a dictionary parameter is sent only with the ambiguous value', function () {
    $product = allegroTestProduct(['code' => 'NWaveL-01']);
    $product->setRelation('family', new ProductCategory(['name' => 'Wave Lamps']));

    $parameters = proposeAllegroProductWithParameters($product, [
        [
            'id'         => '20',
            'name'       => 'Typ',
            'type'       => 'dictionary',
            'required'   => true,
            'dictionary' => [['id' => '20_1', 'value' => 'Desk lamp'], ['id' => '20_99', 'value' => 'inny']],
            'options'    => ['ambiguousValueId' => '20_99', 'customValuesEnabled' => true],
        ],
        [
            'id'         => '21',
            'name'       => 'Rodzaj',
            'type'       => 'dictionary',
            'required'   => true,
            'dictionary' => [['id' => '21_1', 'value' => 'Wave Lamps'], ['id' => '21_99', 'value' => 'inny']],
            'options'    => ['ambiguousValueId' => '21_99', 'customValuesEnabled' => true],
        ],
    ]);

    expect($parameters)->toBe([
        ['id' => '20', 'valuesIds' => ['20_99'], 'values' => ['Wave Lamps']],
        ['id' => '21', 'valuesIds' => ['21_1']],
    ]);
});

test('required parameters are matched to the most specific product attribute', function () {
    $product = allegroTestProduct(['code' => 'NWaveL-01', 'tariff_code' => '94052040']);
    $product->setRelation('family', null);

    $parameters = proposeAllegroProductWithParameters($product, [
        ['id' => '30', 'name' => 'Customs tariff code', 'type' => 'string', 'required' => true],
        ['id' => '31', 'name' => 'Manufacturer code', 'type' => 'string', 'required' => true],
    ]);

    expect(Arr::pluck($parameters, 'values', 'id'))->toBe([
        '30' => ['94052040'],
        '31' => ['NWaveL-01'],
    ]);
});

test('a portfolio already live on Allegro is not uploaded again, so no duplicate offer is created', function () {
    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldNotReceive('createOffer', 'proposeProduct', 'getCategoryParameters');

    $customerSalesChannel = new CustomerSalesChannel();
    $customerSalesChannel->setRelation('user', $allegroUser);

    $portfolio = new Portfolio(['platform_product_id' => '19077641116', 'platform_status' => true]);
    $portfolio->setRelation('customerSalesChannel', $customerSalesChannel);

    expect(StoreProductToAllegro::run($portfolio))->toBe($portfolio);
});

test('a private Allegro seller refused a company-only delivery method gets our price list on the next usable method', function () {
    $prepaidToPoland = fn (string $id, ?string $dispatchCountry) => [
        'id'                       => $id,
        'paymentPolicy'            => 'IN_ADVANCE',
        'destinationCountry'       => 'PL',
        'dispatchCountry'          => $dispatchCountry,
        'marketplaces'             => ['allegro-pl'],
        'shippingRatesConstraints' => ['allowed' => true, 'firstItemRate' => ['currency' => 'PLN']],
    ];

    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->setRelation('customerSalesChannel', (new CustomerSalesChannel())->forceFill(['slug' => 'cdeu-004423-allegro']));
    $allegroUser->shouldReceive('getDeliveryMethods')->andReturn(['deliveryMethods' => [
        ['paymentPolicy' => 'CASH_ON_DELIVERY'] + $prepaidToPoland('cash-on-delivery', 'SK'),
        $prepaidToPoland('inpost', null),
        $prepaidToPoland('dpd-company-only', 'SK'),
        $prepaidToPoland('poczta-polska', 'SK'),
    ]]);

    $triedMethods = [];
    $allegroUser->shouldReceive('createShippingRates')->andReturnUsing(function (array $data) use (&$triedMethods) {
        $methodId      = $data['rates'][0]['deliveryMethod']['id'];
        $triedMethods[] = $methodId;

        return $methodId === 'dpd-company-only'
            ? ['message' => 'Allegro DPD Courier Poland delivery option is available only for companies.']
            : ['id' => 'rates-1'];
    });

    $result = ProcessShippingRates::make()->handle($allegroUser, 'allegro-pl');

    expect($result)->toBe(['id' => 'rates-1'])
        ->and($triedMethods)->toBe(['dpd-company-only', 'poczta-polska']);
});

test('an upload links only to the Allegro catalogue product that carries our barcode, not the first search result', function () {
    $storeProductToAllegro = new StoreProductToAllegro();

    $otherProduct = ['id' => 'other', 'parameters' => [['id' => '225693', 'name' => 'EAN (GTIN)', 'values' => ['5901234123457']]]];
    $ourProduct   = ['id' => 'ours', 'parameters' => [['id' => '225693', 'name' => 'EAN (GTIN)', 'values' => ['05056368347337']]]];
    $flaggedGtin  = ['id' => 'flagged', 'parameters' => [['id' => '1', 'name' => 'Kód', 'values' => ['5056368347337'], 'options' => ['isGTIN' => true]]]];

    expect($storeProductToAllegro->catalogueProductWithBarcode([$otherProduct, $ourProduct], '5056368347337')['id'])->toBe('ours')
        ->and($storeProductToAllegro->catalogueProductWithBarcode([$otherProduct], '5056368347337'))->toBeNull()
        ->and($storeProductToAllegro->catalogueProductWithBarcode([['id' => 'no-parameters']], '5056368347337'))->toBeNull()
        ->and($storeProductToAllegro->catalogueProductHasBarcode($flaggedGtin, '5056368347337'))->toBeTrue()
        ->and($storeProductToAllegro->catalogueProductHasBarcode(['parameters' => [['name' => 'Model', 'values' => ['5056368347337']]]], '5056368347337'))->toBeFalse();
});

function allegroCheckedPortfolio(array $attributes, AllegroUser $allegroUser): array
{
    $portfolio = new Portfolio(array_merge(['platform_product_id' => '19118876760', 'platform_status' => true, 'exist_in_platform' => true], $attributes));
    $customerSalesChannel = new CustomerSalesChannel();
    $customerSalesChannel->setRelation('user', $allegroUser);
    $portfolio->setRelation('customerSalesChannel', $customerSalesChannel);

    $updates = new \ArrayObject();
    UpdatePortfolio::mock()
        ->shouldReceive('handle')
        ->andReturnUsing(function (Portfolio $portfolio, array $modelData) use ($updates) {
            $updates->append($modelData);

            return $portfolio;
        });

    return [$portfolio, $updates];
}

function allegroOfferResponse(int $status, array $body = []): Response
{
    return new Response(new Psr7Response($status, [], json_encode($body)));
}

test('an Allegro offer is live only when Allegro says its publication is ACTIVE, and otherwise the portfolio says why', function (array $offer, bool $live, ?string $reason) {
    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldReceive('getOfferResponse')->with('19118876760')->andReturn(allegroOfferResponse(200, $offer));

    [$portfolio, $updates] = allegroCheckedPortfolio([], $allegroUser);

    CheckAllegroPortfolio::run($portfolio);

    expect($updates)->toHaveCount(1)
        ->and($updates[0]['platform_status'])->toBe($live)
        ->and($updates[0]['exist_in_platform'])->toBeTrue()
        ->and($updates[0]['data']['allegro_publication']['status'])->toBe($offer['publication']['status'])
        ->and(Arr::get($updates[0], 'errors_response.message'))->toBe($reason);
})->with([
    'active'                => [['publication' => ['status' => 'ACTIVE']], true, null],
    'inactive with errors'  => [['publication' => ['status' => 'INACTIVE'], 'validation' => ['errors' => [['userMessage' => 'Missing return policy.']]]], false, 'Allegro did not activate this offer: Missing return policy.'],
    'inactive, no reason'   => [['publication' => ['status' => 'INACTIVE']], false, 'This offer is on Allegro but not active, and Allegro gave no reason.'],
    'ended for empty stock' => [['publication' => ['status' => 'ENDED', 'endedBy' => 'EMPTY_STOCK']], false, 'Allegro ended this offer because it ran out of stock. It is activated again when we have stock.'],
]);

test('an Allegro offer that is gone is marked missing, and an Allegro outage leaves the portfolio as it was', function () {
    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldReceive('getOfferResponse')->andReturn(allegroOfferResponse(404), allegroOfferResponse(503));

    [$portfolio, $updates] = allegroCheckedPortfolio([], $allegroUser);

    CheckAllegroPortfolio::run($portfolio);
    expect($updates)->toHaveCount(1)
        ->and($updates[0]['exist_in_platform'])->toBeFalse()
        ->and($updates[0]['platform_status'])->toBeFalse();

    CheckAllegroPortfolio::run($portfolio);
    expect($updates)->toHaveCount(1);
});

test('uploading a product whose Allegro offer still exists activates that offer instead of creating a second one', function () {
    Queue::fake();

    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldNotReceive('createOffer');
    $allegroUser->shouldReceive('publishOffers')->once()->withArgs(fn ($commandId, $offerIds) => $offerIds === ['19118876760'])->andReturn([]);

    [$portfolio, $updates] = allegroCheckedPortfolio(['platform_status' => false], $allegroUser);

    StoreProductToAllegro::run($portfolio);

    expect($updates)->toHaveCount(1)
        ->and($updates[0]['data']['allegro_publication']['status'])->toBe('ACTIVATING')
        ->and($updates[0]['errors_response'])->toBe(['message' => CheckAllegroPortfolio::ACTIVATING_MESSAGE]);
    Queue::assertPushed(JobDecorator::class);
});

function allegroStockPush(array $offerAfterPatch, AllegroUser $allegroUser): \ArrayObject
{
    Queue::fake();
    StorePlatformPortfolioLog::mock()->shouldReceive('handle')->andReturn(new PlatformPortfolioLogs());
    UpdatePlatformPortfolioLog::mock()->shouldReceive('handle');

    $product = allegroTestProduct(['available_quantity' => 12]);
    $product->shouldReceive('isSellableThroughSalesChannels')->andReturn(true);

    $allegroUser->shouldReceive('updateOffer')->once()->andReturn($offerAfterPatch);

    $customerSalesChannel = Mockery::mock(CustomerSalesChannel::class)->makePartial();
    $customerSalesChannel->shouldReceive('update')->andReturnTrue();
    $customerSalesChannel->setRelation('user', $allegroUser);

    $portfolio = Mockery::mock(Portfolio::class)->makePartial();
    $portfolio->shouldReceive('update')->andReturnTrue();
    $portfolio->platform_product_id = '19118876760';
    $portfolio->setRelation('item', $product);
    $portfolio->setRelation('customerSalesChannel', $customerSalesChannel);

    $updates = new \ArrayObject();
    UpdatePortfolio::mock()->shouldReceive('handle')->andReturnUsing(function (Portfolio $portfolio, array $modelData) use ($updates) {
        $updates->append($modelData);

        return $portfolio;
    });

    UpdateInventoryAllegro::run($portfolio);

    return $updates;
}

test('a stock push with stock reactivates an offer Allegro ended for empty stock', function () {
    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldReceive('publishOffers')->once()->andReturn([]);

    $updates = allegroStockPush(['publication' => ['status' => 'ENDED', 'endedBy' => 'EMPTY_STOCK']], $allegroUser);

    expect($updates[0]['platform_status'])->toBeFalse()
        ->and($updates[1]['data']['allegro_publication']['status'])->toBe('ACTIVATING');
});

test('a stock push never reactivates an offer the seller ended', function () {
    $allegroUser = Mockery::mock(AllegroUser::class)->makePartial();
    $allegroUser->shouldNotReceive('publishOffers');

    $updates = allegroStockPush(['publication' => ['status' => 'ENDED', 'endedBy' => 'USER']], $allegroUser);

    expect($updates)->toHaveCount(1)
        ->and($updates[0]['errors_response']['message'])->toBe('This offer was ended on Allegro.');
});
