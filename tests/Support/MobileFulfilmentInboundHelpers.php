<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Feature;

use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPallet;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Address;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/MobileApiHelpers.php';
require_once __DIR__.'/MobileGoodsOutHelpers.php';

function mobileFulInboundRental(Fulfilment $fulfilment): Rental
{
    static $rentals = [];

    return $rentals[$fulfilment->id] ??= StoreRental::make()->action($fulfilment->shop, [
        'price' => 10,
        'unit'  => RentalUnitEnum::WEEK->value,
        'code'  => 'MFR'.Str::upper(Str::random(6)),
        'name'  => 'Mobile fulfilment inbound rental',
    ]);
}

function mobileFulInboundOtherWarehouse(Organisation $organisation): Warehouse
{
    static $warehouses = [];

    return $warehouses[$organisation->id] ??= StoreWarehouse::make()->action($organisation, Warehouse::factory()->definition());
}

function mobileFulInboundCustomer(Fulfilment $fulfilment, bool $itemsStorage = true, bool $withRentalAgreement = true, ?string $name = null): FulfilmentCustomer
{
    $name ??= 'MFI '.Str::upper(Str::random(8));

    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action($fulfilment, [
        'state'           => CustomerStateEnum::ACTIVE,
        'status'          => CustomerStatusEnum::APPROVED,
        'contact_name'    => $name,
        'company_name'    => $name,
        'interest'        => ['pallets_storage', 'items_storage'],
        'contact_address' => Address::factory()->definition(),
    ]);
    UpdateFulfilmentCustomer::make()->action($fulfilmentCustomer, [
        'pallets_storage' => true,
        'items_storage'   => $itemsStorage,
    ]);

    if ($withRentalAgreement) {
        StoreRentalAgreement::make()->action($fulfilmentCustomer, [
            'state'         => RentalAgreementStateEnum::ACTIVE,
            'billing_cycle' => RentalAgreementBillingCycleEnum::MONTHLY,
            'pallets_limit' => null,
        ]);
    }

    return $fulfilmentCustomer->refresh();
}

/**
 * @param array<int, array<string, mixed>> $pallets
 */
function mobileFulInboundDelivery(FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, array $pallets = [[]]): PalletDelivery
{
    $palletDelivery = StorePalletDelivery::make()->action($fulfilmentCustomer, ['warehouse_id' => $warehouse->id]);
    $palletDelivery->update(['warehouse_id' => $warehouse->id]);
    $rental         = mobileFulInboundRental($fulfilmentCustomer->fulfilment);

    foreach ($pallets as $attributes) {
        $pallet = StorePalletCreatedInPalletDelivery::make()->action($palletDelivery, array_merge(['customer_reference' => 'MFC'.Str::upper(Str::random(8))], $attributes));
        $pallet->update(['rental_id' => $rental->id]);
    }

    return $palletDelivery->refresh();
}

function mobileFulInboundConfirmed(PalletDelivery $palletDelivery): PalletDelivery
{
    return ConfirmPalletDelivery::make()->action($palletDelivery->refresh())->refresh();
}

function mobileFulInboundReceived(PalletDelivery $palletDelivery): PalletDelivery
{
    return ReceivePalletDelivery::make()->action(mobileFulInboundConfirmed($palletDelivery))->refresh();
}

function mobileFulInboundBookingIn(PalletDelivery $palletDelivery): PalletDelivery
{
    return StartBookingPalletDelivery::make()->action(mobileFulInboundReceived($palletDelivery))->refresh();
}

/**
 * @return array<int, Pallet>
 */
function mobileFulInboundPallets(PalletDelivery $palletDelivery): array
{
    return $palletDelivery->pallets()->orderBy('id')->get()->all();
}

/**
 * @param array<string, mixed> $attributes
 */
function mobileFulInboundLocation(Warehouse $warehouse, array $attributes = []): Location
{
    $location = StoreLocation::make()->action($warehouse, ['code' => 'MFL'.Str::upper(Str::random(6))]);
    $location->update(array_merge(['status' => LocationStatusEnum::OPERATIONAL, 'allow_fulfilment' => true], $attributes));

    return $location->refresh();
}

/**
 * @param array<string, mixed> $attributes
 */
function mobileFulInboundStoredPallet(FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, ?Location $location = null, array $attributes = []): Pallet
{
    return StorePallet::make()->action($fulfilmentCustomer, array_merge([
        'warehouse_id'       => $warehouse->id,
        'customer_reference' => 'MFS'.Str::upper(Str::random(8)),
        'state'              => PalletStateEnum::STORING,
        'status'             => PalletStatusEnum::STORING,
        'location_id'        => $location?->id,
    ], $attributes));
}

function mobileFulInboundStoredItem(Pallet $pallet, int $quantity, ?string $reference = null): StoredItem
{
    $storedItem = StoreStoredItem::make()->action($pallet->fulfilmentCustomer, ['reference' => $reference ?? 'MFI'.Str::upper(Str::random(8))]);
    SyncStoredItemToPallet::make()->action($pallet, ['stored_item_ids' => [$storedItem->id => ['quantity' => $quantity]]]);

    return $storedItem->refresh();
}

/**
 * @param array<int, RolesEnum> $roles
 * @param array<int, string> $permissions
 */
function mobileFulInboundUser(object $test, array $roles = [RolesEnum::FULFILMENT_WAREHOUSE_WORKER], array $permissions = []): User
{
    return actingAsGoodsOutUser($test->group, $test->warehouse, $roles, $permissions);
}

function mobileFulInboundShopClerk(object $test): User
{
    return mobileFulInboundUser($test, [], ["fulfilment.{$test->warehouse->id}.view", "fulfilment-shop.{$test->fulfilment->id}.edit"]);
}

function mobileFulInboundClerkWithShopRight(object $test): User
{
    return mobileFulInboundUser($test, [RolesEnum::FULFILMENT_WAREHOUSE_WORKER], ["fulfilment-shop.{$test->fulfilment->id}.edit"]);
}

/**
 * @param array<string, mixed> $query
 */
function mobileFulInboundGet(string $route, array $parameters, array $query = []): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.'.$route, $parameters, $query);
}

/**
 * @param array<string, mixed> $body
 */
function mobileFulInboundPost(string $route, array $parameters, array $body = []): TestResponse
{
    return postGoodsOut('grp.api.mobile.warehouses.'.$route, $parameters, $body);
}

/**
 * @return array<int, string>
 */
function mobileFulInboundKeys(mixed $data): array
{
    if (!is_array($data)) {
        return [];
    }

    return collect($data)->flatMap(fn ($value, $key) => [...(is_string($key) ? [$key] : []), ...mobileFulInboundKeys($value)])->unique()->values()->all();
}
