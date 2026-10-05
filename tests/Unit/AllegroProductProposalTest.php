<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 10:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Dropshipping\Allegro\Product\ProposeAllegroProduct;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Mockery;

test('a product proposal sends the shipping weight and real dimensions in the units of each Allegro parameter', function () {
    $product = new Product([
        'code'                 => 'jcg-05',
        'gross_weight'         => 1371,
        'marketing_weight'     => 430,
        'marketing_dimensions' => ['l' => 0.26, 'w' => 0.32, 'h' => 0.125, 'type' => 'rectangular', 'units' => 'cm'],
    ]);
    $product->setRelation('images', collect());
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
