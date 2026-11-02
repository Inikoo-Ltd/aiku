<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 10:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Dropshipping\Allegro\Product\ProposeAllegroProduct;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
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

test('a dictionary parameter with no matching value picks the ambiguous value and sends no unrelated custom value', function () {
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

    expect($parameters)->toBe([['id' => '10', 'valuesIds' => ['10_99']]]);
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
