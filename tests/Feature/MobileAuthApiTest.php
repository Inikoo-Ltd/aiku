<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Models\SysAdmin\User;
use App\Notifications\MobilePasswordResetCodeNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FAQRCode\Google2FA;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';

function mobileLoginBody(User $user, array $extra = []): array
{
    return array_merge([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ], $extra);
}

function postMobileAuth(string $routeName, array $body, ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];
    if ($token !== null) {
        $headers['Authorization'] = 'Bearer '.$token;
    }

    return postJson(route($routeName), $body, $headers);
}

function lastMobilePasswordResetCode(User $user): string
{
    return Notification::sent($user, MobilePasswordResetCodeNotification::class)->last()->code;
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group = createGroup();
    config([
        'app.mobile_app_keys'   => ['mobile-test-key'],
        'app.mobile_two_factor' => true,
    ]);
    Bus::withoutDispatchingAfterResponses();
});

test('mobile login limits tries per username, so colleagues on the same network can still sign in', function () {
    $blocked   = createMobileAppUser($this->group);
    $colleague = createMobileAppUser($this->group);

    foreach (range(1, 6) as $attempt) {
        postMobileLogin(['username' => $blocked->username, 'password' => 'wrong-password', 'device_name' => 'Pixel 8'])->assertUnprocessable();
    }

    postMobileLogin(['username' => $blocked->username, 'password' => 'wrong-password', 'device_name' => 'Pixel 8'])->assertTooManyRequests();
    postMobileLogin(['username' => $colleague->username, 'password' => 'mobile-app-password', 'device_name' => 'Pixel 8'])->assertOk();
});

test('mobile login with two factor on asks an account with a secret for its code, refuses a wrong one and accepts the right one', function () {
    $google2fa = new Google2FA();
    $secret    = $google2fa->generateSecretKey();
    $user      = createMobileAppUser($this->group, ['google2fa_secret' => $secret]);

    postMobileLogin(mobileLoginBody($user))->assertUnprocessable()
        ->assertJsonPath('code', 'otp_required')
        ->assertJsonValidationErrors(['one_time_password' => 'Enter OTP from your Authenticator App']);

    postMobileLogin(mobileLoginBody($user, ['one_time_password' => '000000' === $google2fa->getCurrentOtp($secret) ? '111111' : '000000']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['one_time_password' => 'Invalid OTP is given']);

    postMobileLogin(mobileLoginBody($user, ['one_time_password' => $google2fa->getCurrentOtp($secret)]))->assertOk()->assertJsonStructure(['token', 'expires_at', 'user']);
});

test('mobile login with two factor off asks no code, even from an account that must use two factor', function () {
    config(['app.mobile_two_factor' => false]);
    $withSecret  = createMobileAppUser($this->group, ['google2fa_secret' => (new Google2FA())->generateSecretKey()]);
    $mustSetItUp = createMobileAppUser($this->group, ['is_two_factor_required' => true]);

    postMobileLogin(mobileLoginBody($withSecret))->assertOk()->assertJsonStructure(['token']);
    postMobileLogin(mobileLoginBody($mustSetItUp))->assertOk()->assertJsonStructure(['token']);

    expect($mustSetItUp->refresh()->google2fa_secret)->toBeNull();
});

test('mobile login hands a user who must use two factor a setup block, then saves the secret and signs them in', function () {
    $user = createMobileAppUser($this->group, ['is_two_factor_required' => true]);

    $setup = postMobileLogin(mobileLoginBody($user))->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_setup_required')
        ->assertJsonPath('message', 'Set up two-factor authentication to continue.')
        ->assertJsonValidationErrors(['one_time_password' => 'Set up two-factor authentication to continue.'])
        ->assertJsonPath('two_factor_setup.issuer', 'aiku')
        ->assertJsonPath('two_factor_setup.account', $user->username)
        ->assertJsonPath('two_factor_setup.expires_in', 600)
        ->json('two_factor_setup');

    expect($setup['secret'])->toHaveLength(32)
        ->and($setup['otpauth_url'])->toStartWith('otpauth://totp/aiku:')
        ->and($setup['otpauth_url'])->toContain('secret='.$setup['secret'])
        ->and($setup['otpauth_url'])->toContain('issuer=aiku')
        ->and($setup['setup_token'])->toBeString()->not->toContain($setup['secret']);

    postMobileLogin(mobileLoginBody($user, [
        'two_factor_setup_token' => $setup['setup_token'],
        'one_time_password'      => (new Google2FA())->getCurrentOtp($setup['secret']),
    ]))->assertOk()->assertJsonStructure(['token', 'expires_at', 'user']);

    expect($user->refresh()->google2fa_secret)->toBe($setup['secret']);

    postMobileLogin(mobileLoginBody($user))->assertUnprocessable()->assertJsonPath('code', 'otp_required');
});

test('mobile two factor setup refuses a missing or wrong code and keeps the setup token usable', function () {
    $user  = createMobileAppUser($this->group, ['is_two_factor_required' => true]);
    $setup = postMobileLogin(mobileLoginBody($user))->json('two_factor_setup');
    $right = (new Google2FA())->getCurrentOtp($setup['secret']);

    postMobileLogin(mobileLoginBody($user, ['two_factor_setup_token' => $setup['setup_token']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['one_time_password' => 'Enter OTP from your Authenticator App']);

    postMobileLogin(mobileLoginBody($user, [
        'two_factor_setup_token' => $setup['setup_token'],
        'one_time_password'      => $right === '000000' ? '111111' : '000000',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['one_time_password' => 'Invalid OTP is given']);

    expect($user->refresh()->google2fa_secret)->toBeNull();

    postMobileLogin(mobileLoginBody($user, [
        'two_factor_setup_token' => $setup['setup_token'],
        'one_time_password'      => $right,
    ]))->assertOk();

    expect($user->refresh()->google2fa_secret)->toBe($setup['secret']);
});

test('mobile two factor setup locks after five wrong codes like the code check', function () {
    $user  = createMobileAppUser($this->group, ['is_two_factor_required' => true]);
    $setup = postMobileLogin(mobileLoginBody($user))->json('two_factor_setup');
    $wrong = (new Google2FA())->getCurrentOtp($setup['secret']) === '000000' ? '111111' : '000000';
    $this->travel(61)->seconds();
    $body = mobileLoginBody($user, ['two_factor_setup_token' => $setup['setup_token'], 'one_time_password' => $wrong]);

    foreach (range(1, 5) as $_) {
        postMobileLogin($body)->assertUnprocessable()->assertJsonValidationErrors(['one_time_password' => 'Invalid OTP is given']);
    }

    postMobileLogin($body)->assertUnprocessable()->assertJsonValidationErrors(['one_time_password' => 'Too many wrong codes']);
});

test('mobile two factor setup answers an expired, forged or another user\'s token with a fresh setup block', function () {
    $user      = createMobileAppUser($this->group, ['is_two_factor_required' => true]);
    $otherUser = createMobileAppUser($this->group, ['is_two_factor_required' => true]);
    $google2fa = new Google2FA();

    $setup      = postMobileLogin(mobileLoginBody($user))->json('two_factor_setup');
    $otherSetup = postMobileLogin(mobileLoginBody($otherUser))->json('two_factor_setup');

    $forged = postMobileLogin(mobileLoginBody($user, [
        'two_factor_setup_token' => Str::random(120),
        'one_time_password'      => $google2fa->getCurrentOtp($setup['secret']),
    ]))->assertUnprocessable()->assertJsonPath('code', 'two_factor_setup_required');

    $borrowed = postMobileLogin(mobileLoginBody($user, [
        'two_factor_setup_token' => $otherSetup['setup_token'],
        'one_time_password'      => $google2fa->getCurrentOtp($otherSetup['secret']),
    ]))->assertUnprocessable()->assertJsonPath('code', 'two_factor_setup_required');

    $this->travel(11)->minutes();
    $expired = postMobileLogin(mobileLoginBody($user, [
        'two_factor_setup_token' => $setup['setup_token'],
        'one_time_password'      => $google2fa->getCurrentOtp($setup['secret']),
    ]))->assertUnprocessable()->assertJsonPath('code', 'two_factor_setup_required');

    foreach ([$forged, $borrowed, $expired] as $response) {
        expect($response->json('two_factor_setup.setup_token'))->not->toBe($setup['setup_token'])
            ->and($response->json('two_factor_setup.secret'))->not->toBe($setup['secret'])
            ->and($response->json('two_factor_setup.account'))->toBe($user->username);
    }

    expect($user->refresh()->google2fa_secret)->toBeNull()
        ->and($otherUser->refresh()->google2fa_secret)->toBeNull();
});

test('mobile password reset code answers the same for an unknown, inactive or emailless login and sends nothing', function () {
    Notification::fake();
    $inactive  = createMobileAppUser($this->group, ['status' => false]);
    $emailless = createMobileAppUser($this->group, ['email' => null]);

    foreach (['nobody-'.uniqid(), 'nobody-'.uniqid().'@example.com', $inactive->username, $inactive->email, $emailless->username] as $login) {
        postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $login])->assertOk()->assertExactJson([
            'data'    => ['sent' => true],
            'message' => 'If an account matches, we have emailed a 6-digit code to its email address.',
        ]);
    }

    Notification::assertNothingSent();
});

test('mobile password reset code is emailed for a username and for an email address', function () {
    Notification::fake();
    $user = createMobileAppUser($this->group, ['email' => 'mobile-reset-'.uniqid().'@example.com']);

    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->username])->assertOk()->assertJsonPath('data.sent', true);
    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => Str::upper($user->email)])->assertOk()->assertJsonPath('data.sent', true);

    Notification::assertSentToTimes($user, MobilePasswordResetCodeNotification::class, 2);
    Notification::assertSentTo($user, MobilePasswordResetCodeNotification::class, function (MobilePasswordResetCodeNotification $notification) use ($user) {
        $mail = $notification->toMail($user);

        return preg_match('/^\d{6}$/', $notification->code)
            && $mail->subject === 'Your Aiku password reset code'
            && in_array('Your code is '.$notification->code, $mail->introLines);
    });
});

test('mobile password reset code needs the app key and a login, and is throttled per ip and login', function () {
    Notification::fake();
    $user = createMobileAppUser($this->group);

    postJson(route('grp.api.mobile.password.reset_code'), ['login' => 'nobody-'.uniqid()])->assertNotFound();
    postMobileAuth('grp.api.mobile.password.reset_code', [])->assertUnprocessable()->assertJsonValidationErrors('login');

    foreach (range(1, 6) as $_) {
        postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->username])->assertOk();
    }

    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->username])->assertTooManyRequests();
    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->email])->assertOk();
});

test('mobile password reset refuses a wrong code, an unknown login and an unconfirmed password', function () {
    Notification::fake();
    $user = createMobileAppUser($this->group);
    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->username]);
    $code  = lastMobilePasswordResetCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    postMobileAuth('grp.api.mobile.password.reset', ['login' => $user->username, 'code' => $wrong, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

    postMobileAuth('grp.api.mobile.password.reset', ['login' => 'nobody-'.uniqid(), 'code' => $code, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

    postMobileAuth('grp.api.mobile.password.reset', ['login' => $user->username, 'code' => $code, 'password' => 'new-secret-1', 'password_confirmation' => 'other-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(Hash::check('mobile-app-password', $user->refresh()->password))->toBeTrue();
});

test('mobile password reset code is void after five wrong tries', function () {
    Notification::fake();
    $user = createMobileAppUser($this->group);
    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->username]);
    $code = lastMobilePasswordResetCode($user);
    $body = ['login' => $user->username, 'code' => $code === '000000' ? '111111' : '000000', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'];

    foreach (range(1, 4) as $_) {
        postMobileAuth('grp.api.mobile.password.reset', $body)->assertUnprocessable()->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);
    }

    postMobileAuth('grp.api.mobile.password.reset', $body)->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Too many wrong codes. Ask for a new code.']);
    postMobileAuth('grp.api.mobile.password.reset', [...$body, 'code' => $code])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Too many wrong codes. Ask for a new code.']);

    expect(Hash::check('mobile-app-password', $user->refresh()->password))->toBeTrue();
});

test('mobile password reset changes the password, signs out every phone, and the code works once', function () {
    Notification::fake();
    $user = createMobileAppUser($this->group);
    $user->update(['reset_password' => true]);
    $firstPhone  = $user->createToken('mobile|Pixel 8', ['mobile'])->accessToken;
    $secondPhone = $user->createToken('mobile|Galaxy S24', ['mobile'])->accessToken;
    $apiToken    = $user->createToken('reporting', ['read'])->accessToken;

    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->username]);
    $replacedCode = lastMobilePasswordResetCode($user);
    postMobileAuth('grp.api.mobile.password.reset_code', ['login' => $user->email]);
    $code = lastMobilePasswordResetCode($user);
    $body = ['login' => $user->username, 'code' => $code, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'];

    if ($replacedCode !== $code) {
        postMobileAuth('grp.api.mobile.password.reset', [...$body, 'code' => $replacedCode])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    postMobileAuth('grp.api.mobile.password.reset', $body)->assertOk()->assertExactJson(['data' => ['reset' => true]]);

    $user->refresh();
    expect(Hash::check('new-secret-1', $user->password))->toBeTrue()
        ->and($user->reset_password)->toBeFalse()
        ->and(PersonalAccessToken::find($firstPhone->id))->toBeNull()
        ->and(PersonalAccessToken::find($secondPhone->id))->toBeNull()
        ->and(PersonalAccessToken::find($apiToken->id))->not->toBeNull();

    postMobileAuth('grp.api.mobile.password.reset', [...$body, 'password' => 'newer-secret-2', 'password_confirmation' => 'newer-secret-2'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

    postMobileLogin(mobileLoginBody($user, ['password' => 'new-secret-1']))->assertOk();
});

test('mobile change password refuses a wrong current password and needs a mobile token', function () {
    $user  = createMobileAppUser($this->group);
    $token = $user->createToken('mobile|Pixel 8', ['mobile'])->plainTextToken;
    $body  = ['current_password' => 'not-my-password', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'];

    postMobileAuth('grp.api.mobile.password.update', $body, $token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password' => 'The current password is incorrect.']);

    postMobileAuth('grp.api.mobile.password.update', [...$body, 'current_password' => 'mobile-app-password'])->assertUnauthorized();

    expect(Hash::check('mobile-app-password', $user->refresh()->password))->toBeTrue();
});

test('mobile change password stops after six tries a minute for the same user', function () {
    $user  = createMobileAppUser($this->group);
    $token = $user->createToken('mobile|Pixel 8', ['mobile'])->plainTextToken;
    $body  = ['current_password' => 'not-my-password', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'];

    foreach (range(1, 6) as $attempt) {
        postMobileAuth('grp.api.mobile.password.update', $body, $token)->assertUnprocessable();
    }

    postMobileAuth('grp.api.mobile.password.update', $body, $token)->assertTooManyRequests();
});

test('mobile change password keeps this phone signed in and signs out the others', function () {
    $user       = createMobileAppUser($this->group);
    $thisPhone  = $user->createToken('mobile|Pixel 8', ['mobile']);
    $otherPhone = $user->createToken('mobile|Galaxy S24', ['mobile'])->accessToken;
    $apiToken   = $user->createToken('reporting', ['read'])->accessToken;

    postMobileAuth('grp.api.mobile.password.update', [
        'current_password'      => 'mobile-app-password',
        'password'              => 'new-secret-1',
        'password_confirmation' => 'new-secret-1',
    ], $thisPhone->plainTextToken)->assertOk()->assertExactJson(['data' => ['changed' => true]]);

    expect(Hash::check('new-secret-1', $user->refresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::find($thisPhone->accessToken->id))->not->toBeNull()
        ->and(PersonalAccessToken::find($otherPhone->id))->toBeNull()
        ->and(PersonalAccessToken::find($apiToken->id))->not->toBeNull();

    app('auth')->forgetGuards();
    getJson(route('grp.api.mobile.permissions'), [
        'X-Aiku-App-Key' => 'mobile-test-key',
        'Authorization'  => 'Bearer '.$thisPhone->plainTextToken,
    ])->assertOk();
});

test('mobile change password follows the web password rule', function () {
    $user  = createMobileAppUser($this->group);
    $token = $user->createToken('mobile|Pixel 8', ['mobile'])->plainTextToken;

    postMobileAuth('grp.api.mobile.password.update', [
        'current_password'      => 'mobile-app-password',
        'password'              => 'new-secret-1',
        'password_confirmation' => 'other-secret',
    ], $token)->assertUnprocessable()->assertJsonValidationErrors('password');

    app()->detectEnvironment(fn () => 'production');
    try {
        postMobileAuth('grp.api.mobile.password.update', [
            'current_password'      => 'mobile-app-password',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ], $token)->assertUnprocessable()->assertJsonValidationErrors('password');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(Hash::check('mobile-app-password', $user->refresh()->password))->toBeTrue();
});
