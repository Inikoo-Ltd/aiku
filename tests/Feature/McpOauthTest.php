<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\SysAdmin\McpOauthAttempt\McpOauthAttemptOutcomeEnum;
use App\Models\SysAdmin\McpOauthAttempt;
use Laravel\Passport\ClientRepository;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user) = createShop();

    app()->instance('group', $this->organisation->group);
    setPermissionsTeamId($this->organisation->group->id);

    $this->user->update(['can_use_mcp' => true]);

    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('ChatGPT', ['https://chatgpt.com/connector/oauth/test']);

    $this->authorizeUrl = fn (string $state = 'abc') => '/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id'     => $this->client->id,
        'redirect_uri'  => 'https://chatgpt.com/connector/oauth/test',
        'scope'         => 'mcp:use',
        'state'         => $state,
    ]);
});

test('a staff member not logged in is sent to the login page, which says why, instead of a blank 401', function () {
    get(($this->authorizeUrl)())->assertRedirect(route('grp.login.show'));

    expect(McpOauthAttempt::where('client_id', $this->client->id)->latest('id')->first())
        ->outcome->toBe(McpOauthAttemptOutcomeEnum::LOGIN_REQUIRED)
        ->user_id->toBeNull();

    get(route('grp.login.show'))->assertInertia(fn ($page) => $page->where('connectingAssistant', 'ChatGPT'));
});

test('logging in on the inertia login page returns to the connect page with a full page load', function () {
    $this->user->update(['password' => 'connect-chatgpt-1']);
    $connectPage = 'https://app.aiku.test'.($this->authorizeUrl)();
    session()->put('url.intended', $connectPage);

    post(route('grp.login.store'), ['username' => $this->user->username, 'password' => 'connect-chatgpt-1'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', $connectPage);
});

test('the connect page opened twice still connects from the first copy', function () {
    actingAs($this->user);

    $firstCopy = get(($this->authorizeUrl)('first'))->assertOk()->viewData('authToken');
    get(($this->authorizeUrl)('second'))->assertOk();

    $response = post('/oauth/authorize', ['state' => '', 'client_id' => $this->client->id, 'auth_token' => $firstCopy]);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toStartWith('https://chatgpt.com/connector/oauth/test?code=')
        ->and($response->headers->get('Location'))->toContain('state=first');

    expect(McpOauthAttempt::where('client_id', $this->client->id)->where('user_id', $this->user->id)->latest('id')->first())
        ->outcome->toBe(McpOauthAttemptOutcomeEnum::CONNECTED);
});

test('a used or unknown connect page shows what to do instead of a 403', function () {
    actingAs($this->user);

    post('/oauth/authorize', ['state' => '', 'client_id' => $this->client->id, 'auth_token' => 'not-a-real-token'])
        ->assertForbidden()
        ->assertSee('This connect page has expired')
        ->assertSee('click Connect again');

    expect(McpOauthAttempt::where('client_id', $this->client->id)->latest('id')->first())
        ->outcome->toBe(McpOauthAttemptOutcomeEnum::FAILED);
});

test('the connect link opened without its details explains itself instead of showing JSON', function () {
    actingAs($this->user);

    get('/oauth/authorize')
        ->assertStatus(400)
        ->assertSee('This link is not complete')
        ->assertDontSee('invalid_request');
});

test('a user without AI assistant access is recorded as such', function () {
    $this->user->update(['can_use_mcp' => false]);
    actingAs($this->user);

    get(($this->authorizeUrl)())->assertOk();

    expect(McpOauthAttempt::where('client_id', $this->client->id)->latest('id')->first())
        ->outcome->toBe(McpOauthAttemptOutcomeEnum::NO_AI_ACCESS);
});

test('a user with 2FA must enter the code before connecting, whatever the method, and the link cannot carry the code', function () {
    $google2fa = new PragmaRX\Google2FAQRCode\Google2FA();
    $secret    = $google2fa->generateSecretKey();
    $this->user->update(['google2fa_secret' => $secret]);
    actingAs($this->user);

    get(($this->authorizeUrl)())->assertRedirect(route('grp.login.show2fa'));
    $this->call('HEAD', ($this->authorizeUrl)())->assertRedirect(route('grp.login.show2fa'));
    get(($this->authorizeUrl)().'&one_time_password='.$google2fa->getCurrentOtp($secret))->assertRedirect(route('grp.login.show2fa'));

    expect(McpOauthAttempt::where('client_id', $this->client->id)->latest('id')->first())
        ->outcome->toBe(McpOauthAttemptOutcomeEnum::TWO_FA_REQUIRED);

    $this->user->update(['google2fa_secret' => null]);
});
