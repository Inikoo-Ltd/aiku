<?php

use App\Notifications\ForwardedChatSessionNotification;

test('staff mail notifications go out through ses from a verified sender in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $mail = (new ForwardedChatSessionNotification('Order question', 'Ana', '', 'Hi', null, null))->toMail(new stdClass());

    expect($mail->mailer)->toBe('ses')
        ->and($mail->from)->toBe(['help@aiku.io', 'Aiku Help']);
});

test('the rental agreement login email never contains a password', function () {
    $webUser = new App\Models\CRM\WebUser(['username' => 'jane']);
    $webUser->setRelation('shop', new App\Models\Catalogue\Shop(['name' => 'AW Fulfilment', 'email' => 'fulfilment@example.com']));
    $webUser->shop->setRelation('website', new App\Models\Web\Website(['domain' => 'fulfilment.test']));

    $mail = (new App\Notifications\SendEmailRentalAgreementCreated())->toMail($webUser);

    expect(implode(' ', $mail->introLines))->toContain('Username: jane')
        ->not->toContain('Password:')
        ->and($mail->actionUrl)->toBe('fulfilment.test/app/login');
});
