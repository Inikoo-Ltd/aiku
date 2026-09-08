<?php

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiGlsSkShipping;

function prepareGlsSkParcelParams(array $parentResource): object
{
    $reflection = new ReflectionMethod(CallApiGlsSkShipping::class, 'prepareParcelParams');
    $reflection->setAccessible(true);

    return $reflection->invoke(new CallApiGlsSkShipping(), array_merge([
        'reference'        => 'DN-1234',
        'shipping_notes'   => 'leave at <b>door</b>',
        'to_contact_name'  => 'Jan Novak',
        'to_email'         => 'jan@example.sk',
        'to_phone'         => '+421900000000',
        'to_address'       => ['address_line_1' => 'Hlavna 1', 'locality' => 'Bratislava', 'postal_code' => '81101', 'country_code' => 'SK'],
        'from_address'     => ['address_line_1' => 'Depot 2', 'locality' => 'Nitra', 'postal_code' => '94901', 'country_code' => 'SK'],
    ], $parentResource), 1234, null);
}

function prepareGlsSkParcelList(array $parentResource, int $numberParcels): array
{
    $reflection = new ReflectionMethod(CallApiGlsSkShipping::class, 'prepareParcelList');
    $reflection->setAccessible(true);

    return $reflection->invoke(new CallApiGlsSkShipping(), array_merge([
        'reference'   => 'DN-1234',
        'to_address'  => ['country_code' => 'SK'],
    ], $parentResource), 1234, array_fill(0, $numberParcels, ['weight' => 1]));
}

it('never points a non production environment at the live GLS endpoint', function () {
    expect((new CallApiGlsSkShipping())->getBaseUrl())->toBe('https://api.test.mygls.sk');
});

it('adds the COD service with amount, currency and reference', function () {
    $params = prepareGlsSkParcelParams([
        'cash_on_delivery' => ['amount' => 149.99, 'currency' => 'EUR'],
    ]);

    expect($params)->not->toHaveProperty('ServiceList')
        ->and($params->CODAmount)->toBe(149.99)
        ->and($params->CODCurrency)->toBe('EUR')
        ->and($params->CODReference)->toBe('DN-1234');
});

it('omits COD when there is no amount to collect', function () {
    expect(prepareGlsSkParcelParams(['cash_on_delivery' => null]))->not->toHaveProperty('CODAmount')
        ->and(prepareGlsSkParcelParams(['cash_on_delivery' => ['amount' => 0, 'currency' => 'EUR']]))->not->toHaveProperty('CODReference');
});

it('puts the whole COD amount on the first parcel and nothing on the rest', function () {
    $parcelList = prepareGlsSkParcelList(['cash_on_delivery' => ['amount' => 149.99, 'currency' => 'EUR']], 3);

    expect($parcelList)->toHaveCount(3);

    expect($parcelList[0]->CODAmount)->toBe(149.99)
        ->and($parcelList[0]->Count)->toBe(1)
        ->and($parcelList[0]->ClientReference)->toBe('DN-1234');

    foreach ([1, 2] as $index) {
        expect($parcelList[$index])->not->toHaveProperty('CODAmount')
            ->and($parcelList[$index]->Count)->toBe(1)
            ->and($parcelList[$index]->ClientReference)->not->toBe('DN-1234');
    }

    $collected = array_sum(array_map(fn ($parcel) => $parcel->CODAmount ?? 0, $parcelList));
    expect($collected)->toBe(149.99);
});

it('keeps sending one Count entry when there is no COD to split', function () {
    $parcelList = prepareGlsSkParcelList(['cash_on_delivery' => null], 3);

    expect($parcelList)->toHaveCount(1)
        ->and($parcelList[0]->Count)->toBe(3);
});

it('keeps sending one entry for a single parcel COD shipment', function () {
    $parcelList = prepareGlsSkParcelList(['cash_on_delivery' => ['amount' => 20.0, 'currency' => 'EUR']], 1);

    expect($parcelList)->toHaveCount(1)
        ->and($parcelList[0]->Count)->toBe(1)
        ->and($parcelList[0]->CODAmount)->toBe(20.0);
});

it('strips tags and non alphanumerics from the label content', function () {
    $params = prepareGlsSkParcelParams(['shipping_notes' => 'ring! bell #2 <i>please</i>']);

    expect($params->Content)->toContain('ring bell 2 please')
        ->and($params->Content)->not->toContain('#');
});
