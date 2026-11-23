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

it('prints the delivery note reference on the label even without a shipping note', function () {
    $params = prepareGlsSkParcelParams(['shipping_notes' => '']);

    expect($params->Content)->toEndWith('DN-1234');
});

it('prints the reference before the shipping note on the label', function () {
    $params = prepareGlsSkParcelParams(['shipping_notes' => 'ring bell']);

    expect($params->Content)->toEndWith('DN-1234 ring bell');
});

it('addresses the parcel to the company and keeps the person as contact', function (array $parentResource, string $name, string $contact) {
    $params = prepareGlsSkParcelParams($parentResource);

    expect($params->DeliveryAddress->Name)->toBe($name)
        ->and($params->DeliveryAddress->ContactName)->toBe($contact);
})->with([
    'company'       => [['to_company_name' => 'Kvety s.r.o.'], 'Kvety s.r.o.', 'Jan Novak'],
    'blank company' => [['to_company_name' => ' '], 'Jan Novak', 'Jan Novak'],
    'no company'    => [[], 'Jan Novak', 'Jan Novak'],
    'long company'  => [['to_company_name' => str_repeat('K', 70)], str_repeat('K', 60), 'Jan Novak'],
]);

it('orders FlexDelivery to the recipient email on every parcel only when asked and there is an email', function () {
    $reflection = new ReflectionMethod(CallApiGlsSkShipping::class, 'prepareParcelList');
    $parentResource = [
        'reference'        => 'DN-1234',
        'to_email'         => 'maria@example.de',
        'to_address'       => ['country_code' => 'DE'],
        'cash_on_delivery' => ['amount' => 20.0, 'currency' => 'EUR'],
    ];
    $parcels = array_fill(0, 2, ['weight' => 1]);

    foreach ($reflection->invoke(new CallApiGlsSkShipping(), $parentResource, 1234, $parcels, true) as $parcel) {
        expect($parcel->ServiceList)->toEqual([(object)['Code' => 'FDS', 'FDSParameter' => (object)['Value' => 'maria@example.de']]]);
    }

    expect($reflection->invoke(new CallApiGlsSkShipping(), $parentResource, 1234, null, false)[0])->not->toHaveProperty('ServiceList')
        ->and($reflection->invoke(new CallApiGlsSkShipping(), array_merge($parentResource, ['to_email' => '']), 1234, null, true)[0])->not->toHaveProperty('ServiceList');
});

it('prints the label again without FlexDelivery when GLS refuses it', function (?string $email, array $firstResponse, array $expectedCalls, bool $expectedFlexDelivery) {
    $calls  = [];
    $action = new class ($calls, $firstResponse) extends CallApiGlsSkShipping {
        public function __construct(public array &$calls, public array $firstResponse)
        {
        }

        protected function printLabels(SoapClient $client, ?string $username, ?string $password, array $parentResource, mixed $clientNumber, ?array $parcels, bool $withFlexDelivery): object
        {
            $this->calls[] = $withFlexDelivery;

            return count($this->calls) === 1 ? (object)$this->firstResponse : (object)['Labels' => 'pdf'];
        }
    };

    $reflection = new ReflectionMethod($action, 'printLabelsWithFlexDelivery');
    [$response, $withFlexDelivery] = $reflection->invoke($action, new SoapClient(null, ['location' => 'http://localhost', 'uri' => 'urn:test']), 'u', 'p', ['to_email' => $email], 1234, null);

    expect($calls)->toBe($expectedCalls)
        ->and($withFlexDelivery)->toBe($expectedFlexDelivery)
        ->and($response->Labels)->toBe('pdf');
})->with([
    'accepted'      => ['maria@example.de', ['Labels' => 'pdf'], [true], true],
    'refused'       => ['maria@example.de', ['Labels' => null], [true, false], false],
    'without email' => [null, ['Labels' => 'pdf'], [false], false],
]);
