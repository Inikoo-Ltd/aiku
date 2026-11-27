<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Dropshipping\Zentrada\ImportZentradaOrder;
use App\Actions\Dropshipping\Zentrada\ImportZentradaOrderFromEmail;
use App\Actions\Dropshipping\Zentrada\ParseZentradaTransactionCsv;
use App\Actions\Dropshipping\Zentrada\SaveZentradaNoticeFromEmail;
use App\Actions\Ordering\SalesChannel\StoreSalesChannel;
use App\Actions\Retina\Dropshipping\Portfolio\ZentradaWebApi;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use App\Models\Tasks\StaffTask;
use App\Services\Gmail\GmailClient;
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
        'web_images'                => ['all' => [['gallery' => ['original_2x' => 'https://media.test/'.$code.'.jpg']]]],
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

test('zentrada feed leaves out products without stock or images, exclusive to a customer or with an EXP code', function () {
    $inStock    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1]);
    $noStock    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'available_quantity' => 0]);
    $unknown    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'available_quantity' => null]);
    $exclusive  = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'exclusive_for_customer_id' => createCustomer($this->shop)->id]);
    $notForSale = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'is_for_sale' => false]);
    $noImages   = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'web_images' => []]);
    $expCode    = zentradaFeedProduct($this->product, ['price' => 10, 'units' => 1, 'code' => 'ZEN-'.Str::upper(Str::random(6)).'-01exp']);

    $rows = readZentradaFeed($this->shop)['rows'];

    expect($rows)->toHaveKey($inStock->code)
        ->and($rows)->not->toHaveKey($noStock->code)
        ->and($rows)->not->toHaveKey($unknown->code)
        ->and($rows)->not->toHaveKey($exclusive->code)
        ->and($rows)->not->toHaveKey($notForSale->code)
        ->and($rows)->not->toHaveKey($noImages->code)
        ->and($rows)->not->toHaveKey($expCode->code)
        ->and($rows[$inStock->code]['image'])->toBe('https://media.test/'.$inStock->code.'.jpg');
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

function zentradaOrderSetup(Shop $shop): Customer
{
    createWarehouse();

    SalesChannel::where('group_id', $shop->group_id)->where('code', 'zentrada')->first()
        ?? StoreSalesChannel::make()->action($shop->group, [
            'code' => 'zentrada',
            'name' => 'Zentrada',
            'type' => SalesChannelTypeEnum::MARKETPLACE,
        ]);

    $customer = createCustomer($shop);
    UpdateShop::make()->action($shop, [
        'zentrada_import_orders'      => true,
        'zentrada_customer_reference' => $customer->reference,
        'zentrada_promotion_discount' => null,
        'zentrada_tier_1_quantity'    => null,
        'zentrada_tier_2_quantity'    => null,
        'zentrada_tier_3_quantity'    => null,
    ]);

    return $customer;
}

/**
 * @param list<array{0: string, 1: int, 2: int, 3: string, 4: string}> $lines code, packs, pieces, piece price, line total
 */
function zentradaTransactionCsv(string $transaction, array $lines, string $postalCode = '2000'): string
{
    $rows = ['A_TRANSAKTIONID;B_ARTIKELBEZEICHNUNG;D_MENGE_VE;E_MENGE_STUECK;F2_PREIS_GESAMT;F3_WAEHRUNG;G_ARTIKEL_NR;I2_PREIS_STUECK;L_EAN;M01_FIRMA;M02_VORNAME;M03_NACHNAME;M04_STRASSE;M05_HAUSNR;M06_PLZ;M07_ORT;M08_LAND;M09_TELEFON;N_UID_ZE;O_FREIGHT'];
    foreach ($lines as [$code, $packs, $pieces, $piecePrice, $total]) {
        $rows[] = "$transaction;Aloë Vera Seife;$packs;$pieces;$total EUR;EUR;$code;$piecePrice EUR;;Demi Shop;Federica;Demi;Meir;12;$postalCode;Antwerpen;BE;+32 3 000 00 00;DE813805341;9,95";
    }

    return mb_convert_encoding(implode("\r\n", $rows), 'Windows-1252', 'UTF-8');
}

test('a TradeSafe transaction CSV is read despite its Windows encoding, decimal commas and EUR suffixes', function () {
    $parsed = ParseZentradaTransactionCsv::make()->handle(zentradaTransactionCsv('958559', [['ATumbleL-01', 2, 12, '0,90', '10,80']]));

    expect($parsed['transaction'])->toBe('958559')
        ->and($parsed['freight'])->toBe(9.95)
        ->and($parsed['vat_number'])->toBe('DE813805341')
        ->and($parsed['address'])->toMatchArray(['company' => 'Demi Shop', 'firstName' => 'Federica', 'name' => 'Demi', 'street' => 'Meir', 'number' => '12', 'postal' => '2000', 'city' => 'Antwerpen', 'country' => 'BE'])
        ->and($parsed['lines'][0])->toMatchArray(['code' => 'ATumbleL-01', 'packs' => 2.0, 'pieces' => 12.0, 'piece_price' => 0.9, 'amount' => 10.8])
        ->and(ImportZentradaOrderFromEmail::transactionFromSubject('Delivery order transaction EU-NL 958559 , Buyer Federica Demi (ZuL-Auto)'))->toBe('EU-NL 958559')
        ->and(ImportZentradaOrderFromEmail::deliveryDate("Order value: 388,50 EUR\nAnnounced delivery date: 30.11.2026"))->toBe('30.11.2026');
});

test('only TradeSafe mail that gmail authenticated is taken as a Zentrada order', function () {
    zentradaOrderSetup($this->shop);
    $shop    = $this->shop->fresh();
    $subject = 'Delivery order transaction EU-NL 958559 , Buyer Federica Demi (ZuL-Auto)';
    $mail    = fn (string $results) => ['payload' => ['headers' => [['name' => 'Authentication-Results', 'value' => $results]]]];

    $dkim    = $mail('mx.google.com; dkim=pass header.i=@tradesafe.eu header.s=s1; spf=softfail smtp.mailfrom=pavol@awgifts.eu');
    $spoofed = $mail('mx.google.com; dkim=none; spf=fail smtp.mailfrom=service-eu@tradesafe.eu');
    $other   = $mail('mx.google.com; dkim=pass header.i=@tradesafe.eu.attacker.com');
    $planted = ['payload' => ['headers' => [
        ['name' => 'Authentication-Results', 'value' => 'mx.google.com; dkim=none; spf=fail smtp.mailfrom=service-eu@tradesafe.eu'],
        ['name' => 'Authentication-Results', 'value' => 'mx.google.com; dkim=pass header.i=@tradesafe.eu'],
    ]]];

    expect(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $dkim, 'Service-EU@tradesafe.eu', $subject))->toBeTrue()
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $spoofed, 'Service-EU@tradesafe.eu', $subject))->toBeFalse()
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $other, 'Service-EU@tradesafe.eu', $subject))->toBeFalse()
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $planted, 'Service-EU@tradesafe.eu', $subject))->toBeFalse()
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $dkim, 'someone@gmail.com', $subject))->toBeFalse()
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $dkim, 'Service-EU@tradesafe.eu', 'Fwd: '.$subject))->toBeFalse()
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderMail($shop, $dkim, 'Service-EU@tradesafe.eu', 'Cancellation of transaction EU-NL 958559'))->toBeFalse();

    UpdateShop::make()->action($this->shop, ['zentrada_import_orders' => false]);

    expect(ImportZentradaOrderFromEmail::isDeliveryOrderMail($this->shop->fresh(), $dkim, 'Service-EU@tradesafe.eu', $subject))->toBeFalse();
});

test('a Zentrada transaction becomes an order at Zentrada prices with its freight, sent to the warehouse, and only once', function () {
    $customer = zentradaOrderSetup($this->shop);
    $code     = 'ZENORD-'.Str::upper(Str::random(5));
    $this->product->forceFill(['code' => $code, 'units' => 6, 'price' => 5.35, 'available_quantity' => 50, 'is_for_sale' => true])->saveQuietly();

    $transaction = 'EU-BE '.random_int(100000, 999999);
    $parsed      = array_merge(ParseZentradaTransactionCsv::make()->handle(zentradaTransactionCsv('1', [[$code, 2, 12, '0,90', '10,80']])), ['transaction' => $transaction]);

    $order = ImportZentradaOrder::make()->handle($this->shop->fresh(), $parsed, ['delivery_date' => '30.11.2026', 'buyer' => 'Federica Demi'], [['name' => 'transaction_1.csv', 'contents' => 'csv']]);
    $line  = $order->transactions()->where('model_type', 'Product')->sole();

    expect($order->customer_id)->toBe($customer->id)
        ->and($order->customer_reference)->toBe($transaction)
        ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
        ->and($order->contact_name)->toBe('Federica Demi')
        ->and($order->company_name)->toBe('Demi Shop')
        ->and($order->deliveryAddress->country_code)->toBe('BE')
        ->and($order->deliveryAddress->address_line_1)->toBe('Meir 12')
        ->and($order->shipping_notes)->toContain('30.11.2026')
        ->and((float) $line->quantity_ordered)->toBe(2.0)
        ->and((float) $line->net_amount)->toBe(10.8)
        ->and((float) $order->shipping_amount)->toBe(9.95)
        ->and($order->attachments()->count())->toBe(1)
        ->and(ImportZentradaOrder::make()->handle($this->shop->fresh(), $parsed)->id)->toBe($order->id)
        ->and(Order::where('shop_id', $this->shop->id)->where('customer_reference', $transaction)->count())->toBe(1);
});

test('a Zentrada transaction that does not match the feed is left unsubmitted with a task, and one keyed in by hand is not imported again', function () {
    zentradaOrderSetup($this->shop);
    $code = 'ZENORD-'.Str::upper(Str::random(5));
    $this->product->forceFill(['code' => $code, 'units' => 6, 'price' => 5.35, 'available_quantity' => 1, 'is_for_sale' => true])->saveQuietly();
    $tasksBefore = StaffTask::where('group_id', $this->shop->group_id)->count();

    $postalCode = (string) random_int(1000, 9999);
    $parsed     = ParseZentradaTransactionCsv::make()->handle(zentradaTransactionCsv('EU-BE '.random_int(100000, 999999), [[$code, 2, 12, '0,70', '8,40'], ['NOPE-01', 1, 1, '1,00', '1,00']], $postalCode));

    $order = ImportZentradaOrder::make()->handle($this->shop->fresh(), $parsed);

    expect($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->internal_notes)->toContain('NOPE-01 is not a product of the shop')
        ->and($order->internal_notes)->toContain('0.70 a piece, the feed sends 0.90')
        ->and($order->internal_notes)->toContain('2 packs ordered, 1 in stock')
        ->and(StaffTask::where('group_id', $this->shop->group_id)->count())->toBe($tasksBefore + 1);

    $order->update(['customer_reference' => null, 'external_id' => null]);
    $sameTransactionKeyedByHand = array_merge($parsed, ['transaction' => 'EU-BE '.random_int(100000, 999999), 'lines' => [$parsed['lines'][0]]]);

    expect(ImportZentradaOrder::make()->existingOrder($this->shop->fresh(), $sameTransactionKeyedByHand)?->id)->toBe($order->id);
});

test('other TradeSafe mail is kept on the order of its transaction, or on the Zentrada customer, and never makes an order', function () {
    $customer = zentradaOrderSetup($this->shop);
    $code     = 'ZENORD-'.Str::upper(Str::random(5));
    $this->product->forceFill(['code' => $code, 'units' => 6, 'price' => 5.35, 'available_quantity' => 50, 'is_for_sale' => true])->saveQuietly();

    $transaction = 'EU-BE '.random_int(100000, 999999);
    $parsed      = array_merge(ParseZentradaTransactionCsv::make()->handle(zentradaTransactionCsv('1', [[$code, 2, 12, '0,90', '10,80']], (string) random_int(1000, 9999))), ['transaction' => $transaction]);
    $order       = ImportZentradaOrder::make()->handle($this->shop->fresh(), $parsed);

    $mail = fn (string $subject, string $body) => ['payload' => [
        'headers'  => [['name' => 'Subject', 'value' => $subject]],
        'mimeType' => 'text/plain',
        'body'     => ['data' => rtrim(strtr(base64_encode($body), '+/', '-_'), '=')],
    ]];
    $client      = Mockery::mock(GmailClient::class);
    $ordersCount = Order::where('shop_id', $this->shop->id)->count();
    $tasksBefore = StaffTask::where('group_id', $this->shop->group_id)->count();

    $cancelled = SaveZentradaNoticeFromEmail::make()->handle($this->shop->fresh(), $client, 'gmail-1', $mail('Cancellation of transaction '.str_replace(' ', '', $transaction), 'The buyer cancelled.'));
    $payout    = SaveZentradaNoticeFromEmail::make()->handle($this->shop->fresh(), $client, 'gmail-2', $mail('Payout notice 11/2026', 'Payout of 1.234,00 EUR'));

    expect($cancelled?->is($order))->toBeTrue()
        ->and($order->refresh()->internal_notes)->toContain('Cancellation of transaction')
        ->and($order->attachments()->count())->toBe(1)
        ->and($payout?->is($customer))->toBeTrue()
        ->and(StaffTask::where('group_id', $this->shop->group_id)->count())->toBe($tasksBefore + 1)
        ->and(Order::where('shop_id', $this->shop->id)->count())->toBe($ordersCount)
        ->and(SaveZentradaNoticeFromEmail::transactionIn('Complaint EU-NL958559'))->toBe('EU-NL 958559')
        ->and(ImportZentradaOrderFromEmail::isDeliveryOrderSubject('Re: Delivery order transaction EU-NL 958559'))->toBeFalse();
});
