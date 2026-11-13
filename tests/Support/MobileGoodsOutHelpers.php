<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePicker;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\JobPosition\SyncEmployeeJobPositions;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Goods\Stock;
use App\Models\Helpers\Address;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';

/**
 * @param array<int, RolesEnum> $roles
 * @param array<int, string> $permissions
 */
function actingAsGoodsOutUser(Group $group, Warehouse $warehouse, array $roles = [], array $permissions = []): User
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    setPermissionsTeamId($group->id);

    $user = createMobileAppUser($group);
    foreach ($roles as $role) {
        $user->assignRole(RolesEnum::getRoleName($role->value, $warehouse));
    }
    if ($permissions) {
        $user->givePermissionTo($permissions);
    }
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    return $user;
}

/**
 * @param array<string, mixed> $attributes
 */
function goodsOutDeliveryNote(Order $order, Warehouse $warehouse, array $attributes = []): DeliveryNote
{
    $deliveryNote = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'GO'.Str::upper(Str::random(8)),
        'email'            => 'test@email.com',
        'phone'            => '+62081353890000',
        'date'             => now()->toDateString(),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $warehouse->id,
    ]);

    if ($attributes) {
        $deliveryNote->update($attributes);
    }

    if ($deliveryNote->picker_user_id) {
        DeliveryNoteHydratePicker::run($deliveryNote->id);
    }

    return $deliveryNote->refresh();
}

function goodsOutOrgStock(Group $group, Order $order, string $code, int $packedIn = 1): OrgStock
{
    $stock    = StoreStock::make()->action($group, array_merge(Stock::factory()->definition(), ['code' => $code]));
    $orgStock = StoreOrgStock::make()->action($order->organisation, $stock);
    $orgStock->update(['packed_in' => $packedIn, 'barcode' => 'GOB'.Str::upper(Str::random(10))]);

    return $orgStock->refresh();
}

function goodsOutLocation(Warehouse $warehouse, ?string $sortCode = null): Location
{
    $location = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => 'GOL'.Str::upper(Str::random(6))]));
    $location->update(['sort_code' => $sortCode]);

    return $location->refresh();
}

function goodsOutLine(DeliveryNote $deliveryNote, Order $order, OrgStock $orgStock, Location $location, float $quantityRequired): DeliveryNoteItem
{
    StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => 30, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    );

    return StoreDeliveryNoteItem::make()->action($deliveryNote, [
        'delivery_note_id'  => $deliveryNote->id,
        'org_stock_id'      => $orgStock->id,
        'transaction_id'    => $order->transactions()->first()->id,
        'quantity_required' => $quantityRequired,
    ]);
}

function goodsOutDropshippingOrder(): Order
{
    [, , $shop] = createOwnShop('mobile-goods-out-dropshipping');
    $order      = createOrder(createCustomer($shop), createProduct($shop)[1]);
    $shop->update(['type' => ShopTypeEnum::DROPSHIPPING]);

    return $order;
}

/**
 * @param array<string, mixed> $query
 */
function getGoodsOut(string $route, mixed $parameters, array $query = []): TestResponse
{
    return getJson(route($route, $parameters).($query ? '?'.http_build_query($query) : ''), ['X-Aiku-App-Key' => 'mobile-test-key']);
}

/**
 * @param array<string, mixed> $query
 * @return array<int, string>
 */
function goodsOutReferences(Warehouse $warehouse, array $query): array
{
    return collect(getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $warehouse->id, $query)->assertOk()->json('data.delivery_notes'))->pluck('reference')->all();
}

/**
 * @param array<string, mixed> $body
 */
function postGoodsOut(string $route, mixed $parameters, array $body = []): TestResponse
{
    return postJson(route($route, $parameters), $body, ['X-Aiku-App-Key' => 'mobile-test-key']);
}

function deleteGoodsOut(string $route, mixed $parameters): TestResponse
{
    return deleteJson(route($route, $parameters), [], ['X-Aiku-App-Key' => 'mobile-test-key']);
}

/**
 * @param array<string, bool> $settings
 */
function setGoodsOutPickingSettings(Organisation $organisation, array $settings): void
{
    $organisationSettings = $organisation->settings;
    foreach ($settings as $setting => $value) {
        data_set($organisationSettings, "orders.$setting", $value);
    }
    $organisation->update(['settings' => $organisationSettings]);
}

function goodsOutSubmittedOrder(object $ctx): Order
{
    $product = createProduct($ctx->shop)[1];
    $product->update(['is_indivisible' => false]);

    $order = StoreOrder::make()->action($ctx->customer, [
        'reference'        => 'MP'.Str::upper(Str::random(8)),
        'date'             => date('Y-m-d'),
        'customer_id'      => $ctx->customer->id,
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    StoreTransaction::make()->action($order, $product->historicAsset, Transaction::factory()->definition());

    return SubmitOrder::make()->action($order);
}

/**
 * @param array<int, float> $quantities
 * @return array{0: DeliveryNote, 1: array<int, DeliveryNoteItem>}
 */
function goodsOutPickingNote(object $ctx, array $quantities = [2], ?User $picker = null): array
{
    $order        = goodsOutSubmittedOrder($ctx);
    $deliveryNote = SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $ctx->warehouse->id]);
    $deliveryNote->deliveryNoteItems()->delete();

    $lines = [];
    foreach ($quantities as $quantity) {
        $lines[] = goodsOutLine($deliveryNote, $order, goodsOutOrgStock($ctx->group, $order, 'PK'.Str::upper(Str::random(6))), goodsOutLocation($ctx->warehouse), $quantity);
    }

    if ($picker) {
        StartHandlingDeliveryNote::make()->action($deliveryNote->refresh(), $picker);
    }

    return [$deliveryNote->refresh(), array_map(fn (DeliveryNoteItem $line) => $line->refresh(), $lines)];
}

function goodsOutLineLocation(DeliveryNoteItem $line): LocationOrgStock
{
    return LocationOrgStock::where('org_stock_id', $line->org_stock_id)->orderBy('id')->firstOrFail();
}

function goodsOutPickerUser(object $ctx): User
{
    $jobPosition = JobPosition::where('organisation_id', $ctx->organisation->id)
        ->whereIn('name', ['Picker', 'Exception Picker', 'Dispatching supervisor'])
        ->orderBy('id')
        ->firstOrFail();

    $employee = StoreEmployee::make()->action($ctx->organisation, array_merge(Employee::factory()->definition(), [
        'alias'         => 'MP'.Str::random(8),
        'worker_number' => 'MP'.Str::random(8),
        'state'         => EmployeeStateEnum::WORKING,
        'username'      => 'mobilepicker'.Str::lower(Str::random(10)),
        'password'      => 'mobile-picker-password',
    ]));
    SyncEmployeeJobPositions::run($employee, [$jobPosition->id => []]);

    return $employee->getUser();
}

/**
 * @return array<int, string>
 */
function goodsOutActionKeys(DeliveryNote $deliveryNote): array
{
    return collect(getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()->json('data.available_actions'))->pluck('key')->all();
}
