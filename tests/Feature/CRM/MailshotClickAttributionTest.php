<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Comms\EmailTrackingEvent\StoreEmailTrackingEvent;
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
