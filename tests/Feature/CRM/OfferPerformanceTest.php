<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\GetShopOfferPerformance;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Artisan::call('migrate');

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->customer = createCustomer($this->shop);

    /* createShop()/createCustomer() reuse the same records across the file, so redemptions from an
       earlier test would otherwise still be counted here. */
    DB::table('transaction_has_offer_allowances')
        ->whereIn('order_id', DB::table('orders')->where('customer_id', $this->customer->id)->pluck('id'))
        ->delete();
    DB::table('orders')->where('customer_id', $this->customer->id)->delete();

    $this->campaignId = DB::table('offer_campaigns')->insertGetId([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'slug'            => 'oc-'.uniqid(),
        'code'            => 'oc-'.uniqid(),
        'name'            => 'Test Campaign',
        'type'            => 'discretionary',
        'data'            => '{}',
        'settings'        => '{}',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $this->offerId = DB::table('offers')->insertGetId([
        'offer_campaign_id' => $this->campaignId,
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'slug'            => 'off-'.uniqid(),
        'code'            => 'test-'.uniqid(),
        'name'            => 'Test Offer',
        'type'            => 'discretionary',
        'state'           => 'active',
        'status'          => true,
        'trigger_data'    => '{}',
        'data'            => '{}',
        'settings'        => '{}',
        'source_data'     => '{}',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $this->allowanceId = DB::table('offer_allowances')->insertGetId([
        'group_id'          => $this->shop->group_id,
        'organisation_id'   => $this->shop->organisation_id,
        'shop_id'           => $this->shop->id,
        'offer_campaign_id' => $this->campaignId,
        'offer_id'          => $this->offerId,
        'slug'              => 'oa-'.uniqid(),
        'data'              => '{}',
        'source_data'       => '{}',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
});

function redeemOffer($customer, $shop, int $offerId, string $date, ?int $campaignId = null, ?int $allowanceId = null): int
{
    $orderId = DB::table('orders')->insertGetId([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'slug'            => 'ord-'.uniqid(),
        'state'           => 'dispatched',
        'status'          => 'settled',
        'net_amount'      => 200,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);

    /* Two allowance rows for one order: an offer is written per transaction, so a basket of several
       discounted lines must still count as one redemption. */
    foreach ([1, 2] as $ignored) {
        DB::table('transaction_has_offer_allowances')->insert([
            'order_id'          => $orderId,
            'offer_id'          => $offerId,
            'offer_campaign_id'  => $campaignId,
            'offer_allowance_id' => $allowanceId,
            'model_type'        => 'Order',
            'model_id'          => $orderId,
            'discounted_amount' => 5,
            'data'              => '{}',
            'created_at'        => $date,
            'updated_at'        => $date,
        ]);
    }

    return $orderId;
}

it('counts one redemption per order, however many discounted lines it has', function () {
    redeemOffer($this->customer, $this->shop, $this->offerId, now()->subDay()->toDateTimeString(), $this->campaignId, $this->allowanceId);

    $offer = collect(GetShopOfferPerformance::run($this->shop, MarketingPeriodEnum::LAST_7)['offers'])
        ->firstWhere('name', 'Test Offer');

    expect($offer['orders'])->toBe(1)
        ->and($offer['customers'])->toBe(1)
        ->and($offer['discount'])->toBe(10.0);
});

it('reports the shop reach so uptake can be read as a proportion', function () {
    $performance = GetShopOfferPerformance::run($this->shop, MarketingPeriodEnum::LAST_7);

    expect($performance['reach'])->toHaveKeys(['emailed', 'customers'])
        ->and($performance['reach']['customers'])->toBeGreaterThan(0);
});

it('leaves lift empty when nobody was emailed, rather than inventing one', function () {
    redeemOffer($this->customer, $this->shop, $this->offerId, now()->subDay()->toDateTimeString(), $this->campaignId, $this->allowanceId);

    $offer = collect(GetShopOfferPerformance::run($this->shop, MarketingPeriodEnum::LAST_7)['offers'])
        ->firstWhere('name', 'Test Offer');

    expect($offer['emailed_customers'])->toBe(0)
        ->and($offer['lift'])->toBeNull();
});

it('ignores a cancelled order when counting redemptions', function () {
    $orderId = redeemOffer($this->customer, $this->shop, $this->offerId, now()->subDay()->toDateTimeString(), $this->campaignId, $this->allowanceId);
    DB::table('orders')->where('id', $orderId)->update(['state' => 'cancelled']);

    $offer = collect(GetShopOfferPerformance::run($this->shop, MarketingPeriodEnum::LAST_7)['offers'])
        ->firstWhere('name', 'Test Offer');

    expect($offer)->toBeNull();
});
