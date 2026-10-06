<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Comms\Ses\SendSesEmail;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunCodeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailOngoingRun;
use App\Models\Comms\Outbox;

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

test('outside production a password reset goes to its real recipient only when the flag is on', function () {
    $passwordReset = (new DispatchedEmail())
        ->setRelation('outbox', new Outbox(['code' => OutboxCodeEnum::PASSWORD_REMINDER]));
    $orderConfirmation = (new DispatchedEmail())
        ->setRelation('outbox', new Outbox(['code' => OutboxCodeEnum::ORDER_CONFIRMATION]));
    $action = SendSesEmail::make();

    config(['app.send_password_reset_to_recipient_in_non_production_env' => false]);
    expect($action->isPasswordResetSentToRecipientOutsideProduction($passwordReset))->toBeFalse();

    config(['app.send_password_reset_to_recipient_in_non_production_env' => true]);
    expect($action->isPasswordResetSentToRecipientOutsideProduction($passwordReset))->toBeTrue()
        ->and($action->isPasswordResetSentToRecipientOutsideProduction($orderConfirmation))->toBeFalse();
});

test('outside production a password reset is sent from the shop address only when the flag is on', function () {
    $passwordReset = new EmailOngoingRun(['code' => EmailOngoingRunCodeEnum::PASSWORD_REMINDER]);
    $orderConfirmation = new EmailOngoingRun(['code' => EmailOngoingRunCodeEnum::ORDER_CONFIRMATION]);

    config(['app.send_password_reset_to_recipient_in_non_production_env' => false]);
    expect($passwordReset->isPasswordResetSentToRecipientOutsideProduction())->toBeFalse();

    config(['app.send_password_reset_to_recipient_in_non_production_env' => true]);
    expect($passwordReset->isPasswordResetSentToRecipientOutsideProduction())->toBeTrue()
        ->and($orderConfirmation->isPasswordResetSentToRecipientOutsideProduction())->toBeFalse();
});

test('outside production every email says loudly that it is a test email', function () {
    $action = SendSesEmail::make();
    $environment = strtoupper(app()->environment());

    expect($action->markSubjectAsNotProduction('Password Reset Request'))->toBe("⚠️ [$environment - TEST EMAIL] Password Reset Request")
        ->and($action->markHtmlBodyAsNotProduction('<html><body class="x"><p>Hi</p></body></html>'))
        ->toContain('<body class="x"><div style="background:#dc2626')
        ->toContain("TEST EMAIL FROM $environment")
        ->toContain(e(config('app.domain')))
        ->and($action->markHtmlBodyAsNotProduction('<p>Hi</p>'))->toStartWith('<div style="background:#dc2626');
});
