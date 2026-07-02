<?php

use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Address;

function makeForbiddenCountryCheckAction()
{
    return new class {
        use WithOrderForbiddenCountryCheck;
    };
}

it('flags a delivery address whose country is fully banned for the shop', function () {
    $shop = new Shop();
    $shop->banned_country_regions = [
        'FR' => ['billing' => false, 'delivery' => true, 'ip_block' => false],
    ];

    $address = new Address();
    $address->country_code = 'FR';

    $action = makeForbiddenCountryCheckAction();

    expect($action->isDeliveryForbiddenForShop($shop, $address))->toBeTrue();
});

it('does not flag a delivery address whose country is not banned', function () {
    $shop = new Shop();
    $shop->banned_country_regions = [
        'FR' => ['billing' => false, 'delivery' => true, 'ip_block' => false],
    ];

    $address = new Address();
    $address->country_code = 'DE';

    $action = makeForbiddenCountryCheckAction();

    expect($action->isDeliveryForbiddenForShop($shop, $address))->toBeFalse();
});

it('honours the postcode regex when the country ban is scoped to a postcode', function () {
    $shop = new Shop();
    $shop->banned_country_regions = [
        'GB' => ['billing' => false, 'delivery' => true, 'ip_block' => false, 'postcode' => '/^BT/'],
    ];

    $action = makeForbiddenCountryCheckAction();

    $bannedAddress = new Address();
    $bannedAddress->country_code = 'GB';
    $bannedAddress->postal_code = 'BT1 1AA';
    expect($action->isDeliveryForbiddenForShop($shop, $bannedAddress))->toBeTrue();

    $allowedAddress = new Address();
    $allowedAddress->country_code = 'GB';
    $allowedAddress->postal_code = 'SW1A 1AA';
    expect($action->isDeliveryForbiddenForShop($shop, $allowedAddress))->toBeFalse();
});

it('returns false when there is no delivery address', function () {
    $shop = new Shop();
    $shop->banned_country_regions = [
        'FR' => ['billing' => false, 'delivery' => true, 'ip_block' => false],
    ];

    $action = makeForbiddenCountryCheckAction();

    expect($action->isDeliveryForbiddenForShop($shop, null))->toBeFalse();
});
