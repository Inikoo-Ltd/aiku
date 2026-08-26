<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tue, 26 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Comms\Outbox\AbandonedCheckout\ProcessAbandonedCheckoutPerOutbox;
use App\Actions\Comms\Outbox\AbandonedCheckout\ProcessAbandonedCheckoutRecipients;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Models\Comms\Outbox;
use App\Models\Ordering\CheckoutAbandonment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->outbox = Outbox::where('code', OutboxCodeEnum::ABANDONED_CHECKOUT)
        ->whereNotNull('shop_id')
        ->first();

    if (!$this->outbox) {
        $this->markTestSkipped('No abandoned_checkout outbox seeded; run website:seed_outboxes');
    }

    $this->outbox->update([
        'state'         => OutboxStateEnum::ACTIVE,
        'is_applicable' => true,
    ]);
});

test('pending abandonment query excludes already emailed rows', function () {
    $pending = pendingAbandonmentIds($this->outbox->shop_id);

    $sent = CheckoutAbandonment::where('shop_id', $this->outbox->shop_id)
        ->where('state', CheckoutAbandonmentStateEnum::ABANDONED)
        ->whereNotNull('email_sent_at')
        ->pluck('id');

    expect($pending->intersect($sent))->toBeEmpty();
});

test('pending abandonment query excludes unsubscribed customers', function () {
    $pending = pendingAbandonmentIds($this->outbox->shop_id);

    $unsubscribed = DB::table('checkout_abandonments')
        ->join('customer_comms', 'customer_comms.customer_id', '=', 'checkout_abandonments.customer_id')
        ->where('checkout_abandonments.shop_id', $this->outbox->shop_id)
        ->where('customer_comms.is_subscribed_to_abandoned_cart', false)
        ->pluck('checkout_abandonments.id');

    expect($pending->intersect($unsubscribed))->toBeEmpty();
});

test('recipients action stamps email_sent_at so a rerun sends nothing', function () {
    $abandonment = CheckoutAbandonment::where('shop_id', $this->outbox->shop_id)
        ->where('state', CheckoutAbandonmentStateEnum::ABANDONED)
        ->whereNull('email_sent_at')
        ->first();

    if (!$abandonment) {
        $this->markTestSkipped('No pending checkout abandonment to exercise');
    }

    expect(pendingAbandonmentIds($this->outbox->shop_id))->toContain($abandonment->id);

    $abandonment->update(['email_sent_at' => now()]);

    expect(pendingAbandonmentIds($this->outbox->shop_id))->not->toContain($abandonment->id);
});

test('run action dispatches one job per active outbox only', function () {
    Queue::fake();

    ProcessAbandonedCheckoutPerOutbox::dispatch($this->outbox);

    Queue::assertPushed(ProcessAbandonedCheckoutPerOutbox::class);
});

test('recipients action is a no-op without a bulk run id', function () {
    Queue::fake();

    ProcessAbandonedCheckoutRecipients::run(null, [['id' => 1, 'order_id' => 1, 'checkout_abandonment_id' => 1]]);

    Queue::assertNothingPushed();
});

function pendingAbandonmentIds(int $shopId): \Illuminate\Support\Collection
{
    return DB::table('checkout_abandonments')
        ->join('customers', 'customers.id', '=', 'checkout_abandonments.customer_id')
        ->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_abandoned_cart', true);
        })
        ->where('checkout_abandonments.shop_id', $shopId)
        ->where('checkout_abandonments.state', CheckoutAbandonmentStateEnum::ABANDONED->value)
        ->whereNull('checkout_abandonments.email_sent_at')
        ->whereNull('customers.deleted_at')
        ->whereNotNull('customers.email')
        ->pluck('checkout_abandonments.id');
}
