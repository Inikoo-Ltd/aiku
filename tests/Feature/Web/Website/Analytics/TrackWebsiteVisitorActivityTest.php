<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Website\Analytics\TrackWebsiteVisitorActivity;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\Web\WebsiteVisitorCountUpdated;
use App\Events\Web\WebsiteVisitorHit;
use App\Models\Helpers\TaxCategory;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    loadDB();

    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->website  = createWebsite($this->shop);
    $this->customer = createCustomer($this->shop);
    $this->webUser  = createWebUser($this->customer);
    $this->tracker  = TrackWebsiteVisitorActivity::make();

    Redis::del(
        "website:{$this->website->id}:visitors:logged_in",
        "website:{$this->website->id}:visitors:logged_out",
        "website:{$this->website->id}:live:watched",
        "website:{$this->website->id}:live:paused",
    );

    Event::fake();
});

it('counts a guest and a logged in visitor separately', function () {
    $this->tracker->handle($this->website, 'session-guest', ['country' => 'GB']);
    $this->tracker->handle($this->website, 'session-user', ['web_user_id' => $this->webUser->id]);

    expect($this->tracker->getCounts($this->website))->toBe([
        'logged_in'  => 1,
        'logged_out' => 1,
    ]);
});

it('stores booleans as redis strings and drops nulls', function () {
    $this->tracker->handle($this->website, 'session-guest', [
        'country'    => 'GB',
        'city'       => 'Sheffield',
        'page_title' => null,
    ]);

    $visitors = $this->tracker->getActiveVisitors($this->website);

    expect($visitors)->toHaveCount(1)
        ->and($visitors[0]['session_id'])->toBe('session-guest')
        ->and($visitors[0]['logged_in'])->toBe('0')
        ->and($visitors[0]['city'])->toBe('Sheffield')
        ->and($visitors[0])->not->toHaveKey('page_title');
});

it('moves a session between the guest and logged in sets when it signs in', function () {
    $this->tracker->handle($this->website, 'session-a', ['country' => 'GB']);
    expect($this->tracker->getCounts($this->website))->toBe(['logged_in' => 0, 'logged_out' => 1]);

    $this->tracker->handle($this->website, 'session-a', ['web_user_id' => $this->webUser->id]);
    expect($this->tracker->getCounts($this->website))->toBe(['logged_in' => 1, 'logged_out' => 0]);
});

it('attaches the customer name and basket total to a logged in visitor', function () {
    Order::factory()->create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $this->customer->id,
        'slug'            => 'live-visitors-basket',
        'tax_category_id' => TaxCategory::first()->id,
        'currency_id'     => $this->shop->currency_id,
        'state'           => OrderStateEnum::CREATING,
        'total_amount'    => 906.30,
    ]);

    $this->tracker->handle($this->website, 'session-user', ['web_user_id' => $this->webUser->id]);

    $visitor = $this->tracker->getActiveVisitors($this->website)[0];

    expect($visitor['logged_in'])->toBe('1')
        ->and((float) $visitor['basket_amount'])->toBe(906.30)
        ->and($visitor['customer_name'])->not->toBeEmpty();
});

it('stays silent on soketi until somebody is watching the dashboard', function () {
    $this->tracker->handle($this->website, 'session-guest', ['country' => 'GB']);

    Event::assertNotDispatched(WebsiteVisitorCountUpdated::class);
    Event::assertNotDispatched(WebsiteVisitorHit::class);

    $this->tracker->markWatched($this->website);
    $this->tracker->handle($this->website, 'session-guest', ['country' => 'GB']);

    Event::assertDispatched(WebsiteVisitorCountUpdated::class);
    Event::assertDispatched(WebsiteVisitorHit::class, function ($event) {
        return $event->website->id === $this->website->id
            && $event->visitorData['session_id'] === 'session-guest'
            && $event->visitorData['country'] === 'GB';
    });
});

it('forgets visitors that fell outside the activity window', function () {
    $this->tracker->handle($this->website, 'session-stale', ['country' => 'GB']);

    Redis::zadd(
        "website:{$this->website->id}:visitors:logged_out",
        time() - TrackWebsiteVisitorActivity::WINDOW - 1,
        'session-stale'
    );

    expect($this->tracker->getCounts($this->website))->toBe(['logged_in' => 0, 'logged_out' => 0])
        ->and($this->tracker->getActiveVisitors($this->website))->toBe([]);
});

it('pauses tracking when a website is flooded with sessions', function () {
    $key = "website:{$this->website->id}:visitors:logged_out";

    // One under the ceiling: still tracking
    $flood = [];
    for ($i = 0; $i < TrackWebsiteVisitorActivity::MAX_ACTIVE - 1; $i++) {
        $flood["flood-$i"] = time();
    }
    Redis::zadd($key, ...collect($flood)->flatMap(fn ($score, $member) => [$score, $member])->all());

    $this->tracker->handle($this->website, 'real-visitor', ['country' => 'GB']);
    expect($this->tracker->isPaused($this->website))->toBeFalse();

    // The next session tips it over
    $this->tracker->handle($this->website, 'one-too-many', ['country' => 'GB']);
    expect($this->tracker->isPaused($this->website))->toBeTrue();
});

it('does no work at all while paused', function () {
    $this->tracker->pause($this->website);
    $this->tracker->markWatched($this->website);

    $this->tracker->handle($this->website, 'blocked-session', ['country' => 'GB']);

    expect($this->tracker->getActiveVisitors($this->website))->toBe([]);
    Event::assertNotDispatched(WebsiteVisitorHit::class);
});
