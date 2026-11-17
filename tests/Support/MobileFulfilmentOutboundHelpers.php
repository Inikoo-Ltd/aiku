<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-11h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Feature;

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\AttachPalletToReturn;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItemAudit\CompleteStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAuditFromPallet;
use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDelta;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/MobileApiHelpers.php';
require_once __DIR__.'/MobileGoodsOutHelpers.php';

function mfoFulfilment(Organisation $organisation, Warehouse $warehouse): Fulfilment
{
    static $fulfilments = [];

    $key = $organisation->id.'-'.$warehouse->id;
    if (!isset($fulfilments[$key]) || !$fulfilments[$key]->fresh()) {
        $shop              = StoreShop::run($organisation, array_merge(Shop::factory()->definition(), [
            'type'       => ShopTypeEnum::FULFILMENT->value,
            'warehouses' => [$warehouse->id],
        ]));
        $fulfilments[$key] = $shop->fulfilment;
    }

    return $fulfilments[$key]->fresh();
}

function mfoOtherWarehouse(Organisation $organisation): Warehouse
{
    static $warehouses = [];

    if (!isset($warehouses[$organisation->id]) || !$warehouses[$organisation->id]->fresh()) {
        $warehouses[$organisation->id] = StoreWarehouse::run($organisation, Warehouse::factory()->definition());
    }

    return $warehouses[$organisation->id]->fresh();
}

function mfoOtherOrganisation(Organisation $organisation): Organisation
{
    return Organisation::where('code', 'mfo2')->first()
        ?? StoreOrganisation::make()->action($organisation->group, array_merge(Organisation::factory()->definition(), ['code' => 'mfo2', 'type' => OrganisationTypeEnum::SHOP]));
}

function mfoLocation(Warehouse $warehouse, string $prefix = 'MFO'): Location
{
    return StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => $prefix.Str::upper(Str::random(6))]));
}

function mfoCustomer(Fulfilment $fulfilment): FulfilmentCustomer
{
    $customer = StoreCustomer::make()->action($fulfilment->shop, Customer::factory()->definition());
    $customer->update(['name' => 'MFO '.Str::upper(Str::random(10))]);

    UpdateFulfilmentCustomer::make()->action($customer->fulfilmentCustomer, [
        'pallets_storage' => true,
        'items_storage'   => true,
    ]);

    return $customer->fulfilmentCustomer->refresh();
}

function mfoPallet(FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, ?Location $location = null): Pallet
{
    return StorePallet::make()->action($fulfilmentCustomer, [
        'warehouse_id'       => $warehouse->id,
        'location_id'        => $location?->id,
        'state'              => PalletStateEnum::STORING->value,
        'status'             => PalletStatusEnum::STORING->value,
        'customer_reference' => 'MFO'.Str::upper(Str::random(8)),
    ])->refresh();
}

/**
 * @param array<string, mixed> $attributes
 */
function mfoStoredItem(FulfilmentCustomer $fulfilmentCustomer, array $attributes = []): StoredItem
{
    $storedItem = StoreStoredItem::make()->action($fulfilmentCustomer, ['reference' => 'MFO-'.Str::upper(Str::random(8))]);
    if ($attributes) {
        $storedItem->update($attributes);
    }

    return $storedItem->refresh();
}

function mfoStock(Pallet $pallet, StoredItem $storedItem, int $quantity): PalletStoredItem
{
    $audit = StoreStoredItemAuditFromPallet::make()->action($pallet, ['warehouse_id' => $pallet->warehouse_id]);
    StoreStoredItemAuditDelta::make()->action($audit, [
        'pallet_id'        => $pallet->id,
        'stored_item_id'   => $storedItem->id,
        'audited_quantity' => $quantity,
    ]);
    CompleteStoredItemAudit::make()->action($audit->refresh(), []);

    return PalletStoredItem::where('pallet_id', $pallet->id)->where('stored_item_id', $storedItem->id)->firstOrFail();
}

/**
 * @param array<int, Pallet> $pallets
 */
function mfoPalletReturn(FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, array $pallets, bool $isCollection = false): PalletReturn
{
    $palletReturn = StorePalletReturn::make()->action($fulfilmentCustomer, ['warehouse_id' => $warehouse->id, 'is_collection' => $isCollection]);
    foreach ($pallets as $pallet) {
        AttachPalletToReturn::make()->action($palletReturn->refresh(), $pallet->refresh());
    }

    return SubmitAndConfirmPalletReturn::make()->action($palletReturn->refresh())->refresh();
}

/**
 * @param array<int, array{0: PalletStoredItem, 1: int}> $lines
 */
function mfoItemsReturn(FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, array $lines, bool $isCollection = false): PalletReturn
{
    $palletReturn = StorePalletReturn::make()->actionWithStoredItems($fulfilmentCustomer, ['warehouse_id' => $warehouse->id, 'is_collection' => $isCollection]);
    foreach ($lines as [$palletStoredItem, $quantity]) {
        AttachStoredItemToReturn::make()->action($palletReturn->refresh(), $palletStoredItem->refresh(), ['quantity_ordered' => $quantity]);
    }

    return SubmitAndConfirmPalletReturn::make()->action($palletReturn->refresh())->refresh();
}

function mfoWorker(object $ctx): User
{
    return actingAsGoodsOutUser($ctx->group, $ctx->warehouse, [RolesEnum::FULFILMENT_WAREHOUSE_WORKER]);
}

function mfoViewer(object $ctx): User
{
    return actingAsGoodsOutUser($ctx->group, $ctx->warehouse, [], ["fulfilment.{$ctx->warehouse->id}.view"]);
}

function mfoShopClerk(object $ctx): User
{
    return actingAsGoodsOutUser($ctx->group, $ctx->warehouse, [], ["fulfilment.{$ctx->warehouse->id}.view", "fulfilment-shop.{$ctx->fulfilment->id}.edit"]);
}

/**
 * @param array<string, mixed> $parameters
 * @param array<string, mixed> $query
 */
function mfoGet(string $route, array $parameters, array $query = []): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.'.$route, $parameters, $query);
}

/**
 * @param array<string, mixed> $parameters
 * @param array<string, mixed> $body
 */
function mfoPost(string $route, array $parameters, array $body = []): TestResponse
{
    return postGoodsOut('grp.api.mobile.warehouses.'.$route, $parameters, $body);
}

/**
 * @param array<string, mixed> $parameters
 */
function mfoDelete(string $route, array $parameters): TestResponse
{
    return deleteGoodsOut('grp.api.mobile.warehouses.'.$route, $parameters);
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function mfoReturnRoute(Warehouse $warehouse, PalletReturn $palletReturn, array $extra = []): array
{
    return ['warehouse' => $warehouse->id, 'palletReturn' => $palletReturn->id, ...$extra];
}

/**
 * @param array<int, array{key: string, label: string}> $actions
 * @return array<int, string>
 */
function mfoActionKeys(array $actions): array
{
    return collect($actions)->pluck('key')->all();
}
