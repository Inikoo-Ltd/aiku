<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Comms\Ses\SendSesEmail;

const SES_MAX_ATTEMPTS = 12;

const QUEUE_RETRY_AFTER_MICROSECONDS = 160 * 1000000;

test('each throttle waits longer than the last, until the cap', function () {
    $action = SendSesEmail::make();

    $waits = collect(range(1, SES_MAX_ATTEMPTS))
        ->map(fn ($attempt) => $action->throttleBackoffMicroseconds($attempt));

    expect($waits->first())->toBeGreaterThan(50000)
        ->and($waits[3])->toBeGreaterThan($waits[0])
        ->and($waits->max())->toBeLessThanOrEqual(2000000 + 50000);
});

test('a full retry run finishes well inside the queue retry_after', function () {
    $action = SendSesEmail::make();

    $worstCase = collect(range(1, SES_MAX_ATTEMPTS))
        ->sum(fn ($attempt) => $action->throttleBackoffMicroseconds($attempt));

    expect($worstCase)->toBeLessThan(QUEUE_RETRY_AFTER_MICROSECONDS / 2);
});

test('the backoff is slow enough to let the ses rate bucket refill', function () {
    $action = SendSesEmail::make();

    $firstThreeWaits = collect(range(1, 3))
        ->sum(fn ($attempt) => $action->throttleBackoffMicroseconds($attempt));

    expect($firstThreeWaits)->toBeGreaterThan(500000);
});
