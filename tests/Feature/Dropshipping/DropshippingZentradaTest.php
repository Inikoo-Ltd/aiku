<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Retina\Dropshipping\Portfolio\ZentradaWebApi;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Str;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, , $this->shop] = createShop();
    $this->website = createWebsite($this->shop);
    [, $this->product] = createProduct($this->shop);
});

function zentradaFeedProduct(Product $template, array $attributes): Product
{
    $code    = 'ZEN-'.Str::upper(Str::random(8));
    $product = $template->replicate(['slug']);
    $product->forceFill(array_merge([
        'code'                      => $code,
        'slug'                      => Str::lower($code),
        'is_for_sale'               => true,
        'exclusive_for_customer_id' => null,
        'available_quantity'        => 10,
        'barcode'                   => null,
    ], $attributes))->saveQuietly();

    return $product;
}

/**
 * @return array<string, array<string, string>>
 */
function readZentradaFeed(Shop $shop): array
{
    ob_start();
    ZentradaWebApi::make()->handle($shop->fresh())->sendContent();
    $lines = array_values(array_filter(explode("\n", ob_get_clean())));

    $header = str_getcsv(array_shift($lines), ';', '"', '');
    $rows   = [];
    foreach ($lines as $line) {
        $row                     = array_combine($header, str_getcsv($line, ';', '"', ''));
        $rows[$row['item_number']] = $row;
    }

    return ['header' => $header, 'rows' => $rows];
}

test('zentrada feed sells packs by the piece at a price rounded up to the cent', function () {
    $pack = zentradaFeedProduct($this->product, ['price' => 5.35, 'units' => 6, 'rrp' => 11.94, 'available_quantity' => 18, 'marketing_weight' => 31, 'gross_weight' => 192]);

    $feed = readZentradaFeed($this->shop);
    $row  = $feed['rows'][$pack->code];

    expect($feed['header'])->toHaveCount(81)
        ->and($feed['header'][29])->toBe('volumebasedpricing_price3')
        ->and($row['quantity_of_units_per_package'])->toBe('6')
        ->and($row['minimum_order_quantity_in_packing_units'])->toBe('1')
        ->and($row['net_price_per_unit'])->toBe('0.90')
        ->and($row['recommended_retail_price'])->toBe('1.99')
        ->and($row['available_quantity_in_packing_units'])->toBe('18')
        ->and($row['weight'])->toBe('0.031')
        ->and($row['weightGrossPcP'])->toBe('0.032')
        ->and($row['promotion_discount'])->toBe('0')
        ->and($row['activ_until'])->toBe(now()->addDays(ZentradaWebApi::OFFER_VALID_DAYS)->format('d.m.Y'))
        ->and($row['short_product_description'])->toStartWith('<strong>1 packing unit = 6 x ');
});

test('zentrada feed leaves out products without stock and products exclusive to a customer', function () {
    $inStock    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1]);
    $noStock    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'available_quantity' => 0]);
    $unknown    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'available_quantity' => null]);
    $exclusive  = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'exclusive_for_customer_id' => createCustomer($this->shop)->id]);
    $notForSale = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'is_for_sale' => false]);

    $rows = readZentradaFeed($this->shop)['rows'];

    expect($rows)->toHaveKey($inStock->code)
        ->and($rows)->not->toHaveKey($noStock->code)
        ->and($rows)->not->toHaveKey($unknown->code)
        ->and($rows)->not->toHaveKey($exclusive->code)
        ->and($rows)->not->toHaveKey($notForSale->code);
});

test('zentrada feed takes the promotion and volume prices from the shop settings', function () {
    $pack = zentradaFeedProduct($this->product, ['price' => 5.35, 'units' => 6]);

    UpdateShop::make()->action($this->shop, [
        'zentrada_promotion_discount' => 5,
        'zentrada_tier_1_quantity'    => 10,
        'zentrada_tier_1_discount'    => 15,
        'zentrada_tier_2_quantity'    => 3,
        'zentrada_tier_2_discount'    => 10,
        'zentrada_tier_3_quantity'    => 20,
        'zentrada_tier_3_discount'    => null,
    ]);

    expect($this->shop->fresh()->settings['zentrada'])->toMatchArray([
        'promotion_discount' => 5.0,
        'tier_1_quantity'    => 10,
        'tier_2_discount'    => 10.0,
        'tier_3_discount'    => null,
    ]);

    $row = readZentradaFeed($this->shop)['rows'][$pack->code];

    expect($row['promotion_discount'])->toBe('5')
        ->and($row['volumedbasedpricing_quantity1'])->toBe('3')
        ->and($row['volumebasedpricing_price1'])->toBe('0.81')
        ->and($row['volumedbasedpricing_quantity2'])->toBe('10')
        ->and($row['volumebasedpricing_price2'])->toBe('0.76')
        ->and($row['volumedbasedpricing_quantity3'])->toBe('')
        ->and($row['volumebasedpricing_price3'])->toBe('');

    UpdateShop::make()->action($this->shop, ['zentrada_promotion_discount' => null]);

    expect(readZentradaFeed($this->shop)['rows'][$pack->code]['promotion_discount'])->toBe('0');
});

test('zentrada feed keeps names and descriptions within what zentrada accepts', function () {
    $product = zentradaFeedProduct($this->product, [
        'price'       => 5.96,
        'units'       => 1,
        'name'        => 'Display Set of 70 Incense Smudge Bricks and Burner - 7 Earthy Himalaya Incense',
        'description' => '<p class="x">Call +44 1234 567890 or mail sales@awgifts.eu, see https://www.awgifts.eu/x <b>Handmade</b> &amp; boxed</p><ul><li>70 bricks</li></ul>',
    ]);

    $row = readZentradaFeed($this->shop)['rows'][$product->code];

    expect($row['product_description'])->toBe('Display Set of 70 Incense Smudge Bricks and Burner')
        ->and(mb_strlen($row['short_product_description']))->toBeLessThanOrEqual(1000)
        ->and($row['short_product_description'])->toStartWith('<strong>1 unit = 1 x Display Set of 70')
        ->and($row['short_product_description'])->toContain('<strong>Handmade</strong> & boxed<br>70 bricks')
        ->and($row['short_product_description'])->not->toContain('567890')
        ->and($row['short_product_description'])->not->toContain('@')
        ->and($row['short_product_description'])->not->toContain('awgifts')
        ->and($row['short_product_description'])->not->toContain('<p')
        ->and(ZentradaWebApi::make()->restrictedHtml('<b>'.str_repeat('a', 1200).'</b>', 1000))->toHaveLength(1000)->toEndWith('</strong>');
});

test('zentrada feed links the public product documents', function () {
    $product = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1]);

    $declaration = createAttachedMedia('Product', $product->id, 'doc');
    $howToUse    = createAttachedMedia('Product', $product->id, 'how_to_use');
    createAttachedMedia('Product', $product->id, 'doc_private');

    $row = readZentradaFeed($this->shop)['rows'][$product->code];

    expect($row['certificate_CE'])->toBe('https://'.$this->website->domain.'/attachment/'.$declaration->ulid.'/download')
        ->and($row['instructionsDocument1'])->toBe('https://'.$this->website->domain.'/attachment/'.$howToUse->ulid.'/download')
        ->and($row['certificate_additional'])->toBe('');
});
