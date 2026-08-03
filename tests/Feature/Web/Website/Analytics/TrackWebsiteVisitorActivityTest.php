<?php

namespace Tests\Feature\Web\Website\Analytics;

use App\Actions\Web\Website\Analytics\TrackWebsiteVisitorActivity;
use App\Events\Web\WebsiteVisitorCountUpdated;
use App\Events\Web\WebsiteVisitorHit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    loadDB();
    list(, , $this->shop) = createShop();
    $this->website = createWebsite($this->shop);
});

it('tracks guest visitor activity and broadcasts', function () {
    Event::fake();

    // We mock Redis to avoid connection issues and to verify calls
    Redis::shouldReceive('zrem')->twice();
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('hmset')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zremrangebyscore')->twice();
    Redis::shouldReceive('zcard')->twice()->andReturn(0, 1);

    TrackWebsiteVisitorActivity::run($this->website, 'session-guest', ['logged_in' => false, 'country' => 'UK']);

    Event::assertDispatched(WebsiteVisitorCountUpdated::class, function ($event) {
        return $event->website->id === $this->website->id
            && $event->loggedInCount === 0
            && $event->loggedOutCount === 1;
    });

    Event::assertDispatched(WebsiteVisitorHit::class, function ($event) {
        return $event->website->id === $this->website->id
            && $event->visitorData['session_id'] === 'session-guest'
            && $event->visitorData['country'] === 'UK';
    });
});

it('tracks logged in visitor activity and broadcasts', function () {
    Event::fake();

    Redis::shouldReceive('zrem')->twice();
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('hmset')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zremrangebyscore')->twice();
    Redis::shouldReceive('zcard')->twice()->andReturn(1, 0);

    TrackWebsiteVisitorActivity::run($this->website, 'session-user', ['logged_in' => true]);

    Event::assertDispatched(WebsiteVisitorCountUpdated::class, function ($event) {
        return $event->website->id === $this->website->id
            && $event->loggedInCount === 1
            && $event->loggedOutCount === 0;
    });
});

it('can get current counts from redis', function () {
    Redis::shouldReceive('zremrangebyscore')->twice();
    Redis::shouldReceive('zcard')->twice()->andReturn(5, 10);

    $counts = TrackWebsiteVisitorActivity::make()->getCounts($this->website);

    expect($counts)->toBe([
        'logged_in' => 5,
        'logged_out' => 10,
    ]);
});

it('can get active visitors from redis', function () {
    Redis::shouldReceive('zremrangebyscore')->twice();
    Redis::shouldReceive('zrange')->twice()->andReturn(['session-1'], ['session-2']);
    Redis::shouldReceive('hgetall')->twice()->andReturn(
        ['logged_in' => 'true', 'country' => 'UK'],
        ['logged_in' => 'false', 'country' => 'US']
    );

    $visitors = TrackWebsiteVisitorActivity::make()->getActiveVisitors($this->website);

    expect($visitors)->toHaveCount(2);
    expect($visitors[0]['session_id'])->toBe('session-1');
    expect($visitors[1]['session_id'])->toBe('session-2');
});
