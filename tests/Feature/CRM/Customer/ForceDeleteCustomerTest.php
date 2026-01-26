<?php

use App\Actions\CRM\Customer\ForceDeleteCustomer;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;

it('can force delete a customer with addresses', function () {
    [, , $shop] = createShop();
    $customer = createCustomer($shop);
    $customer->refresh();

    // The StoreCustomer action might have used addDirectAddress which doesn't use the pivot table
    // Let's ensure there's at least one pivot address
    if ($customer->addresses->isEmpty()) {
        $address = Address::factory()->create(['group_id' => $customer->group_id]);
        $customer->addresses()->attach($address->id, ['group_id' => $customer->group_id, 'scope' => 'test']);
        $customer->refresh();
    }

    // Ensure customer has addresses in the pivot table
    expect($customer->addresses)->not->toBeEmpty();

    $addressIds = $customer->addresses->pluck('id')->toArray();
    $billingAddressId = $customer->address_id;
    $deliveryAddressId = $customer->delivery_address_id;

    ForceDeleteCustomer::run($customer);

    expect(Customer::where('id', $customer->id)->withTrashed()->exists())->toBeFalse();

    foreach ($addressIds as $addressId) {
        expect(Address::where('id', $addressId)->exists())->toBeFalse();
    }

    if ($billingAddressId) {
        expect(Address::where('id', $billingAddressId)->exists())->toBeFalse();
    }

    if ($deliveryAddressId) {
        expect(Address::where('id', $deliveryAddressId)->exists())->toBeFalse();
    }
});
