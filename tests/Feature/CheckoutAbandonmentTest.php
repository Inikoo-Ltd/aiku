<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\Ordering\CheckoutAbandonment\RunCheckoutAbandonmentScan;
use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Helpers\Address;
use App\Models\Ordering\CheckoutAbandonment;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function makeAbandonedCheckoutOrder(): Order
{
    [$organisation, $user, $shop] = createShop();
    $customer = createCustomer($shop);
    $website  = createWebsite($shop);
    $webUser  = createWebUser($customer);

    $order = StoreOrder::make()->action($customer, [
        'reference'        => 'CA-'.Str::random(6),
        'date'             => now()->toDateString(),
        'customer_id'      => $customer->id,
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);

    DB::table('orders')->where('id', $order->id)->update([
        'state'        => OrderStateEnum::CREATING->value,
        'submitted_at' => null,
        'total_amount' => 99,
        'created_at'   => now()->subHours(48),
    ]);

    $customer->update(['current_order_in_basket_id' => $order->id]);

    $visitorId = DB::table('website_visitors')->insertGetId([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'website_id'      => $website->id,
        'web_user_id'     => $webUser->id,
        'session_id'      => 'sess-'.Str::random(8),
        'visitor_hash'    => Str::random(16),
        'device_type'     => 'desktop',
        'os'              => 'linux',
        'browser'         => 'firefox',
        'user_agent'      => 'test-agent',
        'ip_hash'         => Str::random(16),
        'first_seen_at'   => now()->subHours(48),
        'last_seen_at'    => now()->subHours(25),
        'created_at'      => now()->subHours(48),
        'updated_at'      => now()->subHours(25),
    ]);

    DB::table('website_page_views')->insert([
        'group_id'           => $shop->group_id,
        'organisation_id'    => $shop->organisation_id,
        'shop_id'            => $shop->id,
        'website_id'         => $website->id,
        'website_visitor_id' => $visitorId,
        'page_url'           => 'https://test/app/checkout',
        'page_path'          => '/app/checkout',
        'view_date'          => now()->subHours(25)->toDateString(),
        'created_at'         => now()->subHours(25),
        'updated_at'         => now()->subHours(25),
    ]);

    return $order->refresh();
}

test('scan detects an abandoned checkout', function () {
    $order = makeAbandonedCheckoutOrder();

    RunCheckoutAbandonmentScan::run();

    $this->assertDatabaseHas('checkout_abandonments', [
        'order_id'    => $order->id,
        'customer_id' => $order->customer_id,
        'state'       => 'abandoned',
    ]);

    expect((float) CheckoutAbandonment::where('order_id', $order->id)->value('total_amount'))->toBe(99.0);
});

test('scan marks an abandonment recovered when the order leaves creating', function () {
    $order = makeAbandonedCheckoutOrder();

    RunCheckoutAbandonmentScan::run();
    $this->assertDatabaseHas('checkout_abandonments', [
        'order_id' => $order->id,
        'state'    => 'abandoned',
    ]);

    DB::table('orders')->where('id', $order->id)->update([
        'state'        => OrderStateEnum::SUBMITTED->value,
        'submitted_at' => now(),
    ]);

    RunCheckoutAbandonmentScan::run();

    $this->assertDatabaseHas('checkout_abandonments', [
        'order_id' => $order->id,
        'state'    => 'recovered',
    ]);

    expect(CheckoutAbandonment::where('order_id', $order->id)->value('recovered_at'))->not->toBeNull();
});
