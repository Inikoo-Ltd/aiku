<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\CRM\TrafficSource\GetShopEmailMarketingPerformance;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Artisan::call('migrate');
    config()->set('services.ses.cost_per_thousand_usd', 100.0);

    // Pin the USD rate so the estimated-cost assertions never depend on a live exchange fetch.
    \Illuminate\Support\Facades\Cache::put('current-currency-exchange:USD-GBP', 1.0, 600);
    \Illuminate\Support\Facades\Cache::put('current-currency-exchange:USD-EUR', 1.0, 600);

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->customer = createCustomer($this->shop);
    $this->customer->update(['traffic_sources' => null]);
    $this->customer->trafficSources()->detach();

    $this->googleAds  = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    $this->newsletter = createTrafficSource($this->shop, 'newsletter', 'Newsletter');
});

function makeMailshot($shop): Mailshot
{
    $outbox = $shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();

    return StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());
}

it('returns empty performance for a shop that has sent nothing', function () {
    $performance = GetShopEmailMarketingPerformance::run($this->shop);

    expect($performance['mailshots'])->toBe([]);
    expect($performance['totals']['sent'])->toBe(0);
    expect($performance['totals']['attributed_revenue'])->toBe(0.0);
});

it('splits customer credit between channels so their stats sum to the real totals', function () {
    $this->customer->update(['traffic_sources' => '1700000000b']);
    \App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

    DB::table('customer_stats')->where('customer_id', $this->customer->id)->update([
        'sales_all'                      => 1000,
        'number_orders_state_dispatched' => 4,
    ]);

    TrafficSourceHydrateCustomers::run($this->googleAds);
    TrafficSourceHydrateCustomers::run($this->newsletter);

    $googleStats     = $this->googleAds->stats()->first();
    $newsletterStats = $this->newsletter->stats()->first();

    expect((float) $googleStats->total_customer_revenue)->toBe(500.0);
    expect((float) $newsletterStats->total_customer_revenue)->toBe(500.0);
    expect((float) $googleStats->number_customers)->toBe(0.5);
    expect((float) $googleStats->number_customer_purchases)->toBe(2.0);

    expect(
        (float) $googleStats->total_customer_revenue + (float) $newsletterStats->total_customer_revenue
    )->toBe(1000.0);
});

it('reports engagement, estimated cost and attributed revenue per mailshot', function () {
    $mailshot = makeMailshot($this->shop);

    $mailshot->stats()->update([
        'number_dispatched_emails'                    => 2000,
        'number_dispatched_emails_state_opened'       => 500,
        'number_dispatched_emails_state_clicked'      => 100,
        'number_dispatched_emails_state_unsubscribed' => 7,
    ]);

    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'), $mailshot);

    DB::table('customer_stats')->where('customer_id', $this->customer->id)->update(['sales_all' => 250]);

    $performance = GetShopEmailMarketingPerformance::run($this->shop);

    expect($performance['mailshots'])->toHaveCount(1);
    $row = $performance['mailshots'][0];

    expect($row['sent'])->toBe(2000);
    expect($row['opened'])->toBe(600);
    expect($row['clicked'])->toBe(100);
    expect($row['unsubscribed'])->toBe(7);
    expect($row['estimated_cost'])->toBe(200.0);
    expect($row['attributed_revenue'])->toBe(250.0);
    expect($row['attributed_customers'])->toBe(1.0);

    expect($performance['totals']['estimated_cost'])->toBe(200.0);
    expect($performance['totals']['attributed_revenue'])->toBe(250.0);
});

it('attributes only the newsletter share of a customer other channels also touched', function () {
    $mailshot = makeMailshot($this->shop);
    $mailshot->stats()->update(['number_dispatched_emails' => 100]);

    $this->customer->update(['traffic_sources' => '1700000000b']);
    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'), $mailshot);

    DB::table('customer_stats')->where('customer_id', $this->customer->id)->update(['sales_all' => 1000]);

    $performance = GetShopEmailMarketingPerformance::run($this->shop);

    expect($performance['mailshots'][0]['attributed_revenue'])->toBe(500.0);
    expect($performance['mailshots'][0]['attributed_customers'])->toBe(0.5);
});

it('credits the mailshot channel with the registration when a prospect converts', function () {
    createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    $mailshot = makeMailshot($this->shop);
    $mailshot->stats()->update(['number_dispatched_emails' => 50]);

    $prospect = \App\Actions\CRM\Prospect\StoreProspect::make()->action(
        $this->shop,
        array_merge(\App\Models\CRM\Prospect::factory()->definition(), [
            'email' => 'converting-'.uniqid().'@example.com',
        ])
    );

    RecordEmailClickTouchpoint::run($prospect, Carbon::parse('2026-01-01 10:00:00'), $mailshot);

    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $this->shop,
        array_merge(\App\Models\CRM\Customer::factory()->definition(), [
            'email'           => $prospect->email,
            'traffic_sources' => '1735689600a',
        ])
    );

    // StoreCustomer only runs the prospect match on is_aiku shops; invoke it directly here.
    \App\Actions\CRM\Customer\MatchCustomerProspects::run($customer);

    expect($customer->fresh()->traffic_sources)->toContain('pmailshot-'.$mailshot->id);
    expect($customer->fresh()->traffic_sources)->toContain('1735689600a');

    $types = $customer->trafficSources()->pluck('type')->all();
    expect($types)->toContain('newsletter');
    expect($types)->toContain('organic-google');

    $performance = GetShopEmailMarketingPerformance::run($this->shop);
    expect($performance['mailshots'][0]['prospects_registered'])->toBe(1);
});

it('serves honest share-weighted numbers through the sql views', function () {
    $mailshot = makeMailshot($this->shop);
    $mailshot->stats()->update(['number_dispatched_emails' => 100]);

    $this->customer->update(['traffic_sources' => '1700000000b']);
    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'), $mailshot);

    DB::table('customer_stats')->where('customer_id', $this->customer->id)->update(['sales_all' => 1000]);
    TrafficSourceHydrateCustomers::run($this->googleAds);
    TrafficSourceHydrateCustomers::run($this->newsletter);

    $channels = collect(DB::select(
        'SELECT * FROM marketing_channel_performance WHERE shop_id = ?',
        [$this->shop->id]
    ))->keyBy('type');

    expect((float) $channels['google-ads']->revenue)->toBe(500.0);
    expect((float) $channels['newsletter']->revenue)->toBe(500.0);
    expect((float) $channels['google-ads']->registrations)->toBe(0.5);

    $mailshotRow = collect(DB::select(
        'SELECT * FROM marketing_mailshot_performance WHERE mailshot_id = ?',
        [$mailshot->id]
    ))->first();

    expect((int) $mailshotRow->sent)->toBe(100);
    expect((float) $mailshotRow->attributed_revenue)->toBe(500.0);
    expect((float) $mailshotRow->attributed_customers)->toBe(0.5);
});

it('keeps repeat clickers visible to every mailshot they clicked', function () {
    $first  = makeMailshot($this->shop);
    $second = makeMailshot($this->shop);
    $first->stats()->update(['number_dispatched_emails' => 10]);
    $second->stats()->update(['number_dispatched_emails' => 10]);

    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'), $first);
    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-02 10:00:00'), $second);

    DB::table('customer_stats')->where('customer_id', $this->customer->id)->update(['sales_all' => 1000]);

    $performance = collect(GetShopEmailMarketingPerformance::run($this->shop)['mailshots'])->keyBy('id');

    expect($performance[$first->id]['attributed_revenue'])->toBe(500.0);
    expect($performance[$second->id]['attributed_revenue'])->toBe(500.0);
    expect($performance[$first->id]['attributed_customers'])->toBe(0.5);
});

it('counts prospects who clicked a mailshot and later registered', function () {
    $mailshot = makeMailshot($this->shop);
    $mailshot->stats()->update(['number_dispatched_emails' => 50]);

    $prospect = \App\Actions\CRM\Prospect\StoreProspect::make()->action(
        $this->shop,
        \App\Models\CRM\Prospect::factory()->definition()
    );

    RecordEmailClickTouchpoint::run($prospect, Carbon::parse('2026-01-01 10:00:00'), $mailshot);

    $performance = GetShopEmailMarketingPerformance::run($this->shop);
    expect($performance['mailshots'][0]['prospects_registered'])->toBe(0);

    $prospect->update(['customer_id' => $this->customer->id]);

    $performance = GetShopEmailMarketingPerformance::run($this->shop);
    expect($performance['mailshots'][0]['prospects_registered'])->toBe(1);
});
