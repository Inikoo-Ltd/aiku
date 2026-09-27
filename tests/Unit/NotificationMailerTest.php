<?php

use App\Notifications\ForwardedChatSessionNotification;

test('staff mail notifications go out through ses from a verified sender in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $mail = (new ForwardedChatSessionNotification('Order question', 'Ana', '', 'Hi', null, null))->toMail(new stdClass());

    expect($mail->mailer)->toBe('ses')
        ->and($mail->from)->toBe(['help@aiku.io', 'Aiku Help']);
});
