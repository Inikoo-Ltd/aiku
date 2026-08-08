<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Comms\EmailTrackingEvent\StoreEmailTrackingEvent;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;
use Illuminate\Support\Facades\Artisan;

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
    $this->customer->trafficSources()->detach();
    $this->customer->update(['traffic_sources' => null]);
    $this->outbox   = $this->shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();
});

function dispatchedEmailFor($outbox, $customer): DispatchedEmail
{
    $dispatchedEmail = $outbox->dispatchedEmails()->create([
        'email_address_id' => $customer->email ? null : null,
        'data'             => [],
    ]);

    $customer->dispatchedEmails()->attach($dispatchedEmail);

    return $dispatchedEmail;
}

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
