<?php

use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateDispatchedEmails;
use Lorisleiva\Actions\Decorators\JobDecorator;

test('keeps the outbox hydration timeout below the redis reservation window', function () {
    $action = OutboxHydrateDispatchedEmails::make();

    expect($action->jobTimeout)->toBeLessThan(config('queue.connections.redis.retry_after'))
        ->and($action->jobTries)->toBe(3)
        ->and($action->jobBackoff)->toBe([10, 30]);
});

test('applies retry settings to the queued action', function () {
    $job = OutboxHydrateDispatchedEmails::makeJob(1);

    expect($job)->toBeInstanceOf(JobDecorator::class)
        ->and($job->timeout)->toBe(120)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30]);
});
