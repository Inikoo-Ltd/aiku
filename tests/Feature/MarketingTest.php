<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

/*
 * Every marketing and attribution test lives here rather than in a file per subject.
 * Each test file pays its own loadDB() restore, and that restore dominates the run: twenty-one
 * files meant twenty-one restores of the same database to run the same suite. One file, one
 * restore, and each former file keeps its own setup inside its own describe() block.
 */

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UI\GetCustomerJourney;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\TrafficSource\GetAggregatedMarketingOverview;
use App\Actions\CRM\TrafficSource\GetAttributionWindow;
use App\Actions\CRM\TrafficSource\GetShopAttributionDataQuality;
use App\Actions\CRM\TrafficSource\GetShopEmailMarketingPerformance;
use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Actions\CRM\TrafficSource\GetShopOfferPerformance;
use App\Actions\CRM\TrafficSource\GetTrafficSourceFromRefererHeader;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCosts;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Actions\CRM\TrafficSource\ProcessTrafficSourceShare;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Actions\CRM\TrafficSource\ReceiveTrafficSourceCostWebhook;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Actions\CRM\TrafficSource\RecordTrafficSourceClick;
use App\Actions\Iris\CaptureTrafficSource;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Actions\CRM\TrafficSource\UI\IndexTrafficSources;
use App\Actions\CRM\TrafficSource\UI\TrafficSourceTabsEnum;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Comms\EmailTrackingEvent\StoreEmailTrackingEvent;
use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\Ordering\Order\ProcessOrderTrafficSource;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCost;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeAll(function () {
    loadDB();
});

/*
 * Every block below shares one shop and one customer: createShop() hands back Shop::first() and
 * createCustomer() the shop's first customer. That was harmless while each block was its own file,
 * because a file restored the database before it ran. Sharing one database in sequence, a block
 * inherits whatever the blocks above it left behind, and the failures that causes are always a count
 * too high, never one too low.
 *
 * Called as the first line of every block's beforeEach rather than from a beforeEach of its own: the
 * reset has to happen before the block builds its fixtures, and calling it explicitly is the only
 * ordering that cannot be got wrong.
 */
function resetMarketingFixtures(): void
{
    DB::table('model_has_traffic_sources')->delete();
    DB::table('traffic_source_costs')->delete();
    DB::table('traffic_source_campaign_stats')->delete();
    DB::table('traffic_source_campaigns')->delete();
    DB::table('traffic_source_visits')->delete();
    DB::table('customers')->update(['traffic_sources' => null]);

    /* Only the rows the fixtures insert. A real invoice or order has a dozen FK children, and the
       seeded ones some blocks read are not ours to delete; createInvoiceFor and
       createDispatchedOrderFor stamp theirs with these prefixes. */
    DB::table('invoices')->where('reference', 'like', 'INV-%')->delete();
    DB::table('orders')->where('slug', 'like', 'ord-%')->delete();

    /* GetAttributionStartedAt caches the earliest touch on record for an hour, which is right in
       production - it only ever moves once - and wrong the moment the touch history is rewritten.
       Left cached, the first block's touch marks where recording began for the whole run, and every
       later block's dashboard is clipped to it: revenue, orders and registrations dated before it
       read zero, which is what six of these blocks were failing on. */
    Cache::forget('marketing:attribution_started_at');
}

/**
 * Removes the mailshots a previous block sent from this shop, for blocks whose figures must not
 * include them. GetEstimatedEmailCost prices marketing spend off
 * `mailshot_stats.number_dispatched_emails` for the shop, so a block that sent nothing otherwise
 * carries the previous block's sends as spend it never made.
 *
 * Called from the blocks that must not see them, never globally: the blocks that send the mailshots
 * assert on exactly these rows, and deleting them for everyone breaks those instead.
 */
function clearMailshotsFor($shop): void
{
    $mailshots = DB::table('mailshots')->where('shop_id', $shop->id)->pluck('id');

    if ($mailshots->isEmpty()) {
        return;
    }

    $emails = DB::table('mailshot_has_dispatched_emails')->whereIn('mailshot_id', $mailshots)->pluck('dispatched_email_id');

    DB::table('mailshot_has_dispatched_emails')->whereIn('mailshot_id', $mailshots)->delete();
    DB::table('mailshot_recipients')->whereIn('mailshot_id', $mailshots)->delete();
    DB::table('mailshot_stats')->whereIn('mailshot_id', $mailshots)->delete();

    if ($emails->isNotEmpty()) {
        DB::table('customer_has_dispatched_emails')->whereIn('dispatched_email_id', $emails)->delete();
        DB::table('model_has_dispatched_emails')->whereIn('dispatched_email_id', $emails)->delete();
        DB::table('email_bulk_run_has_dispatched_emails')->whereIn('dispatched_email_id', $emails)->delete();
        DB::table('email_ongoing_run_has_dispatched_emails')->whereIn('dispatched_email_id', $emails)->delete();
        DB::table('dispatched_emails')->whereIn('id', $emails)->delete();
    }

    DB::table('mailshots')->whereIn('id', $mailshots)->delete();
}

function windowInvoice(string $date, float $net, $customer, $shop): void
{
    DB::table('invoices')->insert([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => $net,
        'total_amount'    => $net,
        'in_process'      => false,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);
}

function dataQualityCheck(string $key, $shop, MarketingPeriodEnum $period = MarketingPeriodEnum::LAST_30): array
{
    $checks = GetShopAttributionDataQuality::run($shop, $period)['checks'];

    return collect($checks)->firstWhere('key', $key);
}

function journeyCustomer(Shop $shop, ?string $trafficSources): Customer
{
    return StoreCustomer::make()->action(
        $shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => $trafficSources,
        ])
    );
}

function journeyInvoice(Customer $customer, string $date, float $netAmount): Invoice
{
    return StoreInvoice::make()->action(
        $customer,
        array_merge(Invoice::factory()->definition(), [
            'date'       => $date,
            'net_amount' => $netAmount,
            'in_process' => false,
        ])
    );
}

function pretendWeOwn(string $domain): void
{
    Cache::put('marketing:our_own_domains', collect([$domain]), now()->addHour());
}

function fakeRouteForCustomers(): void
{
    $route = (new Route('GET', '/_test', []))->name('test.traffic_sources.show');

    request()->setRouteResolver(fn () => $route);
}

function campaignFor($trafficSource, string $reference): TrafficSourceCampaign
{
    return TrafficSourceCampaign::firstOrCreate(
        ['traffic_source_id' => $trafficSource->id, 'reference' => $reference],
        ['name' => 'Campaign '.$reference, 'type' => $trafficSource->type]
    );
}

function postChannelTypeCosts(array $costs, string $token): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson(route('webhooks.traffic_source_costs'), [
            'source'   => 'google-ads',
            'currency' => test()->shop->currency->code,
            'costs'    => $costs,
        ]);
}

function fakeCurrentRoute(): void
{
    $route = (new Route('GET', '/_test', []))->name('test.traffic_sources.index');

    request()->setRouteResolver(fn () => $route);
}

function costCsvFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'costs').'.csv';
    file_put_contents($path, "shop,source,campaign,date,amount,currency\n".implode("\n", $rows)."\n");

    return $path;
}

function importCosts(array $rows, array $options = []): int
{
    return Artisan::call('traffic-source:import-costs', array_merge(
        ['file' => costCsvFile($rows)],
        $options
    ));
}

function postCosts(string $token, array $payload)
{
    return test()->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('webhooks.traffic_source_costs'), $payload);
}

function costPayload(array $overrides = []): array
{
    return array_merge([
        'source'   => 'google-ads',
        'currency' => test()->shop->currency->code,
        'costs'    => [
            ['date' => '2026-08-06', 'campaign' => '21723300927', 'campaign_name' => 'Brand', 'amount' => 153.22],
        ],
    ], $overrides);
}

function metaInsights(array $rows, ?string $next = null): array
{
    return array_filter([
        'data'   => $rows,
        'paging' => $next ? ['next' => $next] : null,
    ]);
}

function metaRow(string $campaignId, float $spend, string $currency, string $date = '2026-08-07', string $platform = 'facebook'): array
{
    return [
        'campaign_id'        => $campaignId,
        'campaign_name'      => 'Campaign '.$campaignId,
        'publisher_platform' => $platform,
        'spend'              => (string) $spend,
        'account_currency'   => $currency,
        'date_start'         => $date,
        'date_stop'          => $date,
    ];
}

function igSplitInsights(array $rows): array
{
    return ['data' => $rows];
}

function igSplitRow(string $campaignId, float $spend, string $currency, string $platform, string $date = '2026-08-07'): array
{
    return [
        'campaign_id'        => $campaignId,
        'campaign_name'      => 'Campaign '.$campaignId,
        'publisher_platform' => $platform,
        'spend'              => (string) $spend,
        'account_currency'   => $currency,
        'date_start'         => $date,
        'date_stop'          => $date,
    ];
}

function dispatchedEmailFor($outbox, $customer): DispatchedEmail
{
    $dispatchedEmail = $outbox->dispatchedEmails()->create([
        'email_address_id' => $customer->email ? null : null,
        'data'             => [],
    ]);

    $customer->dispatchedEmails()->attach($dispatchedEmail);

    return $dispatchedEmail;
}

function makeMailshot($shop): Mailshot
{
    $outbox = $shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();

    return StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());
}

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

function invoiceOn(string $date, float $net, $customer, $shop, bool $inProcess = false): void
{
    DB::table('invoices')->insert([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => $shop->taxCategory?->id ?? App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => $net,
        'total_amount'    => $net,
        'in_process'      => $inProcess,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);
}

describe('traffic source attribution', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('traffic source attribution');
    });

    it('attaches a single traffic source without a campaign to a newly registered customer', function () {
        $trafficSource = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000a',
            ])
        );

        expect($customer->trafficSources()->count())->toBe(1);

        $pivot = $customer->trafficSources()->first()->pivot;
        expect($pivot->traffic_source_id)->toBe($trafficSource->id);
        expect((float) $pivot->share)->toBe(1.0);
        expect($pivot->traffic_source_campaign_id)->toBeNull();
    });

    it('splits credit across multiple distinct traffic sources on registration', function () {
        createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000a|1700000100b',
            ])
        );

        expect($customer->trafficSources()->count())->toBe(2);

        $totalShare = (float) $customer->trafficSources()->sum('model_has_traffic_sources.share');
        expect(round($totalShare, 2))->toBe(1.0);
    });

    it('links the matching campaign reference to the pivot record', function () {
        $trafficSource = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $campaign = TrafficSourceCampaign::create([
            'traffic_source_id' => $trafficSource->id,
            'reference'         => 'summer-'.uniqid(),
            'name'              => 'Summer Sale',
            'type'              => 'search',
        ]);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000b'.$campaign->reference,
            ])
        );

        $pivot = $customer->trafficSources()->first()->pivot;
        expect($pivot->traffic_source_campaign_id)->toBe($campaign->id);
    });

    it('keeps a row per campaign with the source credit summing to one', function () {
        $trafficSource = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $campaigns = collect(['spring', 'summer'])->map(fn (string $season) => TrafficSourceCampaign::create([
            'traffic_source_id' => $trafficSource->id,
            'reference'         => $season.'-'.uniqid(),
            'name'              => ucfirst($season).' Sale',
            'type'              => 'search',
        ]));

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000b'.$campaigns[0]->reference.'|1700000100b'.$campaigns[1]->reference,
            ])
        );

        $rows = $customer->trafficSources()->get();

        expect($rows)->toHaveCount(2);
        expect($rows->pluck('pivot.traffic_source_campaign_id')->sort()->values()->all())
            ->toBe([$campaigns[0]->id, $campaigns[1]->id]);
        expect(round($rows->sum(fn ($row) => (float) $row->pivot->share), 2))->toBe(1.0);
    });

    it('creates the campaign from a touch when the reference looks like an ad platform id', function () {
        createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $reference = (string) random_int(10000000000, 99999999999);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000b'.$reference,
            ])
        );

        $campaign = TrafficSourceCampaign::where('reference', $reference)->first();

        expect($campaign)->not->toBeNull();
        expect($customer->trafficSources()->first()->pivot->traffic_source_campaign_id)->toBe($campaign->id);
    });

    it('refuses to mint a campaign from a non-numeric reference', function () {
        createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $reference = 'crafted-'.uniqid();

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000b'.$reference,
            ])
        );

        expect(TrafficSourceCampaign::where('reference', $reference)->exists())->toBeFalse();

        $pivot = $customer->trafficSources()->first()->pivot;
        expect($pivot->traffic_source_campaign_id)->toBeNull();
        expect((float) $pivot->share)->toBe(1.0);
    });

    it('does not attach anything when the abbreviation does not match a known traffic source type', function () {
        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => '1700000000z',
            ])
        );

        expect($customer->trafficSources()->count())->toBe(0);
    });

    it('ignores blank traffic source data', function () {
        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => null,
            ])
        );

        expect($customer->trafficSources()->count())->toBe(0);
    });

    it('does not credit a channel with a registration that happened before the touch', function () {
        $newsletter = createTrafficSource($this->shop, 'newsletter', 'Newsletter');
        $customer   = createOwnCustomer($this->shop, 'traffic source attribution');
        $customer->trafficSources()->detach();
        $customer->update(['created_at' => now()->subDays(30)]);

        $customer->trafficSources()->attach($newsletter->id, [
            'share'          => 1,
            'first_touch_at' => now()->subDay(),
            'last_touch_at'  => now()->subDay(),
        ]);

        TrafficSourceHydrateCustomers::run($newsletter);

        expect((float) $newsletter->stats->fresh()->number_customers)->toBe(0.0);
    });

    it('credits a channel with a registration that followed the touch', function () {
        $organic  = createTrafficSource($this->shop, 'organic-google', 'Organic Google');
        $customer = createOwnCustomer($this->shop, 'traffic source attribution');
        $customer->trafficSources()->detach();
        $customer->update(['created_at' => now()->subDay()]);

        $customer->trafficSources()->attach($organic->id, [
            'share'          => 1,
            'first_touch_at' => now()->subDays(2),
            'last_touch_at'  => now()->subDays(2),
        ]);

        TrafficSourceHydrateCustomers::run($organic);

        expect((float) $organic->stats->fresh()->number_customers)->toBe(1.0);
    });

    it('gives the whole credit to the remaining touches when a channel has no row in the shop', function () {
        $shop = $this->shop;

        createTrafficSource($shop, 'organic-google', 'Organic Google');
        App\Models\CRM\TrafficSource::where('shop_id', $shop->id)->where('type', 'organic-bing')->delete();

        $customer = createOwnCustomer($shop, 'traffic source attribution');
        $customer->trafficSources()->detach();
        $customer->update([
            'traffic_sources' => now()->subDays(2)->timestamp.'a|'.now()->subDay()->timestamp.'c',
        ]);

        App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($customer->fresh());

        $credited = $customer->trafficSources()->get();

        expect($credited)->toHaveCount(1)
            ->and($credited->first()->type)->toBe('organic-google')
            ->and((float) $credited->first()->pivot->share)->toBe(1.0);
    });

    it('refreshes a channel\'s rollups when the customer is invoiced, not only when a touch lands', function () {
        $source   = createTrafficSource($this->shop, 'organic-google', 'Organic Google');
        $customer = createOwnCustomer($this->shop, 'traffic source attribution');
        $customer->trafficSources()->detach();
        $customer->trafficSources()->attach($source->id, [
            'share'          => 1,
            'first_touch_at' => now()->subDays(2),
            'last_touch_at'  => now()->subDays(2),
        ]);

        createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 400);

        App\Actions\CRM\TrafficSource\Hydrator\RefreshCustomerTrafficSourceStats::run($customer->fresh());

        expect((float) $source->stats()->first()->total_customer_revenue)->toBe(400.0);
    });
});

describe('recalculating attribution', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('recalculating attribution');

        $this->customer = createOwnCustomer($this->shop, 'recalculating attribution');

        $this->customer->trafficSources()->detach();
        $this->customer->update(['traffic_sources' => null]);

        createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    });

    it('rebuilds attribution for a customer under the requested model', function () {
        $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);

        $trafficSources = $this->customer->trafficSources()->get();
        expect($trafficSources)->toHaveCount(1);
        expect($trafficSources->first()->type)->toBe('organic-google');
        expect((float) $trafficSources->first()->pivot->share)->toBe(1.0);
        expect($trafficSources->first()->pivot->attribution_model)->toBe(ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);
    });

    it('is idempotent: rerunning the same model does not create duplicate pivot rows', function () {
        $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

        expect($this->customer->trafficSources()->count())->toBe(2);
    });

    it('replaces the attribution cleanly when switching models', function () {
        $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);
        expect($this->customer->trafficSources()->count())->toBe(2);

        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LAST_TOUCH);

        $trafficSources = $this->customer->trafficSources()->get();
        expect($trafficSources)->toHaveCount(1);
        expect($trafficSources->first()->type)->toBe('google-ads');
    });

    it('detaches everything and does nothing else when there is no touch history', function () {
        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

        expect($this->customer->trafficSources()->count())->toBe(0);
    });

    it('recalculates customers via the artisan command', function () {
        $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

        Artisan::call('traffic-source:recalculate-attribution', [
            '--shop'  => $this->shop->slug,
            '--model' => ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH,
        ]);

        $trafficSources = $this->customer->trafficSources()->get();
        expect($trafficSources)->toHaveCount(1);
        expect($trafficSources->first()->type)->toBe('organic-google');
    });

    it('does not modify records when the artisan command runs in dry-run mode', function () {
        $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

        Artisan::call('traffic-source:recalculate-attribution', [
            '--shop'    => $this->shop->slug,
            '--model'   => ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH,
            '--dry-run' => true,
        ]);

        expect($this->customer->trafficSources()->count())->toBe(0);
    });
});

describe('attribution window', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        config()->set('marketing.attribution_window_days', 90);

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('attribution window');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->customer  = createOwnCustomer($this->shop, 'attribution window');

        $this->customer->trafficSources()->detach();
        DB::table('invoices')->where('customer_id', $this->customer->id)->delete();

        // One touch, 10 days ago.
        $this->touchedAt = now()->subDays(10);
        $this->customer->update(['traffic_sources' => $this->touchedAt->timestamp.'b']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());
    });

    it('records when each touch happened', function () {
        $pivot = $this->customer->trafficSources()->first()->pivot;

        expect($pivot->first_touch_at)->not->toBeNull();
        expect(substr((string) $pivot->first_touch_at, 0, 10))->toBe($this->touchedAt->toDateString());
    });

    it('ignores revenue invoiced before the touch', function () {
        windowInvoice(now()->subDays(400)->toDateTimeString(), 5000, $this->customer, $this->shop);
        windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

        // Only the invoice raised after the click counts; the 5,000 predates it.
        expect($overview['totals']['revenue'])->toBe(300.0);
    });

    it('ignores revenue invoiced after the window closes', function () {
        // Touch was 10 days ago, so a 5-day window has already expired.
        config()->set('marketing.attribution_window_days', 5);

        windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

        expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['totals']['revenue'])
            ->toBe(0.0);
    });

    it('counts revenue inside the window', function () {
        config()->set('marketing.attribution_window_days', 90);

        windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

        expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['totals']['revenue'])
            ->toBe(300.0);
    });

    it('lets a shop override the group window', function () {
        expect(GetAttributionWindow::run($this->shop))->toBe(90);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], [
            'marketing' => ['attribution_window_days' => 30],
        ])]);

        expect(GetAttributionWindow::run($this->shop->fresh()))->toBe(30);
    });

    it('disables the window when it is set to zero', function () {
        config()->set('marketing.attribution_window_days', 0);

        windowInvoice(now()->subDays(400)->toDateTimeString(), 5000, $this->customer, $this->shop);
        windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

        // Causality still applies: the pre-touch invoice never counts, window or no window.
        expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['totals']['revenue'])
            ->toBe(300.0);
    });

    it('credits revenue by the date the customer ordered, not the date the invoice was raised', function () {
        $customer = $this->customer;
        $customer->trafficSources()->detach();
        DB::table('invoices')->where('customer_id', $customer->id)->delete();

        /* Ordered yesterday, touched this morning, invoiced after that: the touch cannot have caused an
           order that was already placed. */
        $customer->trafficSources()->attach($this->googleAds->id, [
            'share'          => 1,
            'first_touch_at' => now()->subHours(6),
            'last_touch_at'  => now()->subHours(6),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'group_id'        => $this->shop->group_id,
            'organisation_id' => $this->shop->organisation_id,
            'shop_id'         => $this->shop->id,
            'customer_id'     => $customer->id,
            'currency_id'     => $this->shop->currency_id,
            'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
            'slug'            => 'ord-'.uniqid(),
            'state'           => 'dispatched',
            'status'          => 'settled',
            'payment_data'    => '{}',
            'data'            => '{}',
            'date'            => now()->subDay()->toDateTimeString(),
            'created_at'      => now()->subDay()->toDateTimeString(),
            'updated_at'      => now()->subDay()->toDateTimeString(),
        ]);

        DB::table('invoices')->insert([
            'group_id'        => $this->shop->group_id,
            'organisation_id' => $this->shop->organisation_id,
            'shop_id'         => $this->shop->id,
            'customer_id'     => $customer->id,
            'order_id'        => $orderId,
            'currency_id'     => $this->shop->currency_id,
            'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
            'reference'       => 'INV-'.uniqid(),
            'slug'            => 'inv-'.uniqid(),
            'type'            => 'invoice',
            'net_amount'      => 500,
            'org_net_amount'  => 500,
            'grp_net_amount'  => 500,
            'total_amount'    => 500,
            'in_process'      => false,
            'payment_data'    => '{}',
            'data'            => '{}',
            'date'            => now()->toDateTimeString(),
            'created_at'      => now()->toDateTimeString(),
            'updated_at'      => now()->toDateTimeString(),
        ]);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', $this->googleAds->type);

        expect($channel['revenue'] ?? 0.0)->toBe(0.0);
    });

    it('lets the recording start date be set, so a fix to capture is not judged by what came before it', function () {
        config()->set('marketing.attribution_started_at', '2026-08-07 19:30:00');

        expect(App\Actions\CRM\TrafficSource\GetAttributionStartedAt::run()->toDateTimeString())
            ->toBe('2026-08-07 19:30:00');

        config()->set('marketing.attribution_started_at', null);
    });
});

describe('attribution data quality', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('attribution data quality');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->customer  = createOwnCustomer($this->shop, 'attribution data quality');
        $this->customer->trafficSources()->detach();
    });

    it('reports registrations with no attribution as a proportion', function () {
        $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        $unattributed = createOwnCustomer($this->shop, 'attribution data quality');
        $unattributed->trafficSources()->detach();

        $check = dataQualityCheck('registrations_without_attribution', $this->shop);

        $registrations = DB::table('customers')
            ->where('shop_id', $this->shop->id)
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->count();

        expect($check['value'])->toContain('/ '.$registrations);
    });

    it('flags customers whose attribution shares do not sum to one', function () {
        $this->customer->trafficSources()->attach($this->googleAds->id, ['share' => 0.5]);

        $check = dataQualityCheck('shares_not_summing_to_one', $this->shop);

        expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_ERROR)
            ->and($check['items'][0])->toContain('#'.$this->customer->id)
            ->and($check['items'][0])->toContain('0.50');
    });

    it('passes the share invariant check for a correctly attributed customer', function () {
        $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b|'.now()->timestamp.'a']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        $check = dataQualityCheck('shares_not_summing_to_one', $this->shop);

        expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_OK)
            ->and($check['items'])->toBe([]);
    });

    it('flags a channel with no traffic source row in the shop', function () {
        TrafficSource::where('shop_id', $this->shop->id)->where('type', 'newsletter')->delete();

        $check = dataQualityCheck('missing_traffic_sources', $this->shop);

        expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_ERROR)
            ->and($check['items'])->toContain('Newsletter');
    });

    it('lists channels that have never been credited a customer', function () {
        $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        $check = dataQualityCheck('never_credited_traffic_sources', $this->shop);

        expect($check['items'])->not->toContain('Google Ads')
            ->and($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_WARNING);
    });

    it('flags campaign references that matched no campaign', function () {
        $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b'.'not-a-campaign']);

        $check = dataQualityCheck('unmatched_campaign_references', $this->shop);

        expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_WARNING)
            ->and($check['items'][0])->toContain('not-a-campaign');
    });

    it('does not flag a campaign reference that resolves to a campaign', function () {
        $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b'.'987654']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        $check = dataQualityCheck('unmatched_campaign_references', $this->shop);

        expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_OK)
            ->and($check['items'])->toBe([]);
    });

    it('surfaces the capture counters so nobody has to run a command to see them', function () {
        $day = now()->toDateString();

        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:matched', 8, now()->addDay());
        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:direct', 2, now()->addDay());
        /* Browsing must not dilute the percentage: one identified arrival is followed by a dozen
           own-site page views, and counting those as hits is what made 7% read as capture failing. */
        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:internal', 90, now()->addDay());
        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':hosts', ['app.aiku.io' => 26], now()->addDay());

        $capture = GetShopAttributionDataQuality::run($this->shop)['capture'];

        expect($capture['arrivals'])->toBe(10)
            ->and($capture['identified_pct'])->toBe(80.0)
            ->and($capture['rows'][0]['internal'])->toBe(90)
            ->and($capture['rejected'][0])->toBe(['host' => 'app.aiku.io', 'hits' => 26]);
    });

    it('reports identified as a share of arrivals in the capture stats command too', function () {
        $day = now()->toDateString();

        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:matched', 8, now()->addDay());
        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:direct', 2, now()->addDay());
        Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:internal', 90, now()->addDay());

        Illuminate\Support\Facades\Artisan::call('traffic-source:capture-stats', ['--days' => 1]);
        $output = Illuminate\Support\Facades\Artisan::output();

        expect($output)->toContain('Arrivals')
            ->and($output)->toContain('80%')
            ->and($output)->not->toContain('7.2%');
    });
});

describe('customer journey', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('customer journey');

        createTrafficSource($this->shop, 'organic-google', 'Organic Google');
        createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    });

    it('interleaves touches and invoices in chronological order', function () {
        $touchA = now()->subDays(20)->timestamp;
        $touchB = now()->subDays(5)->timestamp;

        $customer = journeyCustomer($this->shop, $touchA.'a|'.$touchB.'b');
        journeyInvoice($customer, now()->subDays(10)->toDateTimeString(), 100);

        $journey = GetCustomerJourney::run($customer->refresh());

        expect(array_column($journey['events'], 'type'))->toBe(['touch', 'invoice', 'touch']);
        expect($journey['events'][1]['net_amount'])->toBe(100.0);
        expect($journey['events'][0]['label'])->toBe('Organic Google');
    });

    it('returns an empty journey for a customer with no touches and no invoices', function () {
        $customer = journeyCustomer($this->shop, null);

        $journey = GetCustomerJourney::run($customer->refresh());

        expect($journey['events'])->toBe([])
            ->and($journey['attribution'])->toBe([])
            ->and($journey['attribution_window_days'])->toBe(90);
    });

    it('flags a touch that falls outside the attribution window of the following purchase', function () {
        $staleTouch  = now()->subDays(200)->timestamp;
        $recentTouch = now()->subDays(3)->timestamp;

        $customer = journeyCustomer($this->shop, $staleTouch.'a|'.$recentTouch.'b');
        journeyInvoice($customer, now()->toDateTimeString(), 50);

        $journey = GetCustomerJourney::run($customer->refresh());

        $touches = array_values(array_filter($journey['events'], fn ($event) => $event['type'] === 'touch'));

        expect($touches[0]['in_window'])->toBeFalse()
            ->and($touches[1]['in_window'])->toBeTrue();
    });

    it('honours the per shop attribution window override', function () {
        $touch = now()->subDays(30)->timestamp;

        $this->shop->settings = array_merge($this->shop->settings ?? [], [
            'marketing' => ['attribution_window_days' => 7],
        ]);
        $this->shop->save();

        $customer = journeyCustomer($this->shop, $touch.'a');

        $journey = GetCustomerJourney::run($customer->refresh());

        expect($journey['attribution_window_days'])->toBe(7)
            ->and($journey['events'][0]['in_window'])->toBeFalse();
    });
});

describe('referral traffic sources', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('referral traffic sources');

        $this->referral = createTrafficSource($this->shop, 'referral', 'Referral');
    });

    it('records an unrecognised external referrer as a referral touch carrying its host', function () {
        $touch = GetTrafficSourceFromRefererHeader::run('https://www.esources.co.uk/directory/x');

        expect($touch)->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::REFERRAL->value].'esources.co.uk');
    });

    it('still prefers a known channel over the referral fallback', function () {
        expect(GetTrafficSourceFromRefererHeader::run('https://www.google.com/search?q=x'))
            ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value]);
    });

    it('does not treat our own systems as a referral', function () {
        expect(GetTrafficSourceFromRefererHeader::run('https://app.aiku.io/org/aw'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::normaliseHost('not a host'))->toBeNull();
    });

    it('creates a campaign per referring host and shows it in the dashboard top referrers', function () {
        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDay()->timestamp.'qesources.co.uk',
            ])
        );

        createInvoiceFor($customer, $this->shop, now()->toDateTimeString(), 250);

        $campaign = TrafficSourceCampaign::where('traffic_source_id', $this->referral->id)
            ->where('reference', 'esources.co.uk')
            ->first();

        expect($campaign)->not->toBeNull();

        $referrers = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['referrers'])
            ->keyBy('host');

        expect($referrers['esources.co.uk']['registrations'])->toBe(1.0)
            ->and($referrers['esources.co.uk']['revenue'])->toBe(250.0);
    });

    it('refuses a referral campaign whose reference is not a hostname', function () {
        StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDay()->timestamp.'qnot-a-host',
            ])
        );

        /* Asserted by reference, not by count: createShop() reuses the shop across the file, so the
           referral source still carries the campaign the earlier test created. */
        expect(TrafficSourceCampaign::where('traffic_source_id', $this->referral->id)
            ->where('reference', 'not-a-host')->exists())->toBeFalse();
    });

    /* Primed rather than seeded from the websites table: the test database carries no live domains, and
       what is under test is the exclusion, not the lookup. */
    it('does not record one of our own storefronts as a referral', function () {
        pretendWeOwn('awgifts.eu');

        expect(GetTrafficSourceFromRefererHeader::run('https://awgifts.eu/products'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://www.awgifts.eu/products'))->toBeNull();
    });

    it('drops a referral touch for our own storefront instead of crediting it', function () {
        $domain   = 'awgifts.eu';
        $customer = createOwnCustomer($this->shop, 'referral traffic sources');
        $customer->trafficSources()->detach();

        pretendWeOwn($domain);

        $customer->update([
            'traffic_sources' => now()->subDay()->timestamp.'q'.$domain.'|'.now()->timestamp.'a',
        ]);
        RecalculateTrafficSourceAttribution::run($customer->fresh());

        $credited = $customer->trafficSources()->get();

        expect($credited)->toHaveCount(1)
            ->and($credited->first()->type)->toBe('organic-google')
            ->and((float) $credited->first()->pivot->share)->toBe(1.0);
    });

    it('records the search engines we have no channel of their own for as organic search', function () {
        $searchAbbr = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_SEARCH->value];

        expect(GetTrafficSourceFromRefererHeader::run('https://duckduckgo.com/?q=incense'))->toBe($searchAbbr.'duckduckgo.com')
            ->and(GetTrafficSourceFromRefererHeader::run('https://uk.search.yahoo.com/search?p=x'))->toBe($searchAbbr.'uk.search.yahoo.com')
            ->and(GetTrafficSourceFromRefererHeader::run('https://search.brave.com/search?q=x'))->toBe($searchAbbr.'search.brave.com');
    });

    it('separates a search engine from the webmail on the same domain', function () {
        $searchAbbr = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_SEARCH->value];

        expect(GetTrafficSourceFromRefererHeader::run('https://search.seznam.cz/?q=x'))->toBe($searchAbbr.'search.seznam.cz')
            ->and(GetTrafficSourceFromRefererHeader::run('https://email.seznam.cz/message/1'))->toBeNull();
    });

    it('counts a click arriving through the google ads redirect as paid google', function () {
        expect(GetTrafficSourceFromRefererHeader::run('https://www.googleadservices.com/pagead/aclk?x=1'))
            ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::GOOGLE_ADS->value]);
    });

    it('keeps a search engine that has its own channel out of the generic bucket', function () {
        expect(GetTrafficSourceFromRefererHeader::run('https://www.google.co.uk/search?q=x'))
            ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value])
            ->and(GetTrafficSourceFromRefererHeader::run('https://www.bing.com/search?q=x'))
            ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_BING->value]);
    });

    it('records no touch for a click arriving from webmail', function () {
        /* Our own mailshot click is already recorded server-side; counting the webmail host as well
           would split that click's credit away from the newsletter. */
        expect(GetTrafficSourceFromRefererHeader::run('https://mail02.orange.fr/inbox/1'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://messageriepro.orange.fr/x'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://email.seznam.cz/message/1'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://poczta.wp.pl/w/message/1'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://outlook.live.com/mail/0/'))->toBeNull();
    });

    it('still records an email marketing platform that is not a mailbox', function () {
        $referralAbbr = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::REFERRAL->value];

        expect(GetTrafficSourceFromRefererHeader::run('https://mailchimp.com/blog/x'))->toBe($referralAbbr.'mailchimp.com');
    });

    it('does not record our own mailshot editor as a referral', function () {
        expect(GetTrafficSourceFromRefererHeader::run('https://app.getbee.io/editor'))->toBeNull();
    });

    it('records no touch for a webmail provider whose subdomain gives nothing away', function () {
        /* abv.bg serves its webmail from nm20.abv.bg, which no prefix rule would catch. */
        expect(GetTrafficSourceFromRefererHeader::run('https://nm20.abv.bg/mail/1'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://abv.bg/'))->toBeNull()
            ->and(GetTrafficSourceFromRefererHeader::run('https://mail.ru/inbox'))->toBeNull();
    });

    it('keeps a search engine matched before the webmail rules can reject it', function () {
        $searchAbbr = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_SEARCH->value];

        expect(GetTrafficSourceFromRefererHeader::run('https://search.seznam.cz/?q=x'))->toBe($searchAbbr.'search.seznam.cz');
    });
});

describe('showing a traffic source', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('showing a traffic source');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->organic   = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        $this->customer = createOwnCustomer($this->shop, 'showing a traffic source');
        $this->customer->trafficSources()->detach();
    });

    it('lists the customers attributed to a traffic source', function () {
        $this->customer->update(['traffic_sources' => '1700000000b']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        fakeRouteForCustomers();

        $customers = IndexCustomers::make()->handle($this->googleAds, TrafficSourceTabsEnum::CUSTOMERS->value);

        expect($customers->pluck('id'))->toContain($this->customer->id);
    });

    it('shows how much of a shared customer the source actually owns', function () {
        $this->customer->update(['traffic_sources' => '1700000000b|1700000100a']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        fakeRouteForCustomers();

        $row = IndexCustomers::make()->handle($this->googleAds, TrafficSourceTabsEnum::CUSTOMERS->value)
            ->firstWhere('id', $this->customer->id);

        expect((float) $row->attribution_share)->toBe(0.5);
    });

    it('excludes customers attributed to a different source', function () {
        $this->customer->update(['traffic_sources' => '1700000000b']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        fakeRouteForCustomers();

        $customers = IndexCustomers::make()->handle($this->organic, TrafficSourceTabsEnum::CUSTOMERS->value);

        expect($customers->pluck('id'))->not->toContain($this->customer->id);
    });
});

describe('campaign stats', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('campaign stats');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
    });

    it('rolls a campaign\'s attributed customers and revenue into its own stats', function () {
        $reference = (string) random_int(10000000000, 99999999999);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDays(10)->timestamp.'b'.$reference,
            ])
        );

        createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 900);

        foreach (range(1, 3) as $ignored) {
            createDispatchedOrderFor($customer, $this->shop, now()->subDay()->toDateTimeString());
        }

        $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();
        TrafficSourceCampaignHydrateStats::run($campaign);

        $stats = $campaign->stats()->first();

        expect((float) $stats->number_customers)->toBe(1.0);
        expect((float) $stats->total_customer_revenue)->toBe(900.0);
        expect((float) $stats->number_customer_purchases)->toBe(3.0);
    });

    it('rolls campaign level spend into the campaign stats', function () {
        $campaign = campaignFor($this->googleAds, (string) random_int(10000000000, 99999999999));

        StoreTrafficSourceCost::run($this->googleAds, [
            'date'                       => now()->subDay()->toDateString(),
            'source_amount'              => 120.00,
            'source_currency_id'         => $this->shop->currency_id,
            'traffic_source_campaign_id' => $campaign->id,
        ]);

        TrafficSourceCampaignHydrateStats::run($campaign);

        expect((float) $campaign->stats()->first()->total_cost)->toBe(120.0);
    });

    it('keeps a campaign with spend but no customers visible', function () {
        $campaign = campaignFor($this->googleAds, (string) random_int(10000000000, 99999999999));

        StoreTrafficSourceCost::run($this->googleAds, [
            'date'                       => now()->subDay()->toDateString(),
            'source_amount'              => 75.00,
            'source_currency_id'         => $this->shop->currency_id,
            'traffic_source_campaign_id' => $campaign->id,
        ]);

        TrafficSourceCampaignHydrateStats::run($campaign);
        $stats = $campaign->stats()->first();

        expect((float) $stats->total_cost)->toBe(75.0);
        expect((float) $stats->number_customers)->toBe(0.0);
        expect((float) $stats->total_customer_revenue)->toBe(0.0);
    });

    it('never lets a source\'s campaigns claim more than the source itself', function () {
        $first  = (string) random_int(10000000000, 99999999999);
        $second = (string) random_int(10000000000, 99999999999);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDays(10)->timestamp.'b'.$first.'|'.now()->subDays(9)->timestamp.'b'.$second,
            ])
        );

        createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 1000);

        TrafficSourceHydrateCustomers::run($this->googleAds);

        $campaignRevenue = 0.0;
        foreach ([$first, $second] as $reference) {
            $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();
            TrafficSourceCampaignHydrateStats::run($campaign);
            $campaignRevenue += (float) $campaign->stats()->first()->total_customer_revenue;
        }

        // The two campaigns split this customer's revenue between them, never duplicating it...
        expect($campaignRevenue)->toBe(1000.0);

        // ...and campaigns can never claim more than the source they belong to. (Other tests in this file
        // share the shop, so the source legitimately carries their customers too - hence not equality.)
        expect($campaignRevenue)
            ->toBeLessThanOrEqual(round((float) $this->googleAds->stats()->first()->total_customer_revenue, 2));
    });

    it('hydrates every campaign through the artisan command', function () {
        $campaign = campaignFor($this->googleAds, (string) random_int(10000000000, 99999999999));

        StoreTrafficSourceCost::run($this->googleAds, [
            'date'                       => now()->subDay()->toDateString(),
            'source_amount'              => 33.00,
            'source_currency_id'         => $this->shop->currency_id,
            'traffic_source_campaign_id' => $campaign->id,
        ]);

        Artisan::call('traffic-source:hydrate-campaign-stats', ['--shop' => $this->shop->slug]);

        expect((float) $campaign->stats()->first()->total_cost)->toBe(33.0);
    });

    it('reports campaign performance for the selected period on the dashboard', function () {
        $reference = (string) random_int(10000000000, 99999999999);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDays(10)->timestamp.'b'.$reference,
            ])
        );

        $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();

        createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 400);

        StoreTrafficSourceCost::run($this->googleAds, [
            'date'                       => now()->subDay()->toDateString(),
            'source_amount'              => 100.00,
            'source_currency_id'         => $this->shop->currency_id,
            'traffic_source_campaign_id' => $campaign->id,
        ]);

        $campaigns = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['campaigns'])
            ->keyBy('name');

        expect($campaigns[$campaign->name]['spend'])->toBe(100.0);
        expect($campaigns[$campaign->name]['revenue'])->toBe(400.0);
        expect($campaigns[$campaign->name]['roas'])->toBe(4.0);
    });

    it('counts a campaign\'s registrations as customers rather than as invoices', function () {
        $reference = (string) random_int(10000000000, 99999999999);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDays(10)->timestamp.'b'.$reference,
            ])
        );

        $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();

        createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 400);
        createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 200);

        $campaigns = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['campaigns'])
            ->keyBy('name');

        expect($campaigns[$campaign->name]['registrations'])->toBe(1.0)
            ->and($campaigns[$campaign->name]['revenue'])->toBe(600.0);
    });

    it('does not credit a campaign with a registration that preceded its touch', function () {
        $reference = (string) random_int(10000000000, 99999999999);

        $customer = StoreCustomer::make()->action(
            $this->shop,
            array_merge(Customer::factory()->definition(), [
                'traffic_sources' => now()->subDay()->timestamp.'b'.$reference,
            ])
        );

        $customer->update(['created_at' => now()->subDays(5)]);
        $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();

        createInvoiceFor($customer, $this->shop, now()->toDateTimeString(), 400);

        $campaigns = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['campaigns'])
            ->keyBy('name');

        expect($campaigns[$campaign->name]['registrations'])->toBe(0.0);
    });
});

describe('campaign channel type', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('campaign channel type');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $this->token = $this->shop->createToken('ads-script', [ReceiveTrafficSourceCostWebhook::ABILITY])->plainTextToken;

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
    });

    it('labels a campaign with the channel type the ad platform reports', function () {
        $reference = 'ref-'.uniqid();

        postChannelTypeCosts([[
            'date'          => '2026-08-07',
            'campaign'      => $reference,
            'campaign_name' => 'Brand Video Push',
            'channel_type'  => 'VIDEO',
            'amount'        => 42.50,
        ]], $this->token)->assertOk();

        expect(TrafficSourceCampaign::where('reference', $reference)->first()->channel_type)->toBe('VIDEO');
    });

    it('fills in the channel type of a campaign that was created by a click', function () {
        $campaign = TrafficSourceCampaign::create([
            'traffic_source_id' => $this->googleAds->id,
            'reference'         => 'ref-'.uniqid(),
            'name'              => 'Known from a gclid only',
            'type'              => 'google-ads',
        ]);

        expect($campaign->channel_type)->toBeNull();

        postChannelTypeCosts([[
            'date'         => '2026-08-07',
            'campaign'     => $campaign->reference,
            'channel_type' => 'PERFORMANCE_MAX',
            'amount'       => 10.00,
        ]], $this->token)->assertOk();

        expect($campaign->refresh()->channel_type)->toBe('PERFORMANCE_MAX');
    });

    it('keeps the spend on the same channel the touch history knows, whatever the label says', function () {
        $reference = 'ref-'.uniqid();

        postChannelTypeCosts([[
            'date'         => '2026-08-07',
            'campaign'     => $reference,
            'channel_type' => 'VIDEO',
            'amount'       => 99.00,
        ]], $this->token)->assertOk();

        $campaign = TrafficSourceCampaign::where('reference', $reference)->first();

        expect($campaign->traffic_source_id)->toBe($this->googleAds->id)
            ->and($campaign->type)->toBe('google-ads')
            ->and((float) TrafficSourceCost::where('traffic_source_campaign_id', $campaign->id)->first()->source_amount)->toBe(99.00);
    });

    it('still accepts a script that has not been updated to send a channel type', function () {
        $reference = 'ref-'.uniqid();

        postChannelTypeCosts([[
            'date'     => '2026-08-07',
            'campaign' => $reference,
            'amount'   => 5.00,
        ]], $this->token)->assertOk();

        expect(TrafficSourceCampaign::where('reference', $reference)->first()->channel_type)->toBeNull();
    });
});

describe('traffic source costs', function () {
    /**
     * IndexTrafficSources names its paginator after the current route, which does not exist when the
     * action is called directly rather than through a request.
     */
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('traffic source costs');

        $this->trafficSource = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->currency      = $this->shop->currency;

        // The shop and traffic source are shared across the tests in this file, so clear anything a
        // previous test left on them.
        TrafficSourceCost::where('traffic_source_id', $this->trafficSource->id)->delete();
        $this->trafficSource->stats()->updateOrCreate(
            ['traffic_source_id' => $this->trafficSource->id],
            [
                'total_cost'                 => 0,
                'org_total_cost'             => 0,
                'total_customer_revenue'     => 0,
                'org_total_customer_revenue' => 0,
                'number_customers'           => 0,
            ]
        );
    });

    it('records a cost and rolls it into the traffic source stats', function () {
        StoreTrafficSourceCost::run($this->trafficSource, [
            'date'               => '2026-08-01',
            'source_amount'      => 153.22,
            'source_currency_id' => $this->currency->id,
        ]);

        TrafficSourceHydrateCosts::run($this->trafficSource);

        expect((float) $this->trafficSource->stats()->first()->total_cost)->toBe(153.22);
    });

    it('keeps the originally billed amount alongside the converted one', function () {
        $cost = StoreTrafficSourceCost::run($this->trafficSource, [
            'date'               => '2026-08-01',
            'source_amount'      => 99.5,
            'source_currency_id' => $this->currency->id,
        ]);

        expect((float) $cost->source_amount)->toBe(99.5);
        expect($cost->source_currency_id)->toBe($this->currency->id);
        expect((float) $cost->amount)->toBe(99.5);
    });

    it('updates rather than duplicates when the same day is imported again', function () {
        foreach ([100.00, 137.65] as $amount) {
            StoreTrafficSourceCost::run($this->trafficSource, [
                'date'               => '2026-08-01',
                'source_amount'      => $amount,
                'source_currency_id' => $this->currency->id,
            ]);
        }

        $costs = TrafficSourceCost::where('traffic_source_id', $this->trafficSource->id)->get();

        expect($costs)->toHaveCount(1);
        expect((float) $costs->first()->source_amount)->toBe(137.65);
    });

    it('keeps campaign level and source level spend as separate rows for the same day', function () {
        $campaign = TrafficSourceCampaign::create([
            'traffic_source_id' => $this->trafficSource->id,
            'reference'         => 'camp-'.uniqid(),
            'name'              => 'August Push',
            'type'              => 'search',
        ]);

        StoreTrafficSourceCost::run($this->trafficSource, [
            'date'               => '2026-08-01',
            'source_amount'      => 40.00,
            'source_currency_id' => $this->currency->id,
        ]);

        StoreTrafficSourceCost::run($this->trafficSource, [
            'date'                       => '2026-08-01',
            'source_amount'              => 60.00,
            'source_currency_id'         => $this->currency->id,
            'traffic_source_campaign_id' => $campaign->id,
        ]);

        TrafficSourceHydrateCosts::run($this->trafficSource);

        expect(TrafficSourceCost::where('traffic_source_id', $this->trafficSource->id)->count())->toBe(2);
        expect((float) $this->trafficSource->stats()->first()->total_cost)->toBe(100.0);
    });

    it('sums spend across days', function () {
        foreach (['2026-08-01' => 10.00, '2026-08-02' => 15.50] as $date => $amount) {
            StoreTrafficSourceCost::run($this->trafficSource, [
                'date'               => $date,
                'source_amount'      => $amount,
                'source_currency_id' => $this->currency->id,
            ]);
        }

        TrafficSourceHydrateCosts::run($this->trafficSource);

        expect((float) $this->trafficSource->stats()->first()->total_cost)->toBe(25.5);
    });

    it('exposes cost, roas and cac on the shop traffic sources listing', function () {
        StoreTrafficSourceCost::run($this->trafficSource, [
            'date'               => '2026-08-01',
            'source_amount'      => 50.00,
            'source_currency_id' => $this->currency->id,
        ]);

        TrafficSourceHydrateCosts::run($this->trafficSource);

        $this->trafficSource->stats()->update([
            'total_customer_revenue' => 200.00,
            'number_customers'       => 4,
        ]);

        fakeCurrentRoute();

        $row = IndexTrafficSources::make()->handle($this->shop)
            ->firstWhere('id', $this->trafficSource->id);

        expect((float) $row->cost)->toBe(50.0);
        expect((float) $row->roas)->toBe(4.0);
        expect((float) $row->cac)->toBe(12.5);
    });

    it('aggregates period spend per channel into the marketing overview', function () {
        $organic = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        foreach ([2, 1] as $daysAgo) {
            StoreTrafficSourceCost::run($this->trafficSource, [
                'date'               => now()->subDays($daysAgo)->toDateString(),
                'source_amount'      => 100.00,
                'source_currency_id' => $this->currency->id,
            ]);
        }
        TrafficSourceHydrateCosts::run($this->trafficSource);

        // A channel with attributed customers but no spend still has to appear, with no ROAS to show.
        $customer = createOwnCustomer($this->shop, 'traffic source costs');
        $customer->trafficSources()->detach();
        $customer->update(['traffic_sources' => now()->subDays(2)->timestamp.'a']);
        App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($customer->fresh());

        $overview = GetShopMarketingOverview::run($this->shop, App\Enums\UI\Marketing\MarketingPeriodEnum::ALL_TIME);
        $channels = collect($overview['channels'])->keyBy('type');

        expect($overview['totals']['spend'])->toBe(200.0);
        expect($overview['currency_code'])->toBe($this->currency->code);
        expect((float) $channels['google-ads']['spend'])->toBe(200.0);
        expect($channels['organic-google']['roas'])->toBeNull();
        expect($channels['organic-google']['registrations'])->toBe(1.0);
    });

    it('reports a null roas and cac in the overview when nothing was spent', function () {
        $overview = GetShopMarketingOverview::run($this->shop);

        expect($overview['totals']['spend'])->toBe(0.0);
        expect($overview['totals']['roas'])->toBeNull();
        expect($overview['totals']['cac'])->toBeNull();
        expect($overview['spend_by_day'])->toBe([]);
    });

    it('reports no roas or cac for a source with no recorded spend', function () {
        $this->trafficSource->stats()->update([
            'total_customer_revenue' => 200.00,
            'number_customers'       => 4,
        ]);

        fakeCurrentRoute();

        $row = IndexTrafficSources::make()->handle($this->shop)
            ->firstWhere('id', $this->trafficSource->id);

        expect($row->roas)->toBeNull();
        expect($row->cac)->toBeNull();
    });

    it('renders whole share weighted counts without decimals and keeps genuine fractions', function () {
        $this->trafficSource->stats()->update([
            'number_customers'          => 333.00,
            'number_customer_purchases' => 0.50,
        ]);

        fakeCurrentRoute();

        $row = IndexTrafficSources::make()->handle($this->shop)
            ->firstWhere('id', $this->trafficSource->id);

        expect($row->number_customers)->toBe('333')
            ->and($row->number_customer_purchases)->toBe('0.5');
    });
});

describe('importing costs from a csv', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('importing costs from a csv');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->metaAds   = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
    });

    it('imports spend from a csv', function () {
        $currency = $this->shop->currency->code;

        $exit = importCosts([
            "{$this->shop->slug},google-ads,,2026-08-01,153.22,{$currency}",
            "{$this->shop->slug},meta-ads,,2026-08-01,88.40,{$currency}",
        ]);

        expect($exit)->toBe(0);

        $costs = TrafficSourceCost::where('shop_id', $this->shop->id)->get();
        expect($costs)->toHaveCount(2);
        expect((float) $costs->firstWhere('traffic_source_id', $this->googleAds->id)->source_amount)->toBe(153.22);
    });

    it('matches a campaign by the reference the touch history already stores', function () {
        $campaign = TrafficSourceCampaign::create([
            'traffic_source_id' => $this->googleAds->id,
            'reference'         => 'ref-'.uniqid(),
            'name'              => 'August Push',
            'type'              => 'search',
        ]);

        importCosts([
            "{$this->shop->slug},google-ads,{$campaign->reference},2026-08-01,25.00,{$this->shop->currency->code}",
        ]);

        $cost = TrafficSourceCost::where('shop_id', $this->shop->id)->first();
        expect($cost->traffic_source_campaign_id)->toBe($campaign->id);
    });

    it('rejects the row and reports failure when the traffic source is unknown', function () {
        $exit = importCosts([
            "{$this->shop->slug},not-a-real-source,,2026-08-01,10.00,{$this->shop->currency->code}",
        ]);

        expect($exit)->toBe(1);
        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
    });

    it('rejects a bad row without discarding the good ones around it', function () {
        $currency = $this->shop->currency->code;

        $exit = importCosts([
            "{$this->shop->slug},google-ads,,2026-08-01,10.00,{$currency}",
            "{$this->shop->slug},nonsense,,2026-08-01,10.00,{$currency}",
            "{$this->shop->slug},meta-ads,,2026-08-01,20.00,{$currency}",
        ]);

        expect($exit)->toBe(1);
        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(2);
    });

    it('writes nothing in dry run mode', function () {
        $exit = importCosts(
            ["{$this->shop->slug},google-ads,,2026-08-01,10.00,{$this->shop->currency->code}"],
            ['--dry-run' => true]
        );

        expect($exit)->toBe(0);
        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
    });

    it('is safe to import the same report twice', function () {
        $row = "{$this->shop->slug},google-ads,,2026-08-01,153.22,{$this->shop->currency->code}";

        importCosts([$row]);
        importCosts([$row]);

        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(1);
    });

    it('fails clearly when the header is missing a column', function () {
        $path = tempnam(sys_get_temp_dir(), 'costs').'.csv';
        file_put_contents($path, "shop,source,date,amount\nuk,google-ads,2026-08-01,10\n");

        expect(Artisan::call('traffic-source:import-costs', ['file' => $path]))->toBe(1);
    });
});

describe('the cost webhook', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('the cost webhook');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();

        $this->token = $this->shop->createToken('Test Agency', [ReceiveTrafficSourceCostWebhook::ABILITY])->plainTextToken;
    });

    it('stores spend posted with a valid token', function () {
        $response = postCosts($this->token, costPayload());

        $response->assertOk()->assertJsonPath('stored', 1);

        $cost = TrafficSourceCost::where('shop_id', $this->shop->id)->first();
        expect((float) $cost->source_amount)->toBe(153.22)
            ->and($cost->traffic_source_id)->toBe($this->googleAds->id);

        $campaign = TrafficSourceCampaign::find($cost->traffic_source_campaign_id);
        expect($campaign->reference)->toBe('21723300927')
            ->and($campaign->name)->toBe('Brand');
    });

    it('rejects a request with no token or a made up one', function () {
        postCosts('', costPayload())->assertForbidden();
        postCosts('999|nonsense', costPayload())->assertForbidden();

        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
    });

    it('rejects a token belonging to another shop', function () {
        $otherShop  = StoreShop::run($this->organisation, Shop::factory()->definition());
        $otherToken = $otherShop->createToken('Other Agency', [ReceiveTrafficSourceCostWebhook::ABILITY])->plainTextToken;

        postCosts($otherToken, costPayload(['shop' => $this->shop->slug]))->assertForbidden();

        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
    });

    it('rejects a shop token minted for something other than costs', function () {
        $wrongAbility = $this->shop->createToken('Reporting', ['reports'])->plainTextToken;

        postCosts($wrongAbility, costPayload())->assertForbidden();
    });

    it('does not double count when the same day is posted twice', function () {
        postCosts($this->token, costPayload())->assertOk();
        postCosts($this->token, costPayload())->assertOk();

        $costs = TrafficSourceCost::where('shop_id', $this->shop->id)->get();
        expect($costs)->toHaveCount(1)
            ->and((float) $costs->first()->source_amount)->toBe(153.22);
    });

    it('updates the day when a corrected figure is posted', function () {
        postCosts($this->token, costPayload())->assertOk();
        postCosts($this->token, costPayload([
            'costs' => [['date' => '2026-08-06', 'campaign' => '21723300927', 'amount' => 160.00]],
        ]))->assertOk();

        $costs = TrafficSourceCost::where('shop_id', $this->shop->id)->get();
        expect($costs)->toHaveCount(1)
            ->and((float) $costs->first()->source_amount)->toBe(160.00);
    });

    it('rejects a malformed payload without writing anything', function () {
        postCosts($this->token, ['source' => 'google-ads'])->assertStatus(422);
        postCosts($this->token, costPayload(['source' => 'not-a-source']))->assertStatus(422);
        postCosts($this->token, costPayload([
            'costs' => [['date' => '06/08/2026', 'amount' => 'lots']],
        ]))->assertStatus(422);

        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
    });

    it('rejects an unknown currency', function () {
        postCosts($this->token, costPayload(['currency' => 'ZZZ']))->assertStatus(422);

        expect(TrafficSourceCost::where('shop_id', $this->shop->id)->count())->toBe(0);
    });
});

describe('fetching meta ads costs', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('fetching meta ads costs');

        $this->metaAds      = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');
        $this->instagramAds = createTrafficSource($this->shop, 'instagram-ads', 'Instagram Ads');

        $settings = $this->shop->settings ?? [];
        data_set($settings, 'meta_ads.ad_account_id', '1234567890');
        $this->shop->update(['settings' => $settings]);

        config()->set('services.meta_ads.access_token', 'system-user-token');

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
    });

    it('stores a day of campaign spend from the insights api', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000001', 88.40, $this->shop->currency->code),
                metaRow('120000002', 12.10, $this->shop->currency->code),
            ])),
        ]);

        $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        expect($exit)->toBe(0);

        $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
        expect($costs)->toHaveCount(2);
        expect((float) $costs->sum('source_amount'))->toBe(100.50);
    });

    it('credits spend to the campaign the touch history already knows', function () {
        $campaign = TrafficSourceCampaign::create([
            'traffic_source_id' => $this->metaAds->id,
            'reference'         => '120000003',
            'name'              => 'August Push',
            'type'              => 'meta-ads',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000003', 25.00, $this->shop->currency->code),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        $cost = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->first();
        expect($cost->traffic_source_campaign_id)->toBe($campaign->id);
    });

    it('replaces rather than adds when the same day is fetched twice', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(metaInsights([metaRow('120000004', 50.00, $this->shop->currency->code)]))
                ->push(metaInsights([metaRow('120000004', 57.25, $this->shop->currency->code)])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);
        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
        expect($costs)->toHaveCount(1);
        expect((float) $costs->first()->source_amount)->toBe(57.25);
    });

    it('follows pagination so a long day is not silently cut short', function () {
        /* Order matters: the stubs are filtered and the FIRST match answers, so the page-two pattern
           has to come before the catch-all. Behind it, the catch-all answers page two as well, the
           command follows a next link back to the page it just read, and the repeated-page guard
           ends the run one page short. */
        Http::fake([
            'graph.facebook.com/*page2*' => Http::response(metaInsights([
                metaRow('120000006', 10.00, $this->shop->currency->code),
            ])),
            'graph.facebook.com/*' => Http::response(metaInsights(
                [metaRow('120000005', 10.00, $this->shop->currency->code)],
                'https://graph.facebook.com/v21.0/act_1234567890/insights?page2=1'
            )),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(2);
    });

    it('skips campaign days that cost nothing', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000007', 0, $this->shop->currency->code),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
        expect(TrafficSourceCampaign::where('reference', '120000007')->first())->toBeNull();
    });

    it('fails loudly when meta rejects the token', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401),
        ]);

        $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        expect($exit)->toBe(1);
        expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
    });

    it('reports instagram spend as its own channel', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000009', 40.00, $this->shop->currency->code, '2026-08-07', 'facebook'),
                metaRow('120000009', 15.00, $this->shop->currency->code, '2026-08-07', 'instagram'),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        $meta      = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
        $instagram = TrafficSourceCost::where('traffic_source_id', $this->instagramAds->id)->get();

        expect((float) $meta->sum('source_amount'))->toBe(40.00);
        expect((float) $instagram->sum('source_amount'))->toBe(15.00);

        /* Both halves of the campaign keep a campaign row, which they only can if their references differ. */
        expect(TrafficSourceCampaign::where('reference', '120000009')->value('traffic_source_id'))->toBe($this->metaAds->id);
        expect(TrafficSourceCampaign::where('reference', 'ig-120000009')->value('traffic_source_id'))->toBe($this->instagramAds->id);
    });

    it('sums the non instagram platforms into one meta ads row', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000010', 10.00, $this->shop->currency->code, '2026-08-07', 'facebook'),
                metaRow('120000010', 4.00, $this->shop->currency->code, '2026-08-07', 'audience_network'),
                metaRow('120000010', 1.50, $this->shop->currency->code, '2026-08-07', 'messenger'),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();

        expect($costs)->toHaveCount(1);
        expect((float) $costs->first()->source_amount)->toBe(15.50);
    });

    it('fails loudly when the instagram source has not been seeded', function () {
        $this->instagramAds->delete();

        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000011', 10.00, $this->shop->currency->code),
            ])),
        ]);

        $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        expect($exit)->toBe(1);
        expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
    });

    it('writes nothing on a dry run', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(metaInsights([
                metaRow('120000008', 33.00, $this->shop->currency->code),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug, '--dry-run' => true]);

        expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
    });
});

describe('splitting instagram from meta', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('splitting instagram from meta');

        $this->metaAds      = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');
        $this->instagramAds = createTrafficSource($this->shop, 'instagram-ads', 'Instagram Ads');

        $settings = $this->shop->settings ?? [];
        data_set($settings, 'meta_ads.ad_account_id', '1234567890');
        $this->shop->update(['settings' => $settings]);

        config()->set('services.meta_ads.access_token', 'system-user-token');

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
    });

    /* Named apart from the helpers in MetaAdsCostFetchTest: Pest test files share one process, so two
       global functions of the same name are a fatal redeclaration rather than an override. */
    it('reports instagram spend as its own channel', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(igSplitInsights([
                igSplitRow('120000009', 40.00, $this->shop->currency->code, 'facebook'),
                igSplitRow('120000009', 15.00, $this->shop->currency->code, 'instagram'),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        $meta      = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
        $instagram = TrafficSourceCost::where('traffic_source_id', $this->instagramAds->id)->get();

        expect((float) $meta->sum('source_amount'))->toBe(40.00);
        expect((float) $instagram->sum('source_amount'))->toBe(15.00);
    });

    it('keeps a campaign row for each half of the same meta campaign', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(igSplitInsights([
                igSplitRow('120000012', 40.00, $this->shop->currency->code, 'facebook'),
                igSplitRow('120000012', 15.00, $this->shop->currency->code, 'instagram'),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        /* References are unique across every traffic source, so both halves only keep their campaign
           breakdown while the Instagram one claims a different string. */
        expect(TrafficSourceCampaign::where('reference', '120000012')->value('traffic_source_id'))->toBe($this->metaAds->id);
        expect(TrafficSourceCampaign::where('reference', 'ig-120000012')->value('traffic_source_id'))->toBe($this->instagramAds->id);
    });

    it('sums the non instagram platforms into one meta ads row', function () {
        Http::fake([
            'graph.facebook.com/*' => Http::response(igSplitInsights([
                igSplitRow('120000010', 10.00, $this->shop->currency->code, 'facebook'),
                igSplitRow('120000010', 4.00, $this->shop->currency->code, 'audience_network'),
                igSplitRow('120000010', 1.50, $this->shop->currency->code, 'messenger'),
            ])),
        ]);

        Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();

        expect($costs)->toHaveCount(1);
        expect((float) $costs->first()->source_amount)->toBe(15.50);
    });

    it('fails loudly when the instagram source has not been seeded', function () {
        $this->instagramAds->delete();

        Http::fake([
            'graph.facebook.com/*' => Http::response(igSplitInsights([
                igSplitRow('120000011', 10.00, $this->shop->currency->code, 'facebook'),
            ])),
        ]);

        $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

        expect($exit)->toBe(1);
        expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
    });
});

describe('mailshot click attribution', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('mailshot click attribution');

        $this->customer = createOwnCustomer($this->shop, 'mailshot click attribution');
        $this->customer->trafficSources()->detach();
        $this->customer->update(['traffic_sources' => null]);
        $this->outbox   = $this->shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();
    });

    it('resolves the mailshot through mailshot_recipients, not the dropped column', function () {
        $mailshot        = StoreMailshot::make()->action($this->outbox, Mailshot::factory()->definition());
        $dispatchedEmail = dispatchedEmailFor($this->outbox, $this->customer);

        MailshotRecipient::create([
            'mailshot_id'         => $mailshot->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'recipient_type'      => 'Customer',
            'recipient_id'        => $this->customer->id,
            'channel'             => 1,
        ]);

        // The legacy relation is dead: its column was dropped in May 2025.
        expect($dispatchedEmail->fresh()->mailshot)->toBeNull();

        // The working one finds it.
        expect($dispatchedEmail->fresh()->sentMailshot?->id)->toBe($mailshot->id);
    });

    it('queues a touchpoint when a mailshot email is clicked', function () {
        $mailshot        = StoreMailshot::make()->action($this->outbox, Mailshot::factory()->definition());
        $dispatchedEmail = dispatchedEmailFor($this->outbox, $this->customer);

        MailshotRecipient::create([
            'mailshot_id'         => $mailshot->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'recipient_type'      => 'Customer',
            'recipient_id'        => $this->customer->id,
            'channel'             => 1,
        ]);

        $this->customer->update(['traffic_sources' => null]);

        StoreEmailTrackingEvent::make()->handle($dispatchedEmail->fresh(), [
            'type' => EmailTrackingEventTypeEnum::CLICKED,
            'data' => [],
        ]);

        expect($this->customer->fresh()->traffic_sources)
            ->toContain(App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id);
    });

    it('records a click row with ip and user agent when the ses event carries them', function () {
        $mailshot        = StoreMailshot::make()->action($this->outbox, Mailshot::factory()->definition());
        $dispatchedEmail = dispatchedEmailFor($this->outbox, $this->customer);

        MailshotRecipient::create([
            'mailshot_id'         => $mailshot->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'recipient_type'      => 'Customer',
            'recipient_id'        => $this->customer->id,
            'channel'             => 1,
        ]);

        Illuminate\Support\Facades\Queue::fake();

        StoreEmailTrackingEvent::make()->handle($dispatchedEmail->fresh(), [
            'type' => EmailTrackingEventTypeEnum::CLICKED,
            'data' => ['l' => 'https://ancientwisdom.biz/offers', 'ipAddress' => '203.0.113.77', 'userAgent' => 'Mozilla/5.0'],
        ]);

        Illuminate\Support\Facades\Queue::assertPushed(
            Lorisleiva\Actions\Decorators\JobDecorator::class,
            fn ($job) => $job->decorates(RecordTrafficSourceClick::class)
                && $job->getParameters()[0]['ip'] === '203.0.113.77'
                && $job->getParameters()[0]['url'] === 'https://ancientwisdom.biz/offers'
        );
    });

    it('gives a scanner burst no touch', function () {
        $mailshot = StoreMailshot::make()->action($this->outbox, Mailshot::factory()->definition());

        foreach (range(1, 5) as $i) {
            RecordTrafficSourceClick::countScannerClick(
                '198.51.100.30',
                App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id
            );
        }

        $this->customer->update(['traffic_sources' => null]);

        App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint::run(
            $this->customer,
            now(),
            $mailshot,
            null,
            '198.51.100.30'
        );

        expect($this->customer->fresh()->traffic_sources)->toBeNull();
    });

    it('records no click row for replayed events without an ip, so aurora imports stay out', function () {
        $mailshot        = StoreMailshot::make()->action($this->outbox, Mailshot::factory()->definition());
        $dispatchedEmail = dispatchedEmailFor($this->outbox, $this->customer);

        MailshotRecipient::create([
            'mailshot_id'         => $mailshot->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'recipient_type'      => 'Customer',
            'recipient_id'        => $this->customer->id,
            'channel'             => 1,
        ]);

        Illuminate\Support\Facades\Queue::fake();

        StoreEmailTrackingEvent::make()->handle($dispatchedEmail->fresh(), [
            'type' => EmailTrackingEventTypeEnum::CLICKED,
            'data' => [],
        ]);

        Illuminate\Support\Facades\Queue::assertNotPushed(
            Lorisleiva\Actions\Decorators\JobDecorator::class,
            fn ($job) => $job->decorates(RecordTrafficSourceClick::class)
        );
    });

    it('queues nothing when a transactional email is clicked', function () {
        $dispatchedEmail = dispatchedEmailFor($this->outbox, $this->customer);

        $this->customer->update(['traffic_sources' => null]);

        StoreEmailTrackingEvent::make()->handle($dispatchedEmail->fresh(), [
            'type' => EmailTrackingEventTypeEnum::CLICKED,
            'data' => [],
        ]);

        expect($this->customer->fresh()->traffic_sources)->toBeNull();
    });

    it('credits a reorder reminder click to the automated emails channel, not to newsletter', function () {
        $automated = createTrafficSource($this->shop, 'email-automated', 'Automated Emails');

        RecordEmailClickTouchpoint::run($this->customer, now(), null, 'reorder_reminder');

        $credited = $this->customer->fresh()->trafficSources()->get();

        expect($credited)->toHaveCount(1)
            ->and($credited->first()->type)->toBe('email-automated');

        $campaign = App\Models\CRM\TrafficSourceCampaign::where('traffic_source_id', $automated->id)
            ->where('reference', 'outbox-reorder_reminder')
            ->first();

        expect($campaign)->not->toBeNull()
            ->and($campaign->name)->toBe('Reorder Reminder');
    });

    it('keeps a second reorder reminder click on the same day from counting twice', function () {
        createTrafficSource($this->shop, 'email-automated', 'Automated Emails');

        RecordEmailClickTouchpoint::run($this->customer, now(), null, 'reorder_reminder');
        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->addMinutes(5), null, 'reorder_reminder');

        expect(App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches::run($this->customer->fresh()->traffic_sources))
            ->toHaveCount(1);
    });

    it('counts a mailshot click as a visit, since nothing identifies it once the reader lands', function () {
        $key = 'traffic_visits:'.now()->toDateString().':'.$this->shop->id.':newsletter';

        Illuminate\Support\Facades\Cache::forget($key);

        $mailshot        = StoreMailshot::make()->action($this->outbox, Mailshot::factory()->definition());
        $dispatchedEmail = dispatchedEmailFor($this->outbox, $this->customer);

        MailshotRecipient::create([
            'mailshot_id'         => $mailshot->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'recipient_type'      => 'Customer',
            'recipient_id'        => $this->customer->id,
            'channel'             => 1,
        ]);

        StoreEmailTrackingEvent::make()->handle($dispatchedEmail->fresh(), [
            'type' => EmailTrackingEventTypeEnum::CLICKED,
            'data' => [],
        ]);

        expect((int) Illuminate\Support\Facades\Cache::get($key, 0))->toBe(1);
    });

    it('sends a marketing mailshot click to its own channel, apart from the newsletter', function () {
        createTrafficSource($this->shop, 'marketing-mailshot', 'Marketing Mailshots');

        $mailshot = StoreMailshot::make()->action($this->outbox, array_merge(
            Mailshot::factory()->definition(),
            ['type' => App\Enums\Comms\Mailshot\MailshotTypeEnum::MARKETING]
        ));

        $this->customer->trafficSources()->detach();
        $this->customer->update(['traffic_sources' => null]);

        RecordEmailClickTouchpoint::run($this->customer, now(), $mailshot);

        $credited = $this->customer->fresh()->trafficSources()->get();

        expect($credited)->toHaveCount(1)
            ->and($credited->first()->type)->toBe('marketing-mailshot');
    });

    it('keeps a newsletter click on the newsletter channel', function () {
        $mailshot = StoreMailshot::make()->action($this->outbox, array_merge(
            Mailshot::factory()->definition(),
            ['type' => App\Enums\Comms\Mailshot\MailshotTypeEnum::NEWSLETTER]
        ));

        $this->customer->trafficSources()->detach();
        $this->customer->update(['traffic_sources' => null]);

        RecordEmailClickTouchpoint::run($this->customer, now(), $mailshot);

        expect($this->customer->fresh()->trafficSources()->first()->type)->toBe('newsletter');
    });

    it('does not collide when a mailshot already has a campaign under the newsletter channel', function () {
        /* AIKU-18ZB: traffic_source_campaigns.reference is unique across the whole table, so once
           newsletters and marketing mailshots became separate channels, the same mailshot-N reference
           could not be created twice. */
        createTrafficSource($this->shop, 'marketing-mailshot', 'Marketing Mailshots');

        $mailshot = StoreMailshot::make()->action($this->outbox, array_merge(
            Mailshot::factory()->definition(),
            ['type' => App\Enums\Comms\Mailshot\MailshotTypeEnum::MARKETING]
        ));

        $newsletter = App\Models\CRM\TrafficSource::where('shop_id', $this->shop->id)->where('type', 'newsletter')->first();

        App\Models\CRM\TrafficSourceCampaign::create([
            'traffic_source_id' => $newsletter->id,
            'reference'         => RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id,
            'slug'              => 'ms-'.uniqid(),
            'name'              => 'Already there',
            'type'              => 'newsletter',
        ]);

        $this->customer->trafficSources()->detach();
        $this->customer->update(['traffic_sources' => null]);

        RecordEmailClickTouchpoint::run($this->customer, now(), $mailshot);

        expect($this->customer->fresh()->trafficSources()->first()->type)->toBe('marketing-mailshot');
    });
});

describe('recording email click touchpoints', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('recording email click touchpoints');

        $this->customer = createOwnCustomer($this->shop, 'recording email click touchpoints');
        $this->customer->update(['traffic_sources' => null]);
        $this->customer->trafficSources()->detach();
    });

    it('records an email click as a newsletter touch and attributes it', function () {
        RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'));

        $trafficSources = $this->customer->fresh()->trafficSources()->get();

        expect($trafficSources)->toHaveCount(1);
        expect($trafficSources->first()->type)->toBe(TrafficSourcesTypeEnum::NEWSLETTER->value);
        expect((float) $trafficSources->first()->pivot->share)->toBe(1.0);
    });

    it('does not record a duplicate touch for a repeat click on the same day', function () {
        RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'));
        RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 18:00:00'));

        $touches = explode('|', $this->customer->fresh()->traffic_sources);

        expect($touches)->toHaveCount(1);
    });

    it('records a new touch for a click occurring on a later day', function () {
        RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'));
        RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-02 10:00:00'));

        $touches = explode('|', $this->customer->fresh()->traffic_sources);

        expect($touches)->toHaveCount(2);
    });

    it('preserves earlier touches and layers the newsletter click on top for attribution', function () {
        $this->customer->update(['traffic_sources' => '1700000000a']);

        RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

        $trafficSources = $this->customer->fresh()->trafficSources()->get();

        expect($trafficSources)->toHaveCount(2);
        expect($trafficSources->pluck('type')->all())->toEqualCanonicalizing([
            TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value,
            TrafficSourcesTypeEnum::NEWSLETTER->value,
        ]);
    });

    it('links the touch to a traffic source campaign matching the mailshot', function () {
        $outbox   = $this->shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();
        $mailshot = StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());

        RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'), $mailshot);

        $trafficSource = TrafficSource::where('shop_id', $this->shop->id)
            ->where('type', TrafficSourcesTypeEnum::NEWSLETTER->value)
            ->first();

        $campaign = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
            ->where('reference', RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id)
            ->first();

        expect($campaign)->not->toBeNull();

        $pivot = $this->customer->fresh()->trafficSources()->wherePivot('traffic_source_campaign_id', $campaign->id)->first();

        expect($pivot)->not->toBeNull();
    });

    it('does not record a duplicate touch for a repeat click on the same mailshot on the same day', function () {
        $outbox   = $this->shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();
        $mailshot = StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());

        RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'), $mailshot);
        RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 18:00:00'), $mailshot);

        $touches = explode('|', $this->customer->fresh()->traffic_sources);

        expect($touches)->toHaveCount(1);
    });

    it('caps the touch history, keeping the first touch and dropping the oldest of the rest', function () {
        $history = collect(range(1, RecordEmailClickTouchpoint::MAX_TOUCHES))
            ->map(fn (int $i) => (1700000000 + $i).'a')
            ->implode('|');

        $this->customer->update(['traffic_sources' => $history]);

        RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

        $touches = explode('|', $this->customer->fresh()->traffic_sources);

        expect($touches)->toHaveCount(RecordEmailClickTouchpoint::MAX_TOUCHES);
        expect($touches[0])->toBe('1700000001a');
        expect($touches[1])->toBe('1700000003a');
    });

    it('keeps the attribution model already stamped on the record when recording a click', function () {
        $this->customer->update(['traffic_sources' => '1700000000a']);

        RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);

        RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

        $trafficSources = $this->customer->fresh()->trafficSources()->get();

        expect($trafficSources->pluck('pivot.attribution_model')->unique()->all())
            ->toBe([ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH]);
    });

    it('also records an email click as a newsletter touch for a prospect', function () {
        $prospect = StoreProspect::make()->action($this->shop, Prospect::factory()->definition());

        RecordEmailClickTouchpoint::run($prospect, Carbon::parse('2026-01-01 10:00:00'));

        $trafficSources = $prospect->fresh()->trafficSources()->get();

        expect($trafficSources)->toHaveCount(1);
        expect($trafficSources->first()->type)->toBe(TrafficSourcesTypeEnum::NEWSLETTER->value);
    });

    it('does not record a duplicate touch for a prospect clicking again on the same day', function () {
        $prospect = StoreProspect::make()->action($this->shop, Prospect::factory()->definition());

        RecordEmailClickTouchpoint::run($prospect, Carbon::parse('2026-01-01 10:00:00'));
        RecordEmailClickTouchpoint::run($prospect->fresh(), Carbon::parse('2026-01-01 18:00:00'));

        $touches = explode('|', $prospect->fresh()->traffic_sources);

        expect($touches)->toHaveCount(1);
    });
});

describe('email marketing performance', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        config()->set('services.ses.cost_per_thousand_usd', 100.0);

        // Pin the USD rate so the estimated-cost assertions never depend on a live exchange fetch.
        \Illuminate\Support\Facades\Cache::put('current-currency-exchange:USD-GBP', 1.0, 600);
        \Illuminate\Support\Facades\Cache::put('current-currency-exchange:USD-EUR', 1.0, 600);

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('email marketing performance');

        $this->customer = createOwnCustomer($this->shop, 'email marketing performance');
        $this->customer->update(['traffic_sources' => null]);
        $this->customer->trafficSources()->detach();

        // Shared fixtures: clear invoices so revenue assertions do not accumulate across tests.
        DB::table('invoices')->where('customer_id', $this->customer->id)->delete();

        $this->googleAds  = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->newsletter = createTrafficSource($this->shop, 'newsletter', 'Newsletter');
    });

    it('returns empty performance for a shop that has sent nothing', function () {
        $performance = GetShopEmailMarketingPerformance::run($this->shop);

        expect($performance['mailshots'])->toBe([]);
        expect($performance['totals']['sent'])->toBe(0);
        expect($performance['totals']['attributed_revenue'])->toBe(0.0);
    });

    it('splits customer credit between channels so their stats sum to the real totals', function () {
        $this->customer->update(['traffic_sources' => now()->subDays(11)->timestamp.'b']);
        \App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->subDays(10));

        createInvoiceFor($this->customer, $this->shop, now()->subDay()->toDateTimeString(), 1000);
        foreach (range(1, 4) as $ignored) {
            createDispatchedOrderFor($this->customer, $this->shop, now()->subDay()->toDateTimeString());
        }

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

        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->subDays(10), $mailshot);

        createInvoiceFor($this->customer, $this->shop, now()->subDay()->toDateTimeString(), 250);

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

        $this->customer->update(['traffic_sources' => now()->subDays(11)->timestamp.'b']);
        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->subDays(10), $mailshot);

        createInvoiceFor($this->customer, $this->shop, now()->subDay()->toDateTimeString(), 1000);

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

        RecordEmailClickTouchpoint::run($prospect, now()->subDays(10), $mailshot);

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

        $this->customer->update(['traffic_sources' => now()->subDays(11)->timestamp.'b']);
        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->subDays(10), $mailshot);

        createInvoiceFor($this->customer, $this->shop, now()->subDay()->toDateTimeString(), 1000);
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

    it('folds a second device cookie into the customer journey', function () {
        createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        // Desktop: registered from a google-ads click.
        $this->customer->update(['traffic_sources' => '1700000000b']);
        \App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        // Phone: an organic visit this browser saw but the server never did.
        \App\Actions\CRM\TrafficSource\SyncCustomerTrafficSourcesFromDevice::run(
            $this->customer->fresh(),
            '1700000000b|1700000100a'
        );

        $customer = $this->customer->fresh();
        expect($customer->traffic_sources)->toBe('1700000000b|1700000100a');

        $shares = $customer->trafficSources()->get();
        expect($shares)->toHaveCount(2);
        expect(round($shares->sum(fn ($row) => (float) $row->pivot->share), 2))->toBe(1.0);
    });

    it('does nothing when the device cookie holds nothing new', function () {
        $this->customer->update(['traffic_sources' => '1700000000b']);
        \App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($this->customer->fresh());
        $updatedAt = $this->customer->fresh()->updated_at;

        \App\Actions\CRM\TrafficSource\SyncCustomerTrafficSourcesFromDevice::run($this->customer->fresh(), '1700000000b');

        expect($this->customer->fresh()->updated_at->eq($updatedAt))->toBeTrue();
    });

    it('keeps repeat clickers visible to every mailshot they clicked', function () {
        $first  = makeMailshot($this->shop);
        $second = makeMailshot($this->shop);
        $first->stats()->update(['number_dispatched_emails' => 10]);
        $second->stats()->update(['number_dispatched_emails' => 10]);

        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->subDays(10), $first);
        RecordEmailClickTouchpoint::run($this->customer->fresh(), now()->subDays(9), $second);

        createInvoiceFor($this->customer, $this->shop, now()->subDay()->toDateTimeString(), 1000);

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

        RecordEmailClickTouchpoint::run($prospect, now()->subDays(10), $mailshot);

        $performance = GetShopEmailMarketingPerformance::run($this->shop);
        expect($performance['mailshots'][0]['prospects_registered'])->toBe(0);

        $prospect->update(['customer_id' => $this->customer->id]);

        $performance = GetShopEmailMarketingPerformance::run($this->shop);
        expect($performance['mailshots'][0]['prospects_registered'])->toBe(1);
    });

    it('prices the newsletter channel from the emails it sent, instead of showing it as free', function () {
        $mailshot = App\Models\Comms\Mailshot::where('shop_id', $this->shop->id)->first();

        expect($mailshot)->not->toBeNull();

        $mailshot->stats()->update(['number_dispatched_emails' => 100000]);
        $mailshot->update(['sent_at' => now()->subDay()]);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', 'newsletter');

        expect($channel['spend'])->toBeGreaterThan(0.0)
            ->and($channel['spend_is_estimated'])->toBeTrue();
    });

    it('separates what the ads cost from what the emails cost', function () {
        $mailshot = App\Models\Comms\Mailshot::where('shop_id', $this->shop->id)->first();
        $mailshot->stats()->update(['number_dispatched_emails' => 100000]);
        $mailshot->update(['sent_at' => now()->subDay()]);

        $googleAds = App\Models\CRM\TrafficSource::where('shop_id', $this->shop->id)->where('type', 'google-ads')->first();

        App\Actions\CRM\TrafficSource\StoreTrafficSourceCost::run($googleAds, [
            'date'               => now()->subDay()->toDateString(),
            'source_amount'      => 200,
            'source_currency_id' => $this->shop->currency_id,
        ]);

        $totals = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['totals'];

        expect($totals['spend_ads'])->toBe(200.0)
            ->and($totals['spend_email'])->toBeGreaterThan(0.0)
            ->and(round($totals['spend_ads'] + $totals['spend_email'], 2))->toBe($totals['spend']);
    });

    it('shows subscribers lost beside the newsletter, never subtracted from registrations', function () {
        $mailshot = App\Models\Comms\Mailshot::where('shop_id', $this->shop->id)->first();
        $mailshot->stats()->update([
            'number_dispatched_emails'                    => 5000,
            'number_dispatched_emails_state_unsubscribed' => 4,
        ]);
        $mailshot->update(['sent_at' => now()->subDay()]);

        $channel = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['channels'])
            ->firstWhere('type', 'newsletter');

        expect($channel['unsubscribed'])->toBe(4)
            ->and($channel['registrations'])->toBeGreaterThanOrEqual(0.0);
    });

    it('charges automated marketing for its sends, at the same rate as any other email', function () {
        $outbox = $this->shop->outboxes()->where('code', 'oos_notification')->first();

        expect($outbox)->not->toBeNull();

        foreach (range(1, 500) as $ignored) {
            $outbox->dispatchedEmails()->create(['data' => [], 'sent_at' => now()]);
        }

        $channel = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['channels'])
            ->firstWhere('type', 'email-automated');

        expect($channel)->not->toBeNull()
            ->and($channel['spend'])->toBeGreaterThan(0.0)
            ->and($channel['spend_is_estimated'])->toBeTrue();
    });

    it('does not charge automated marketing for emails that were never sent', function () {
        $outbox = $this->shop->outboxes()->where('code', 'oos_notification')->first();

        $spend = fn () => collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['channels'])
            ->firstWhere('type', 'email-automated')['spend'] ?? 0.0;

        $before = $spend();

        foreach (range(1, 500) as $ignored) {
            $outbox->dispatchedEmails()->create(['data' => [], 'sent_at' => null]);
        }

        expect($spend())->toBe($before);
    });
});

describe('offer performance', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('offer performance');

        $this->customer = createOwnCustomer($this->shop, 'offer performance');

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
            'type'            => 'Amount',
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

    it('leaves discretionary discounts out, since no campaign caused them', function () {
        DB::table('offers')->where('id', $this->offerId)->update(['type' => 'Discretionary']);

        redeemOffer($this->customer, $this->shop, $this->offerId, now()->subDay()->toDateTimeString(), $this->campaignId, $this->allowanceId);

        $offers = GetShopOfferPerformance::run($this->shop, MarketingPeriodEnum::LAST_7)['offers'];

        expect(collect($offers)->firstWhere('name', 'Test Offer'))->toBeNull();
    });
});

describe('marketing periods', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('marketing periods');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->customer  = createOwnCustomer($this->shop, 'marketing periods');

        $this->customer->trafficSources()->detach();
        // Touch 70 days ago: old enough to precede the fixtures' invoices, recent enough that they
        // fall inside the 90-day attribution window.
        $this->touchedAt = now()->subDays(70);
        $this->customer->update(['traffic_sources' => $this->touchedAt->timestamp.'b']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
        DB::table('invoices')->where('customer_id', $this->customer->id)->delete();
        DB::table('orders')->where('customer_id', $this->customer->id)->delete();

        /* Marketing spend prices in an estimated cost per email sent, so mailshots a previous block
           sent from this shop land in this block's spend as pence nobody here spent. This block
           measures spend from advertising costs alone - as its own file it saw a database where no
           mailshot had ever been sent, and these two lines are what that condition looks like when
           the database is shared. */
        clearMailshotsFor($this->shop);
        config()->set('services.ses.cost_per_thousand_usd', 0);
    });

    it('counts only revenue and spend inside the selected period', function () {
        invoiceOn(now()->subDays(3)->toDateTimeString(), 400, $this->customer, $this->shop);
        invoiceOn(now()->subDays(60)->toDateTimeString(), 1000, $this->customer, $this->shop);

        StoreTrafficSourceCost::run($this->googleAds, [
            'date'               => now()->subDays(3)->toDateString(),
            'source_amount'      => 100,
            'source_currency_id' => $this->shop->currency_id,
        ]);
        StoreTrafficSourceCost::run($this->googleAds, [
            'date'               => now()->subDays(60)->toDateString(),
            'source_amount'      => 500,
            'source_currency_id' => $this->shop->currency_id,
        ]);

        $recent = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);

        expect($recent['totals']['revenue'])->toBe(400.0);
        expect($recent['totals']['spend'])->toBe(100.0);
        expect($recent['totals']['roas'])->toBe(4.0);

        $allTime = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

        // The -60d invoice postdates the touch and is inside the window, so all-time sees both.
        expect($allTime['totals']['revenue'])->toBe(1400.0);
        expect($allTime['totals']['spend'])->toBe(600.0);
    });

    it('reproduces the lifetime stats figure when the period is all time', function () {
        // The 200-day-old invoice predates the touch, so neither the dashboard nor the rollup counts it.
        invoiceOn(now()->subDays(200)->toDateTimeString(), 250, $this->customer, $this->shop);
        invoiceOn(now()->subDay()->toDateTimeString(), 750, $this->customer, $this->shop);

        // sales_all is maintained by its own hydrator; bring it in sync before comparing the two paths.
        App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices::run($this->customer->id);
        TrafficSourceHydrateCustomers::run($this->googleAds);

        $allTime = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

        expect($allTime['totals']['revenue'])
            ->toBe(round((float) $this->googleAds->stats()->first()->total_customer_revenue, 2));
    });

    it('splits period revenue between channels by their shares', function () {
        $organic = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        $this->customer->update(['traffic_sources' => $this->touchedAt->timestamp.'b|'.$this->touchedAt->addSecond()->timestamp.'a']);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        invoiceOn(now()->subDay()->toDateTimeString(), 1000, $this->customer, $this->shop);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
        $channels = collect($overview['channels'])->keyBy('type');

        expect($channels['google-ads']['revenue'])->toBe(500.0);
        expect($channels['organic-google']['revenue'])->toBe(500.0);
        expect($overview['totals']['revenue'])->toBe(1000.0);
    });

    it('excludes in-process refund invoices', function () {
        invoiceOn(now()->subDay()->toDateTimeString(), 500, $this->customer, $this->shop);
        invoiceOn(now()->subDay()->toDateTimeString(), 9999, $this->customer, $this->shop, inProcess: true);

        expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['totals']['revenue'])
            ->toBe(500.0);
    });

    it('reports the selected period back to the dashboard', function () {
        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::MONTH_TO_DATE);

        expect($overview['period'])->toBe('month_to_date');
        expect($overview['from'])->toBe(now()->startOfMonth()->toDateString());

        /* All time means since we started recording, not since the beginning: every figure on the screen
           is capped at the attribution marker, so the window it reports has to say so. */
        expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['from'])
            ->toBe(App\Actions\CRM\TrafficSource\GetAttributionStartedAt::run()?->toDateString());
    });

    it('serves the same period numbers through the daily sql view', function () {
        invoiceOn(now()->subDays(3)->toDateTimeString(), 400, $this->customer, $this->shop);
        StoreTrafficSourceCost::run($this->googleAds, [
            'date'               => now()->subDays(3)->toDateString(),
            'source_amount'      => 100,
            'source_currency_id' => $this->shop->currency_id,
        ]);

        $row = collect(DB::select(
            'SELECT SUM(revenue) AS revenue, SUM(cost) AS cost
             FROM marketing_channel_daily
             WHERE shop_id = ? AND date >= ?',
            [$this->shop->id, now()->subDays(7)->toDateString()]
        ))->first();

        expect(round((float) $row->revenue, 2))->toBe(400.0);
        expect(round((float) $row->cost, 2))->toBe(100.0);
    });

    it('shows placed but uninvoiced order value as pending revenue, separate from invoiced', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted');
        invoiceOn(now()->subDay()->toDateTimeString(), 400, $this->customer, $this->shop);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);

        expect($overview['totals']['revenue'])->toBe(400.0)
            ->and($overview['totals']['pending'])->toBe(100.0);

        $channel = collect($overview['channels'])->firstWhere('type', 'google-ads');
        expect($channel['pending'])->toBe($overview['totals']['pending']);
    });

    it('drops an order out of pending once it has an invoice', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted');

        $orderId = DB::table('orders')->where('customer_id', $this->customer->id)->orderByDesc('id')->value('id');
        DB::table('invoices')->where('customer_id', $this->customer->id)->delete();
        invoiceOn(now()->subHour()->toDateTimeString(), 100, $this->customer, $this->shop);
        DB::table('invoices')->where('customer_id', $this->customer->id)->update(['order_id' => $orderId]);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);

        expect($overview['totals']['pending'])->toBe(0.0);
    });

    it('does not count a cancelled order as pending', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'cancelled');

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);

        expect($overview['totals']['pending'])->toBe(0.0);
    });

    it('reports the shop baseline alongside the attributed figures', function () {
        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);

        expect($overview['baseline'])->toHaveKeys(['registrations', 'orders', 'revenue'])
            ->and($overview['baseline']['registrations'])->toBeGreaterThan(0.0);
    });

    it('shows visits a channel sent even when none of them converted', function () {
        $source = App\Models\CRM\TrafficSource::where('shop_id', $this->shop->id)->where('type', 'google-ads')->first();

        DB::table('traffic_source_visits')->insert([
            'shop_id'           => $this->shop->id,
            'traffic_source_id' => $source->id,
            'date'              => now()->subDay()->toDateString(),
            'visits'            => 480,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

        expect($channel)->not->toBeNull()
            ->and($channel['visits'])->toBe(480)
            ->and($channel['revenue'])->toBe(0.0);
    });

    it('does not report a roas of zero while money is still awaiting invoice', function () {
        $source = App\Models\CRM\TrafficSource::where('shop_id', $this->shop->id)->where('type', 'google-ads')->first();

        App\Actions\CRM\TrafficSource\StoreTrafficSourceCost::run($source, [
            'date'               => now()->subDay()->toDateString(),
            'source_amount'      => 100,
            'source_currency_id' => $this->shop->currency_id,
        ]);
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted');

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

        expect($channel['pending'])->toBeGreaterThan(0.0)
            ->and($channel['revenue'])->toBe(0.0)
            ->and($channel['roas'])->toBeNull();
    });

    it('reports orders per channel so a visit count can be read against what it produced', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted');

        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

        expect($channel['orders'])->toBe(1.0);
    });

    it('caps spend at the attribution marker, like every other figure on the screen', function () {
        $source = App\Models\CRM\TrafficSource::where('shop_id', $this->shop->id)->where('type', 'google-ads')->first();

        App\Actions\CRM\TrafficSource\StoreTrafficSourceCost::run($source, [
            'date'               => now()->subYears(2)->toDateString(),
            'source_amount'      => 5000,
            'source_currency_id' => $this->shop->currency_id,
        ]);

        /* One window for the whole screen: spend from before we were measuring cannot be set against
           revenue we could not have attributed, or ROAS compares two different stretches of time. */
        $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

        expect($overview['totals']['spend'])->toBe(0.0);
    });

    it('groups channels so nineteen rows read as four questions', function () {
        $groups = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['channels'])
            ->pluck('group', 'type');

        expect($groups['google-ads'] ?? null)->toBe('paid');

        expect(App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum::ORGANIC_GOOGLE->group()['key'])->toBe('organic')
            ->and(App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum::NEWSLETTER->group()['key'])->toBe('email')
            ->and(App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum::EMAIL_AUTOMATED->group()['key'])->toBe('email')
            ->and(App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum::REFERRAL->group()['key'])->toBe('other')
            ->and(App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum::YOUTUBE->group()['key'])->toBe('other');
    });

    it('gives the shop dashboard every field its grouped table needs', function () {
        $channel = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['channels'])
            ->firstWhere('type', 'google-ads');

        expect($channel)->toHaveKeys([
            'name', 'type', 'group', 'group_label', 'group_position',
            'visits', 'orders', 'spend', 'spend_is_estimated', 'pending', 'revenue',
            'registrations', 'unsubscribed', 'roas',
        ]);
    });
});

describe('the aggregated marketing overview', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('the aggregated marketing overview');

        $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
        $this->customer  = createOwnCustomer($this->shop, 'the aggregated marketing overview');
        $this->customer->trafficSources()->detach();

        $this->customer->update([
            'created_at'      => now()->subDays(3),
            'traffic_sources' => now()->subDays(4)->timestamp.'b',
        ]);
        RecalculateTrafficSourceAttribution::run($this->customer->fresh());

        DB::table('orders')->where('customer_id', $this->customer->id)->delete();
        DB::table('invoices')->where('customer_id', $this->customer->id)->delete();
    });

    it('reports the organisation total in the organisation currency, not the shop currency', function () {
        DB::table('invoices')->insert([
            'group_id'        => $this->shop->group_id,
            'organisation_id' => $this->shop->organisation_id,
            'shop_id'         => $this->shop->id,
            'customer_id'     => $this->customer->id,
            'currency_id'     => $this->shop->currency_id,
            'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
            'reference'       => 'INV-'.uniqid(),
            'slug'            => 'inv-'.uniqid(),
            'type'            => 'invoice',
            'net_amount'      => 100,
            'org_net_amount'  => 250,
            'grp_net_amount'  => 400,
            'total_amount'    => 100,
            'in_process'      => false,
            'payment_data'    => '{}',
            'data'            => '{}',
            'date'            => now()->subDay()->toDateTimeString(),
            'created_at'      => now()->subDay()->toDateTimeString(),
            'updated_at'      => now()->subDay()->toDateTimeString(),
        ]);

        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

        expect($overview['totals']['revenue'])->toBe(250.0)
            ->and($overview['currency_code'])->toBe($this->organisation->currency->code);

        $channel = collect($overview['channels'])->firstWhere('type', 'google-ads');

        expect($channel['revenue'])->toBe(250.0)
            ->and($channel['registrations'])->toBe(1.0);
    });

    it('links each shop of the organisation to its own dashboard instead of repeating it', function () {
        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

        $child = collect($overview['children'])->firstWhere('slug', $this->shop->slug);

        expect($child)->not->toBeNull()
            ->and($child['route']['name'])->toBe('grp.org.shops.show.marketing.dashboard')
            ->and($child['route']['parameters'])->toBe([$this->organisation->slug, $this->shop->slug]);
    });

    it('reports the group total and its spend in the group currency', function () {
        App\Actions\CRM\TrafficSource\StoreTrafficSourceCost::run($this->googleAds, [
            'date'               => now()->subDay()->toDateString(),
            'source_amount'      => 100,
            'source_currency_id' => $this->shop->currency_id,
        ]);

        $group    = $this->organisation->group;
        $overview = GetAggregatedMarketingOverview::run($group, MarketingPeriodEnum::LAST_7);

        $expected = (float) App\Models\CRM\TrafficSourceCost::where('traffic_source_id', $this->googleAds->id)
            ->sum('grp_amount');

        /* spend_ads rather than spend: the headline adds the estimated cost of every mailshot the
           group has sent, and the mailshots other blocks send from their own shops are still in the
           database. Ad spend is the half this test is about. */
        expect($overview['currency_code'])->toBe($group->currency->code)
            ->and($expected)->toBeGreaterThan(0.0)
            ->and($overview['totals']['spend_ads'])->toBe(round($expected, 2));
    });

    it('links the group children to each organisation dashboard', function () {
        $overview = GetAggregatedMarketingOverview::run($this->organisation->group, MarketingPeriodEnum::LAST_7);

        $child = collect($overview['children'])->firstWhere('slug', $this->organisation->slug);

        expect($child)->not->toBeNull()
            ->and($child['route']['name'])->toBe('grp.org.marketing.dashboard');
    });

    it('does not credit a registration that preceded the touch in the aggregate either', function () {
        /* createCustomer() reuses the shop's customer, so this rewrites the one the aggregate would
           otherwise have counted: its touch now lands after it registered. */
        $this->customer->trafficSources()->detach();
        $this->customer->update(['created_at' => now()->subDays(30)]);
        $this->customer->trafficSources()->attach($this->googleAds->id, [
            'share'          => 1,
            'first_touch_at' => now()->subDay(),
            'last_touch_at'  => now()->subDay(),
        ]);

        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

        expect($channel['registrations'] ?? 0.0)->toBe(0.0);
    });

    it('shows pending order value in the aggregate, in the parent currency', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted', 100);

        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

        expect($overview['totals']['pending'])->toBe(100.0);

        $channel = collect($overview['channels'])->firstWhere('type', 'google-ads');
        expect($channel['pending'])->toBe(100.0);
    });

    it('counts a submitted order in the orders figure without waiting for dispatch', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted');

        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);
        $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

        expect($channel['orders'])->toBe(1.0);
    });

    it('lets a user who can see marketing on a shop open the organisation dashboard', function () {
        $shopId = $this->shop->id;

        setPermissionsTeamId($this->organisation->group_id);
        $this->user->refresh();

        expect($this->user->authTo("marketing.$shopId.view"))->toBeTrue()
            ->and($this->user->authTo('marketing.view'))->toBeFalse();
    });

    it('reports what happened without marketing, so nought attributed can be told from nought happening', function () {
        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

        expect($overview['baseline']['registrations'])->toBeGreaterThan(0.0)
            ->and($overview['baseline'])->toHaveKeys(['registrations', 'orders', 'revenue']);
    });

    it('does not repeat invoiced revenue in the pending figure', function () {
        createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'dispatched', 100);

        $orderId = DB::table('orders')->where('customer_id', $this->customer->id)->orderByDesc('id')->value('id');

        DB::table('invoices')->insert([
            'group_id'        => $this->shop->group_id,
            'organisation_id' => $this->shop->organisation_id,
            'shop_id'         => $this->shop->id,
            'customer_id'     => $this->customer->id,
            'order_id'        => $orderId,
            'currency_id'     => $this->shop->currency_id,
            'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
            'reference'       => 'INV-'.uniqid(),
            'slug'            => 'inv-'.uniqid(),
            'type'            => 'invoice',
            'net_amount'      => 100,
            'org_net_amount'  => 100,
            'grp_net_amount'  => 100,
            'total_amount'    => 100,
            'in_process'      => false,
            'payment_data'    => '{}',
            'data'            => '{}',
            'date'            => now()->subHour()->toDateTimeString(),
            'created_at'      => now()->subHour()->toDateTimeString(),
            'updated_at'      => now()->subHour()->toDateTimeString(),
        ]);

        $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

        expect($overview['totals']['revenue'])->toBe(100.0)
            ->and($overview['totals']['pending'])->toBe(0.0);
    });

    it('pools referring sites across every shop underneath', function () {
        $referral = createTrafficSource($this->shop, 'referral', 'Referral');

        $campaignId = DB::table('traffic_source_campaigns')->insertGetId([
            'traffic_source_id' => $referral->id,
            'slug'              => 'r-'.uniqid(),
            'reference'         => 'esources.co.uk-'.uniqid(),
            'name'              => 'esources.co.uk',
            'type'              => 'referral',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->customer->trafficSources()->detach();
        $this->customer->trafficSources()->attach($referral->id, [
            'share'                      => 1,
            'traffic_source_campaign_id' => $campaignId,
            'first_touch_at'             => now()->subDays(2),
            'last_touch_at'              => now()->subDays(2),
        ]);

        $referrers = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['referrers'];

        expect(collect($referrers)->firstWhere('host', 'esources.co.uk'))->not->toBeNull()
            ->and(collect($referrers)->firstWhere('host', 'esources.co.uk')['visitors'])->toBe(1.0);
    });

    it('gives each child the total it is a share of, so a zero can be read', function () {
        $child = collect(GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['children'])
            ->firstWhere('slug', $this->shop->slug);

        expect($child)->toHaveKeys(['registrations_total', 'orders_total'])
            ->and($child['registrations_total'])->toBeGreaterThanOrEqual($child['registrations']);
    });

    it('lists search engines alongside referring sites, marked as searches', function () {
        $search = createTrafficSource($this->shop, 'organic-search', 'Organic Search (other)');

        $campaignId = DB::table('traffic_source_campaigns')->insertGetId([
            'traffic_source_id' => $search->id,
            'slug'              => 's-'.uniqid(),
            'reference'         => 'duckduckgo.com-'.uniqid(),
            'name'              => 'duckduckgo.com',
            'type'              => 'organic-search',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->customer->trafficSources()->detach();
        $this->customer->trafficSources()->attach($search->id, [
            'share'                      => 1,
            'traffic_source_campaign_id' => $campaignId,
            'first_touch_at'             => now()->subDays(2),
            'last_touch_at'              => now()->subDays(2),
        ]);

        $entry = collect(GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['referrers'])
            ->firstWhere('host', 'duckduckgo.com');

        expect($entry)->not->toBeNull()
            ->and($entry['kind'])->toBe('search');
    });

    it('gives each child its pending and its revenue total, so the share can be read', function () {
        $child = collect(GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['children'])
            ->firstWhere('slug', $this->shop->slug);

        expect($child)->toHaveKeys(['pending', 'revenue_total'])
            ->and($child['revenue_total'])->toBeGreaterThanOrEqual($child['revenue']);
    });

    it('lists an email channel that has cost but no touches yet', function () {
        /* A channel with spend and nothing to show for it is exactly the one worth seeing, so it must not
           need a touch to appear. */
        $outbox   = $this->shop->outboxes()->where('type', App\Enums\Comms\Outbox\OutboxCodeEnum::MARKETING)->first();
        $mailshot = App\Actions\Comms\Mailshot\StoreMailshot::make()->action($outbox, array_merge(
            App\Models\Comms\Mailshot::factory()->definition(),
            ['type' => App\Enums\Comms\Mailshot\MailshotTypeEnum::MARKETING]
        ));

        $mailshot->update(['sent_at' => now()->subDay()]);
        $mailshot->stats()->update(['number_dispatched_emails' => 200000]);

        createTrafficSource($this->shop, 'marketing-mailshot', 'Marketing Mailshots');

        $channel = collect(GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['channels'])
            ->firstWhere('type', 'marketing-mailshot');

        expect($channel)->not->toBeNull()
            ->and($channel['spend'])->toBeGreaterThan(0.0)
            ->and($channel['group'])->toBe('email');
    });
});

describe('order attribution', function () {
    beforeEach(function () {
        resetMarketingFixtures();

        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('order attribution');

        list(
            $this->tradeUnit,
            $this->product
        ) = createProduct($this->shop);

        $this->customer = createOwnCustomer($this->shop, 'order attribution');
        $this->order     = createOrder($this->customer, $this->product);

        // The shared fixtures above are reused across tests, reset any state left by a previous test.
        $this->customer->trafficSources()->detach();
        $this->order->trafficSources()->detach();
        $this->customer->update(['traffic_sources' => null]);
        $this->order->update([
            'traffic_sources' => null,
            'state'           => OrderStateEnum::CREATING,
            'status'          => OrderStatusEnum::CREATING,
            'pay_status'      => OrderPayStatusEnum::UNPAID,
            'submitted_at'    => null,
            'cancelled_at'    => null,
        ]);
    });

    it('exposes a traffic sources relationship on the order model', function () {
        expect($this->order->trafficSources()->count())->toBe(0);
    });

    it('logs basket ups, downs and removals with the basket value at that time', function () {
        $this->order->update(['data' => array_merge((array) $this->order->data, ['basket_log' => []])]);
        $transaction = $this->order->transactions()->where('model_type', 'Product')->first();
        $startingQuantity = (float) $transaction->quantity_ordered;

        App\Actions\Ordering\Transaction\UpdateTransaction::make()->action($transaction, ['quantity_ordered' => $startingQuantity + 5]);
        App\Actions\Ordering\Transaction\UpdateTransaction::make()->action($transaction->fresh(), ['quantity_ordered' => $startingQuantity + 2]);

        $log = collect(data_get($this->order->fresh()->data, 'basket_log'));

        expect($log->pluck('e')->all())->toBe(['up', 'down'])
            ->and((float) $log->first()['q'])->toBeGreaterThan(0)
            ->and((float) $log->last()['q'])->toBeLessThan(0)
            ->and($log->last())->toHaveKey('basket');

        $journey = App\Actions\Ordering\Order\UI\GetOrderMarketingJourney::run($this->order->fresh());
        $kinds   = collect($journey['events'])->where('type', 'product')->pluck('kind');

        expect($kinds->all())->toBe(['up', 'down']);
    });

    it('tells the order marketing story on one time axis, registration included for a first order', function () {
        createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $this->customer->update(['traffic_sources' => '1700000000b']);
        $this->order->update(['submitted_at' => now()]);
        ProcessOrderTrafficSource::run($this->order->fresh());

        $journey = App\Actions\Ordering\Order\UI\GetOrderMarketingJourney::run($this->order->fresh());

        $types = collect($journey['events'])->pluck('type');

        expect($journey['is_first_order'])->toBeTrue()
            ->and($types)->toContain('touch')
            ->and($types)->toContain('registration')
            ->and($types)->toContain('product')
            ->and(collect($journey['events'])->firstWhere('type', 'touch')['attributed'])->toBeTrue()
            ->and(collect($journey['events'])->last()['id'])->toBe('order-submitted')
            ->and($journey['attribution'][0]['label'])->toBe('Google Ads');
    });

    it('attributes an order from the customer touch history when the order has none of its own', function () {
        createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $this->customer->update(['traffic_sources' => '1700000000b']);

        ProcessOrderTrafficSource::run($this->order->fresh());

        expect($this->order->trafficSources()->count())->toBe(1);
        expect((float) $this->order->trafficSources()->first()->pivot->share)->toBe(1.0);
    });

    it('prefers the order own touch history over the customer one when present', function () {
        createTrafficSource($this->shop, 'organic-google', 'Organic Google');

        createTrafficSource($this->shop, 'google-ads', 'Google Ads');

        $this->customer->update(['traffic_sources' => '1700000000a']);
        $this->order->update(['traffic_sources' => '1700000100b']);

        ProcessOrderTrafficSource::run($this->order->fresh());

        $trafficSources = $this->order->trafficSources()->get();
        expect($trafficSources)->toHaveCount(1);
        expect($trafficSources->first()->type)->toBe('google-ads');
    });

    it('does nothing when there is no touch history at all', function () {
        ProcessOrderTrafficSource::run($this->order->fresh());

        expect($this->order->trafficSources()->count())->toBe(0);
    });

    it('attributes the order automatically when it is submitted', function () {
        createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

        $this->customer->update(['traffic_sources' => '1700000000f']);

        $order = SubmitOrder::make()->action($this->order);

        expect($order->state)->toBe(OrderStateEnum::SUBMITTED);
        expect($order->trafficSources()->count())->toBe(1);
    });

    it('leaves the submit time attribution intact when an order without its own touch history is recalculated', function () {
        createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

        $this->customer->update(['traffic_sources' => '1700000000f']);

        ProcessOrderTrafficSource::run($this->order->fresh());
        expect($this->order->trafficSources()->count())->toBe(1);

        RecalculateTrafficSourceAttribution::run($this->order->fresh());

        expect($this->order->trafficSources()->count())->toBe(1);
    });

    it('refreshes the traffic source stats but keeps the attribution audit trail when an order is cancelled', function () {
        $trafficSource = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

        $this->customer->update(['traffic_sources' => '1700000000f']);

        $order = SubmitOrder::make()->action($this->order);
        expect($order->trafficSources()->count())->toBe(1);

        $order = CancelOrder::make()->action($order->fresh());

        expect($order->state)->toBe(OrderStateEnum::CANCELLED);
        expect($order->trafficSources()->count())->toBe(1);
        expect($order->trafficSources()->first()->id)->toBe($trafficSource->id);
        expect((float) $order->trafficSources()->first()->pivot->share)->toBe(1.0);
    });
});

describe('traffic source clicks', function () {
    beforeEach(function () {
        resetMarketingFixtures();
        App\Models\CRM\TrafficSourceClick::query()->delete();
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createOwnShop('traffic source clicks');
    });

    it('records the click-level detail the aggregates drop', function () {
        RecordTrafficSourceClick::run([
            'shop_id'      => $this->shop->id,
            'website_id'   => null,
            'type'         => 'google-ads',
            'campaign_ref' => '12345',
            'click_id'     => 'TESTCLICK',
            'ip'           => '203.0.113.9',
            'country_code' => 'GB',
            'user_agent'   => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'url'          => 'https://ancientwisdom.biz/?gclid=TESTCLICK',
            'is_repeat'    => false,
        ]);

        $click = App\Models\CRM\TrafficSourceClick::sole();

        expect($click->type)->toBe('google-ads')
            ->and($click->click_id)->toBe('TESTCLICK')
            ->and($click->ip)->toBe('203.0.113.9')
            ->and($click->country_code)->toBe('GB')
            ->and($click->device_type)->toBe('Smartphone')
            ->and($click->is_bot)->toBeFalse()
            ->and($click->is_repeat)->toBeFalse();
    });

    it('marks a crawler click as a bot', function () {
        RecordTrafficSourceClick::run([
            'shop_id'      => $this->shop->id,
            'website_id'   => null,
            'type'         => 'google-ads',
            'campaign_ref' => null,
            'click_id'     => null,
            'ip'           => '203.0.113.10',
            'country_code' => null,
            'user_agent'   => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'url'          => null,
            'is_repeat'    => true,
        ]);

        expect(App\Models\CRM\TrafficSourceClick::sole()->is_bot)->toBeTrue();
    });

    it('marks a scanner burst as bot clicks and counts it no visits', function () {
        Illuminate\Support\Facades\Cache::flush();

        foreach (range(1, 5) as $i) {
            RecordTrafficSourceClick::countScannerClick('198.51.100.20', 'mailshot-99');
        }

        RecordTrafficSourceClick::run([
            'shop_id'       => $this->shop->id,
            'website_id'    => null,
            'type'          => 'newsletter',
            'campaign_ref'  => 'mailshot-99',
            'click_id'      => null,
            'ip'            => '198.51.100.20',
            'country_code'  => null,
            'user_agent'    => 'Mozilla/5.0',
            'url'           => null,
            'is_repeat'     => false,
            'check_scanner' => true,
            'count_visit'   => true,
        ]);

        $visitKey = 'traffic_visits:'.now()->toDateString().':'.$this->shop->id.':newsletter';

        expect(App\Models\CRM\TrafficSourceClick::sole()->is_bot)->toBeTrue()
            ->and((int) Illuminate\Support\Facades\Cache::get($visitKey, 0))->toBe(0);
    });

    it('counts the visit for an email click below the scanner threshold', function () {
        Illuminate\Support\Facades\Cache::flush();

        RecordTrafficSourceClick::countScannerClick('198.51.100.21', 'mailshot-99');

        RecordTrafficSourceClick::run([
            'shop_id'       => $this->shop->id,
            'website_id'    => null,
            'type'          => 'newsletter',
            'campaign_ref'  => 'mailshot-99',
            'click_id'      => null,
            'ip'            => '198.51.100.21',
            'country_code'  => null,
            'user_agent'    => 'Mozilla/5.0',
            'url'           => null,
            'is_repeat'     => false,
            'check_scanner' => true,
            'count_visit'   => true,
        ]);

        $visitKey = 'traffic_visits:'.now()->toDateString().':'.$this->shop->id.':newsletter';

        expect(App\Models\CRM\TrafficSourceClick::sole()->is_bot)->toBeFalse()
            ->and((int) Illuminate\Support\Facades\Cache::get($visitKey, 0))->toBe(1);
    });

    it('surfaces bots and same-ip clusters as suspicious, computed at read time', function () {
        foreach (range(1, 6) as $i) {
            App\Models\CRM\TrafficSourceClick::create([
                'shop_id'    => $this->shop->id,
                'type'       => 'google-ads',
                'ip'         => '198.51.100.7',
                'is_repeat'  => $i > 1,
                'created_at' => now(),
            ]);
        }
        App\Models\CRM\TrafficSourceClick::create([
            'shop_id'    => $this->shop->id,
            'type'       => 'google-ads',
            'ip'         => '198.51.100.8',
            'is_bot'     => true,
            'created_at' => now(),
        ]);
        App\Models\CRM\TrafficSourceClick::create([
            'shop_id'    => $this->shop->id,
            'type'       => 'referral',
            'ip'         => '198.51.100.9',
            'created_at' => now(),
        ]);

        $fraud = App\Actions\CRM\TrafficSource\GetShopClickFraud::run($this->shop);

        expect($fraud['totals'])->toMatchArray(['clicks' => 8, 'bots' => 1, 'ips' => 3, 'repeats' => 5])
            ->and(collect($fraud['suspect_ips'])->pluck('ip')->all())->toBe(['198.51.100.7', '198.51.100.8'])
            ->and($fraud['suspect_ips'][0]['clicks'])->toBe(6)
            ->and($fraud['recent_bots'])->toHaveCount(1)
            ->and($fraud['recent_bots'][0]['ip'])->toBe('198.51.100.8')
            ->and(collect($fraud['channels'])->firstWhere('channel', 'Google Ads')['bot_pct'])->toBe(14.3);
    });

    it('queues a click record when capture matches a source', function () {
        Illuminate\Support\Facades\Queue::fake();

        $request = Illuminate\Http\Request::create('https://ecom.test/?gclid=TESTCLICK&gad_campaignid=123', 'GET', [], [], [], [
            'HTTP_CF_IPCOUNTRY' => 'PL',
            'HTTP_USER_AGENT'   => 'Mozilla/5.0',
        ]);
        $request->attributes->set('website', (object) ['id' => 1, 'shop_id' => $this->shop->id, 'type' => null]);
        app()->instance('request', $request);

        CaptureTrafficSource::make()->getCookies();

        Illuminate\Support\Facades\Queue::assertPushed(
            Lorisleiva\Actions\Decorators\JobDecorator::class,
            fn ($job) => $job->decorates(RecordTrafficSourceClick::class)
                && $job->getParameters()[0]['click_id'] === 'TESTCLICK'
                && $job->getParameters()[0]['country_code'] === 'PL'
        );
    });
});
