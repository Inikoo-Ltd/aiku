<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\LinkSlackUsers;
use App\Actions\Chat\Staff\Slack\StoreSlackUser;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteClient;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteException;
use App\Actions\Chat\Staff\Slack\Write\StartSlackConnection;
use App\Actions\Chat\Staff\Slack\Write\StartSlackPrivateConnection;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Lorisleiva\Actions\ActionRequest;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        list($this->organisation, $this->user) = createShop();
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
    }
    $this->group = $this->organisation->group;
    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    Config::set('services.slack.client_id', 'client-id-test');
    Config::set('services.slack.client_secret', 'client-secret-test');
    Config::set('services.slack.team_id', 'TOURTEAM');
    Http::preventStrayRequests();

    $this->token = 'xoxp-test-'.Str::random(24);

    $this->makeUser = function (?string $role = 'help-desk-clerk', bool $linked = true): User {
        $user = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
        if ($role) {
            $user->assignRole($role);
        }
        if ($linked) {
            SlackUser::create(['slack_id' => 'UW'.Str::upper(Str::random(9)), 'name' => 'person.'.$user->id, 'display_name' => 'Person '.$user->id, 'email' => $user->email, 'user_id' => $user->id]);
        }

        return $user;
    };

    $this->slackIdOf = fn (User $user): string => SlackUser::where('user_id', $user->id)->value('slack_id');

    $this->connect = function (User $user, array $overrides = []): SlackConnection {
        return SlackConnection::create(array_merge([
            'user_id'       => $user->id,
            'slack_user_id' => ($this->slackIdOf)($user),
            'team_id'       => 'TOURTEAM',
            'token'         => $this->token,
            'scopes'        => implode(',', SlackWriteAccess::USER_SCOPES),
            'connected_at'  => now(),
        ], $overrides));
    };

    $this->fakeSlack = function (User $user, array $authedUser = [], string $teamId = 'TOURTEAM'): void {
        Http::fake([
            'slack.com/api/oauth.v2.access' => Http::response([
                'ok'          => true,
                'team'        => ['id' => $teamId],
                'authed_user' => array_merge(['id' => ($this->slackIdOf)($user), 'scope' => implode(',', SlackWriteAccess::USER_SCOPES), 'access_token' => $this->token, 'token_type' => 'user'], $authedUser),
            ]),
            'slack.com/api/auth.revoke' => Http::response(['ok' => true, 'revoked' => true]),
        ]);
    };

    $this->startAndGetState = function (User $user): string {
        $response = actingAs($user)->get(route('grp.chat.staff.slack.connect.start'));
        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        expect($response->headers->get('Location'))->toStartWith('https://slack.com/oauth/v2/authorize?')
            ->and($query['user_scope'])->toBe(implode(',', SlackWriteAccess::USER_SCOPES))
            ->and($query)->not->toHaveKey('scope')
            ->and($query['client_id'])->toBe('client-id-test')
            ->and($query['redirect_uri'])->toBe(StartSlackConnection::redirectUri())
            ->and(parse_url($query['redirect_uri'], PHP_URL_HOST))->toBe(parse_url(route('grp.profile.edit'), PHP_URL_HOST))
            ->and(parse_url($query['redirect_uri'], PHP_URL_PATH))->toBe('/vx/slack-connect/callback')
            ->and($query['state'])->toBe(session(StartSlackConnection::STATE_KEY)['value']);

        return $query['state'];
    };
});

test('connecting stores the Slack token encrypted and never shows it, and a replayed state stores nothing more', function () {
    $user = ($this->makeUser)();
    ($this->fakeSlack)($user);
    $state = ($this->startAndGetState)($user);

    $callback = actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-1', 'state' => $state]));
    $callback->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::NOTICE_KEY)->assertSessionMissing(StartSlackConnection::STATE_KEY);
    expect($callback->getContent())->not->toContain($this->token);

    $connection = SlackConnection::where('user_id', $user->id)->first();
    $stored     = DB::table('slack_connections')->where('user_id', $user->id)->value('token');
    expect($connection->token)->toBe($this->token)
        ->and($stored)->not->toContain($this->token)
        ->and($connection->slack_user_id)->toBe(($this->slackIdOf)($user))
        ->and($connection->team_id)->toBe('TOURTEAM')
        ->and($connection->scopes)->toBe(implode(',', SlackWriteAccess::USER_SCOPES))
        ->and($connection->isLive())->toBeTrue()
        ->and($connection->toArray())->not->toHaveKey('token')
        ->and($connection->toJson())->not->toContain($this->token)
        ->and(SlackWriteAccess::reasonUnavailable($user))->toBeNull()
        ->and(SlackWriteClient::for($user))->toBeInstanceOf(SlackWriteClient::class);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://slack.com/api/oauth.v2.access'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id-test:client-secret-test'))
        && $request['code'] === 'code-1'
        && $request['redirect_uri'] === StartSlackConnection::redirectUri());

    $page = actingAs($user)->get(route('grp.profile.edit'));
    $page->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('formData.blueprint', function ($blueprint) {
            $field = collect($blueprint)->pluck('fields')->map(fn ($fields) => $fields['slack_connection'] ?? null)->filter()->first();

            return $field['connection']['state'] === 'connected' && str_starts_with($field['connection']['slack_name'], 'Person ');
        }));
    expect($page->getContent())->not->toContain($this->token)->not->toContain($stored);

    $replay = actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-2', 'state' => $state]));
    $replay->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY);
    Http::assertSentCount(1);
});

test('a callback that cannot be trusted stores nothing and explains itself on the settings screen', function (string $case) {
    $user  = ($this->makeUser)();
    $other = ($this->makeUser)();
    match ($case) {
        'wrong team'   => ($this->fakeSlack)($user, [], 'TOTHERTEAM'),
        'wrong person' => ($this->fakeSlack)($user, ['id' => ($this->slackIdOf)($other)]),
        'rotating'     => ($this->fakeSlack)($user, ['refresh_token' => 'xoxe-1-refresh', 'expires_in' => 43200]),
        'fewer scopes' => ($this->fakeSlack)($user, ['scope' => 'chat:write']),
        'refused'      => Http::fake(['slack.com/api/oauth.v2.access' => Http::response(['ok' => false, 'error' => 'invalid_code'])]),
        default        => ($this->fakeSlack)($user),
    };
    $state = ($this->startAndGetState)($user);

    $query = match ($case) {
        'bad state' => ['code' => 'code-1', 'state' => 'not-'.$state],
        'no state'  => ['code' => 'code-1'],
        'denied'    => ['error' => 'access_denied', 'state' => $state],
        default     => ['code' => 'code-1', 'state' => $state],
    };
    $response = actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', $query));

    $response->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY)->assertSessionMissing(StartSlackConnection::STATE_KEY);
    expect(SlackConnection::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(SlackConnection::where('user_id', $other->id)->exists())->toBeFalse()
        ->and(session(StartSlackConnection::ERROR_KEY))->not->toContain($this->token);

    if (in_array($case, ['bad state', 'no state', 'denied'])) {
        Http::assertNothingSent();
    }
    if (in_array($case, ['wrong team', 'wrong person', 'rotating', 'fewer scopes'])) {
        Http::assertSent(fn (Request $request) => $request->url() === 'https://slack.com/api/auth.revoke' && $request->hasHeader('Authorization', 'Bearer '.$this->token));
    }

    actingAs($user)->get(route('grp.profile.edit'))->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('fields')->contains(fn ($fields) => ($fields['slack_connection']['connection']['state'] ?? null) === 'none')));
})->with(['bad state', 'no state', 'wrong team', 'wrong person', 'denied', 'rotating', 'fewer scopes', 'refused']);

test('a state made for one person is refused for another, and an expired one too', function () {
    $user  = ($this->makeUser)();
    $other = ($this->makeUser)();
    ($this->fakeSlack)($other);
    $state = ($this->startAndGetState)($user);

    actingAs($other)->withSession([StartSlackConnection::STATE_KEY => session(StartSlackConnection::STATE_KEY)])
        ->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-1', 'state' => $state]))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY);

    actingAs($other)->withSession([StartSlackConnection::STATE_KEY => ['value' => 'expired-state', 'user_id' => $other->id, 'expires_at' => now()->subMinute()->timestamp]])
        ->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-1', 'state' => 'expired-state']))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY);

    expect(SlackConnection::whereIn('user_id', [$user->id, $other->id])->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('any active staff member linked to a Slack person can connect, one who is not linked cannot', function () {
    $staff = ($this->makeUser)(null);
    expect(SlackWriteAccess::allows($staff))->toBeTrue();
    actingAs($staff)->get(route('grp.profile.edit'))->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('fields')->contains(fn ($fields) => isset($fields['slack_connection']))));

    $unlinkedStaff = ($this->makeUser)(null, false);
    actingAs($unlinkedStaff)->get(route('grp.chat.staff.slack.connect.start'))->assertForbidden();
    actingAs($unlinkedStaff)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'c', 'state' => 's']))->assertForbidden();
    actingAs($unlinkedStaff)->get(route('grp.profile.edit'))->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('formData.blueprint', fn ($blueprint) => !collect($blueprint)->pluck('fields')->contains(fn ($fields) => isset($fields['slack_connection']))));
    expect(SlackWriteAccess::allows($unlinkedStaff))->toBeFalse()
        ->and(SlackWriteAccess::reasonUnavailable($unlinkedStaff))->toBeString();

    $unlinked = ($this->makeUser)('help-desk-clerk', false);
    actingAs($unlinked)->get(route('grp.chat.staff.slack.connect.start'))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY);

    Config::set('services.slack.client_secret', null);
    actingAs(($this->makeUser)())->get(route('grp.chat.staff.slack.connect.start'))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY);
    Http::assertNothingSent();
});

test('the write client exists only for a linked person with a live connection on a machine allowed to write', function () {
    $engineer = ($this->makeUser)();
    expect(SlackWriteClient::for($engineer))->toBeNull()
        ->and(SlackWriteAccess::reasonUnavailable($engineer))->toContain('Connect');

    $connection = ($this->connect)($engineer);
    expect(SlackWriteClient::for($engineer))->toBeInstanceOf(SlackWriteClient::class)
        ->and(SlackWriteAccess::reasonUnavailable($engineer))->toBeNull();

    $staff = ($this->makeUser)(null);
    ($this->connect)($staff);
    expect(SlackWriteClient::for($staff))->toBeInstanceOf(SlackWriteClient::class);

    $environment = app()->environment();
    app()->instance('env', 'local');
    try {
        expect(SlackWriteClient::for($engineer))->toBeNull()
            ->and(SlackWriteAccess::reasonUnavailable($engineer))->toContain('switched off');
    } finally {
        app()->instance('env', $environment);
    }

    $connection->update(['needs_reconnect' => true]);
    expect(SlackWriteClient::for($engineer))->toBeNull()
        ->and(SlackWriteAccess::reasonUnavailable($engineer))->toContain('Reconnect');

    $connection->update(['needs_reconnect' => false, 'revoked_at' => now()]);
    expect(SlackWriteClient::for($engineer))->toBeNull();

    $connection->update(['revoked_at' => null, 'scopes' => 'chat:write']);
    expect(SlackWriteClient::for($engineer))->toBeNull()
        ->and(SlackWriteAccess::reasonUnavailable($engineer))->toContain('Reconnect')
        ->and($connection->refresh()->needs_reconnect)->toBeFalse();
    actingAs($engineer)->get(route('grp.profile.edit'))->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('fields')->contains(fn ($fields) => ($fields['slack_connection']['connection']['state'] ?? null) === 'reconnect')));

    $connection->update(['scopes' => implode(',', SlackWriteAccess::USER_SCOPES)]);
    DB::table('slack_connections')->where('id', $connection->id)->update(['token' => 'encrypted-with-another-key']);
    expect(SlackWriteClient::for($engineer))->toBeNull()
        ->and($connection->refresh()->needs_reconnect)->toBeTrue()
        ->and(SlackWriteAccess::reasonUnavailable($engineer))->toContain('Reconnect');
    DB::table('slack_connections')->where('id', $connection->id)->update(['token' => encrypt($this->token, false), 'needs_reconnect' => false]);
    expect(SlackWriteClient::for($engineer))->toBeInstanceOf(SlackWriteClient::class);

    $connection->update(['revoked_at' => null]);
    $engineer->update(['status' => false]);
    expect(SlackWriteClient::for($engineer->refresh()))->toBeNull();
});

test('the write client posts as the person, gives up at once on a rate limit and flags a refused token for reconnection', function () {
    $engineer   = ($this->makeUser)();
    $connection = ($this->connect)($engineer);

    Http::fake(['slack.com/api/pins.add' => Http::response(['ok' => false, 'error' => 'ratelimited'], 429, ['Retry-After' => '120'])]);
    expect(fn () => SlackWriteClient::for($engineer)->call('pins.add', ['channel' => 'C1']))
        ->toThrow(fn (SlackWriteException $exception) => expect($exception->slackError)->toBe('ratelimited'))
        ->and($connection->refresh()->needs_reconnect)->toBeFalse();
    Http::assertSentCount(1);

    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1790000000.000100'])]);
    $answer = SlackWriteClient::for($engineer)->call('chat.postMessage', ['channel' => 'C1', 'text' => 'hello', 'blocks' => [['type' => 'section']], 'unfurl_links' => false, 'thread_ts' => null]);
    expect($answer['ts'])->toBe('1790000000.000100');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://slack.com/api/chat.postMessage'
        && $request->hasHeader('Authorization', 'Bearer '.$this->token)
        && $request['text'] === 'hello'
        && $request['blocks'] === '[{"type":"section"}]'
        && $request['unfurl_links'] === 'false'
        && !isset($request['thread_ts'])
        && !str_contains($request->body(), $this->token));

    Http::fake(['slack.com/api/chat.update' => Http::response(['ok' => false, 'error' => 'cant_update_message'])]);
    expect(fn () => SlackWriteClient::for($engineer)->call('chat.update', ['channel' => 'C1', 'ts' => '1.1', 'text' => 'x']))
        ->toThrow(fn (SlackWriteException $exception) => expect($exception->slackError)->toBe('cant_update_message'))
        ->and($connection->refresh()->needs_reconnect)->toBeFalse();

    Http::fake(['slack.com/api/reactions.add' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);
    $client = SlackWriteClient::for($engineer);
    try {
        $client->call('reactions.add', ['channel' => 'C1', 'timestamp' => '1.1', 'name' => 'thumbsup']);
        $this->fail('The refused token did not throw');
    } catch (SlackWriteException $exception) {
        expect($exception->slackError)->toBe('invalid_auth')
            ->and($exception->getMessage())->not->toContain($this->token);
    }
    expect($connection->refresh()->needs_reconnect)->toBeTrue()
        ->and(SlackWriteClient::for($engineer))->toBeNull();
});

test('disconnecting withdraws the permission at Slack and clears the token, even when Slack cannot be reached', function () {
    $engineer   = ($this->makeUser)();
    $connection = ($this->connect)($engineer);
    Http::fake(['slack.com/api/auth.revoke' => Http::response(['ok' => true, 'revoked' => true])]);

    actingAs($engineer)->delete(route('grp.chat.staff.slack.connect.delete'))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::NOTICE_KEY);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://slack.com/api/auth.revoke'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer '.$this->token)
        && !str_contains($request->body(), $this->token));
    expect(DB::table('slack_connections')->where('id', $connection->id)->value('token'))->toBeNull()
        ->and($connection->refresh()->revoked_at)->not->toBeNull()
        ->and(SlackWriteClient::for($engineer))->toBeNull();

    $second           = ($this->makeUser)();
    $secondConnection = ($this->connect)($second);
    Http::fake(['slack.com/api/auth.revoke' => fn () => throw new Illuminate\Http\Client\ConnectionException('down')]);
    actingAs($second)->delete(route('grp.chat.staff.slack.connect.delete'))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))
        ->assertSessionHas(StartSlackConnection::NOTICE_KEY, fn (string $notice) => str_contains($notice, 'could not be reached'));
    expect(DB::table('slack_connections')->where('id', $secondConnection->id)->value('token'))->toBeNull();

    $third           = ($this->makeUser)();
    $thirdConnection = ($this->connect)($third);
    DB::table('slack_connections')->where('id', $thirdConnection->id)->update(['token' => 'not-encrypted-with-this-key']);
    actingAs($third)->delete(route('grp.chat.staff.slack.connect.delete'))->assertRedirect(route('grp.profile.edit', ['section' => 'slack']));
    expect(DB::table('slack_connections')->where('id', $thirdConnection->id)->value('token'))->toBeNull();
});

test('deactivating a user withdraws their Slack permission', function () {
    $engineer   = ($this->makeUser)();
    $connection = ($this->connect)($engineer);
    Http::fake(['slack.com/api/auth.revoke' => Http::response(['ok' => true, 'revoked' => true])]);

    UpdateUser::make()->action($engineer, ['status' => false]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://slack.com/api/auth.revoke');
    expect(DB::table('slack_connections')->where('id', $connection->id)->value('token'))->toBeNull();
});

test('a machine that is not production never connects, and disconnects only locally', function () {
    $engineer   = ($this->makeUser)();
    $connected  = ($this->makeUser)();
    $connection = ($this->connect)($connected);
    ($this->fakeSlack)($engineer);
    $environment = app()->environment();

    foreach (['local', 'staging'] as $machine) {
        app()->instance('env', $machine);
        try {
            actingAs($engineer)->get(route('grp.chat.staff.slack.connect.start'))
                ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))
                ->assertSessionHas(StartSlackConnection::ERROR_KEY)->assertSessionMissing(StartSlackConnection::STATE_KEY);

            actingAs($engineer)->withSession([StartSlackConnection::STATE_KEY => ['value' => 'state-1', 'user_id' => $engineer->id, 'expires_at' => now()->addMinutes(5)->timestamp]])
                ->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-1', 'state' => 'state-1']))
                ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))->assertSessionHas(StartSlackConnection::ERROR_KEY);
            expect(SlackConnection::where('user_id', $engineer->id)->exists())->toBeFalse();
        } finally {
            app()->instance('env', $environment);
        }
    }

    app()->instance('env', 'local');
    try {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        actingAs($connected)->delete(route('grp.chat.staff.slack.connect.delete'))
            ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']))
            ->assertSessionHas(StartSlackConnection::NOTICE_KEY, fn (string $notice) => str_contains($notice, 'this machine'));
    } finally {
        app()->instance('env', $environment);
    }
    expect(DB::table('slack_connections')->where('id', $connection->id)->value('token'))->toBeNull();
    Http::assertNothingSent();
});

test('the connect routes have their own small limit', function () {
    $engineer = ($this->makeUser)('help-desk-clerk', false);
    foreach (range(1, 10) as $ignored) {
        actingAs($engineer)->get(route('grp.chat.staff.slack.connect.start'))->assertRedirect();
    }
    actingAs($engineer)->get(route('grp.chat.staff.slack.connect.start'))->assertStatus(429);
    actingAs($engineer)->delete(route('grp.chat.staff.slack.connect.delete'))->assertStatus(429);
});

test('a Slack person linked by hand counts although the emails differ, and the nightly people sync keeps the link', function () {
    $user  = ($this->makeUser)(linked: false);
    $by    = ($this->makeUser)(linked: false);
    $slack = SlackUser::create(['slack_id' => 'UH'.Str::upper(Str::random(9)), 'name' => 'hand.'.$user->id, 'email' => 'other.'.$user->id.'@example.com']);

    expect(SlackChannel::linkedSlackPerson($user))->toBeNull();

    LinkSlackUsers::make()->link($slack, $user, $by);

    expect(SlackChannel::linkedSlackPerson($user)?->is($slack))->toBeTrue()
        ->and($slack->fresh()->link_confirmed_by_user_id)->toBe($by->id)
        ->and($user->fresh()->slack_user_id)->toBe($slack->slack_id);

    StoreSlackUser::run(['id' => $slack->slack_id, 'name' => $slack->name, 'profile' => ['email' => $slack->email]]);

    expect($slack->fresh()->user_id)->toBe($user->id)
        ->and(SlackChannel::linkedSlackPerson($user)?->is($slack))->toBeTrue();
});

test('slack:link-users writes nothing without --confirm, refuses ambiguous pairs and can undo a link', function () {
    $user    = ($this->makeUser)(linked: false);
    $taken   = ($this->makeUser)();
    $by      = ($this->makeUser)(linked: false);
    $handle  = 'hand.'.$user->id;
    $slack   = SlackUser::create(['slack_id' => 'UH'.Str::upper(Str::random(9)), 'name' => $handle, 'email' => 'other.'.$user->id.'@example.com']);
    $twin    = 'twin.'.$user->id;
    SlackUser::create(['slack_id' => 'UT'.Str::upper(Str::random(9)), 'name' => $twin]);
    SlackUser::create(['slack_id' => 'UT'.Str::upper(Str::random(9)), 'name' => $twin]);
    $guest   = SlackUser::create(['slack_id' => 'UG'.Str::upper(Str::random(9)), 'name' => 'guest.'.$user->id, 'data' => ['is_restricted' => true]]);
    $other   = SlackUser::create(['slack_id' => 'UO'.Str::upper(Str::random(9)), 'name' => 'other.'.$user->id]);

    $this->artisan('slack:link-users', ['pairs' => ["$handle={$user->username}"]])->assertSuccessful();
    expect($slack->fresh()->link_confirmed_at)->toBeNull();

    $plan = collect(LinkSlackUsers::make()->plan([
        "$twin={$user->username}",
        "{$guest->name}={$user->username}",
        "{$other->name}={$taken->username}",
        "$handle=nobody-".$user->id,
        "$handle={$user->username}",
    ], false))->pluck('action');

    expect($plan[0])->toStartWith('refused: several')
        ->and($plan[1])->toStartWith('refused: Slack person is a bot')
        ->and($plan[2])->toBe('link, replaces email match person.'.$taken->id)
        ->and($plan[3])->toStartWith('refused: no active aiku user')
        ->and($plan[4])->toBe('link');

    $this->artisan('slack:link-users', ['pairs' => ["$handle={$user->username}"], '--confirm' => true])->assertFailed();
    expect($slack->fresh()->link_confirmed_at)->toBeNull();

    $this->artisan('slack:link-users', ['pairs' => ["$handle={$user->username}"], '--confirm' => true, '--by' => $by->username])->assertSuccessful();
    expect($slack->fresh()->user_id)->toBe($user->id);

    $emailMatch = SlackChannel::linkedSlackPerson($taken);
    $taken->update(['slack_user_id' => $emailMatch->slack_id]);
    $this->artisan('slack:link-users', ['pairs' => ["{$other->name}={$taken->username}"], '--confirm' => true, '--by' => $by->username])->assertSuccessful();
    StoreSlackUser::run(['id' => $emailMatch->slack_id, 'name' => $emailMatch->name, 'profile' => ['email' => $emailMatch->email]]);
    expect(SlackChannel::linkedSlackPerson($taken)?->is($other))->toBeTrue()
        ->and($taken->fresh()->slack_user_id)->toBe($other->slack_id)
        ->and(LinkSlackUsers::make()->plan(["$handle={$taken->username}"], false)[0]['action'])->toBe('refused: '.$taken->username.' is already linked by hand to Slack person '.$other->name);

    $this->artisan('slack:link-users', ['pairs' => [$handle], '--undo' => true, '--confirm' => true])->assertSuccessful();
    expect($slack->fresh()->user_id)->toBeNull()
        ->and($slack->fresh()->link_confirmed_at)->toBeNull()
        ->and($user->fresh()->slack_user_id)->toBeNull()
        ->and(SlackChannel::linkedSlackPerson($user))->toBeNull();
});

test('private conversations need a second yes: the opt-in asks Slack for the private scopes on top, records the consent with both text versions, and the public flow never asks for them, INI-429', function () {
    $user = ($this->makeUser)();
    ($this->connect)($user);
    $versions = ['consent_version' => SlackWriteAccess::PRIVATE_CONSENT_VERSION, 'terms_version' => SlackWriteAccess::PRIVATE_TERMS_VERSION];

    actingAs($user)->post(route('grp.chat.staff.slack.connect.private.start'), $versions)->assertSessionHasErrors('understood');
    actingAs($user)->post(route('grp.chat.staff.slack.connect.private.start'), ['understood' => true, 'consent_version' => 'v0', 'terms_version' => 'old'])->assertSessionHasErrors(['consent_version', 'terms_version']);

    $response = actingAs($user)->withHeaders(['X-Inertia' => 'true'])->post(route('grp.chat.staff.slack.connect.private.start'), ['understood' => true] + $versions);
    $response->assertStatus(409);
    test()->flushHeaders();
    parse_str((string) parse_url($response->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $query);
    expect(explode(',', $query['user_scope']))->toBe([...SlackWriteAccess::USER_SCOPES, ...SlackWriteAccess::PRIVATE_SCOPES])
        ->and(session(StartSlackConnection::STATE_KEY)['private'])->toBe($versions);

    $granted = [...SlackWriteAccess::USER_SCOPES, ...SlackWriteAccess::PRIVATE_SCOPES];
    Http::fake(['slack.com/api/oauth.v2.access' => function () use (&$granted, $user) {
        return Http::response(['ok' => true, 'team' => ['id' => 'TOURTEAM'], 'authed_user' => ['id' => ($this->slackIdOf)($user), 'scope' => implode(',', $granted), 'access_token' => $this->token, 'token_type' => 'user']]);
    }]);
    \Illuminate\Support\Facades\Queue::fake();
    actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-p', 'state' => $query['state']]))
        ->assertRedirect(route('grp.profile.edit', ['section' => 'slack']));
    \App\Actions\Chat\Staff\Slack\FillSlackDirectGaps::assertPushed(1, fn ($action, array $arguments) => $arguments === [$user->id]);

    $connection = SlackConnection::where('user_id', $user->id)->firstOrFail();
    expect($connection->hasPrivateConsent())->toBeTrue()
        ->and($connection->private_consent_version)->toBe(SlackWriteAccess::PRIVATE_CONSENT_VERSION)
        ->and($connection->private_terms_version)->toBe(SlackWriteAccess::PRIVATE_TERMS_VERSION)
        ->and(DB::table('slack_private_consents')->where('user_id', $user->id)->pluck('event')->all())->toBe(['granted']);
    actingAs($user)->get(route('grp.profile.edit'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('fields')->contains(fn ($fields) => ($fields['slack_connection']['connection']['private']['on'] ?? null) === true)));

    $state = ($this->startAndGetState)($user);
    expect(session(StartSlackConnection::STATE_KEY)['private'])->toBeNull();
    $granted = SlackWriteAccess::USER_SCOPES;
    actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-q', 'state' => $state]))->assertSessionHas(StartSlackConnection::NOTICE_KEY);
    expect($connection->refresh()->private_consented_at)->toBeNull()
        ->and(DB::table('slack_private_consents')->where('user_id', $user->id)->orderBy('id')->pluck('event')->all())->toBe(['granted', 'lapsed']);
});

test('declining the private opt-in in Slack changes nothing and says so', function () {
    $user       = ($this->makeUser)();
    $connection = ($this->connect)($user);
    actingAs($user)->withHeaders(['X-Inertia' => 'true'])->post(route('grp.chat.staff.slack.connect.private.start'), ['understood' => true, 'consent_version' => SlackWriteAccess::PRIVATE_CONSENT_VERSION, 'terms_version' => SlackWriteAccess::PRIVATE_TERMS_VERSION]);
    test()->flushHeaders();
    $state = session(StartSlackConnection::STATE_KEY)['value'];
    Http::fake();

    actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', ['error' => 'access_denied', 'state' => $state]))
        ->assertSessionHas(StartSlackConnection::ERROR_KEY, fn ($message) => str_contains($message, 'nothing changed'));

    expect($connection->refresh()->private_consented_at)->toBeNull()
        ->and($connection->token)->toBe($this->token)
        ->and($connection->isLive())->toBeTrue()
        ->and(DB::table('slack_private_consents')->where('user_id', $user->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('switching private conversations off revokes the token that holds them and reconnects public channels only; another person cannot touch it', function () {
    $user       = ($this->makeUser)();
    $other      = ($this->makeUser)();
    $connection = ($this->connect)($user, ['scopes' => implode(',', [...SlackWriteAccess::USER_SCOPES, ...SlackWriteAccess::PRIVATE_SCOPES])]);
    $connection->grantPrivateConsent(SlackWriteAccess::PRIVATE_CONSENT_VERSION, SlackWriteAccess::PRIVATE_TERMS_VERSION);
    ($this->connect)($other);
    ($this->fakeSlack)($user);

    actingAs($other)->delete(route('grp.chat.staff.slack.connect.private.delete'))->assertRedirect();
    expect($connection->refresh()->hasPrivateConsent())->toBeTrue();
    Http::assertNothingSent();

    $response = actingAs($user)->withHeaders(['X-Inertia' => 'true'])->delete(route('grp.chat.staff.slack.connect.private.delete'));
    $response->assertStatus(409);
    test()->flushHeaders();
    parse_str((string) parse_url($response->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $query);
    expect($query['user_scope'])->toBe(implode(',', SlackWriteAccess::USER_SCOPES))
        ->and($connection->refresh()->private_consented_at)->toBeNull()
        ->and(DB::table('slack_connections')->where('id', $connection->id)->value('token'))->toBeNull()
        ->and(DB::table('slack_private_consents')->where('user_id', $user->id)->pluck('event')->all())->toBe(['granted', 'withdrawn']);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'auth.revoke'));

    $savedState = session(StartSlackConnection::STATE_KEY);
    actingAs($other)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-x', 'state' => $query['state']]))
        ->assertSessionHas(StartSlackConnection::ERROR_KEY);
    expect(SlackConnection::where('user_id', $other->id)->value('scopes'))->toBe(implode(',', SlackWriteAccess::USER_SCOPES));
    session()->put(StartSlackConnection::STATE_KEY, $savedState);

    actingAs($user)->get(route('grp.chat.staff.slack.connect.callback', ['code' => 'code-r', 'state' => $query['state']]))
        ->assertSessionHas(StartSlackConnection::NOTICE_KEY, fn ($message) => str_contains($message, 'public channels only'));
    expect($connection->refresh()->isLive())->toBeTrue()
        ->and($connection->missingScopes())->toBe([])
        ->and($connection->hasPrivateConsent())->toBeFalse();
});

test('the private opt-in stays closed in production until the launch, and is not offered there', function () {
    app()->detectEnvironment(fn () => 'production');
    try {
        expect(SlackWriteAccess::privateOptInOpen())->toBe(SlackWriteAccess::PRIVATE_OPT_IN_LAUNCHED)
            ->and(StartSlackPrivateConnection::make()->authorize(ActionRequest::createFrom(request())->setUserResolver(fn () => ($this->makeUser)())))->toBe(SlackWriteAccess::PRIVATE_OPT_IN_LAUNCHED);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
    expect(SlackWriteAccess::privateOptInOpen())->toBeTrue();
});

test('a person asks for their DM words to be removed: a group administrator approves, a legal hold keeps them hidden, and only their DM words go, INI-568', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $ann   = ($this->makeUser)();
    $ben   = ($this->makeUser)();
    $admin = ($this->makeUser)('group-admin');
    $clerk = ($this->makeUser)();
    $annId = ($this->slackIdOf)($ann);
    $benId = ($this->slackIdOf)($ben);
    $connection = ($this->connect)($ann, ['scopes' => implode(',', [...SlackWriteAccess::USER_SCOPES, ...SlackWriteAccess::PRIVATE_SCOPES])]);
    $connection->grantPrivateConsent(SlackWriteAccess::PRIVATE_CONSENT_VERSION, SlackWriteAccess::PRIVATE_TERMS_VERSION);

    $s       = Str::upper(Str::random(6));
    $dm      = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "DER{$s}", 'direct_type' => 'im', 'is_private' => true, 'is_public' => false, 'is_member' => false]);
    $private = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "GER{$s}", 'name' => "er-{$s}", 'is_private' => true, 'is_public' => false, 'is_member' => true]);
    $public  = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CER{$s}", 'name' => "erp-{$s}", 'is_member' => true]);
    $message = fn (SlackChannel $channel, string $author, string $ts) => \App\Models\Chat\SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => $ts, 'slack_user_id' => $author, 'text' => "words of $author", 'data' => ['text' => "words of $author"], 'sent_at' => now()->subDay()]);
    $annDm   = $message($dm, $annId, '1789800000.000100');
    $annDm2  = $message($dm, $annId, '1789800001.000100');
    $benDm   = $message($dm, $benId, '1789800002.000100');
    $annPriv = $message($private, $annId, '1789800003.000100');
    $annPub  = $message($public, $annId, '1789800004.000100');
    \App\Models\Chat\SlackMessageVersion::create(['slack_message_id' => $annDm->id, 'text' => 'older', 'data' => [], 'replaced_at' => now()]);
    \App\Models\Chat\SlackMessageReaction::create(['slack_message_id' => $annDm->id, 'slack_user_id' => $benId, 'name' => 'tada']);
    $file = \App\Models\Chat\SlackFile::create(['group_id' => $this->group->id, 'slack_id' => "FER{$s}", 'slack_message_id' => $annDm2->id, 'name' => 'payslip.pdf', 'state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::NOT_AVAILABLE]);
    $shared = \App\Models\Chat\SlackFile::create(['group_id' => $this->group->id, 'slack_id' => "FSH{$s}", 'slack_message_id' => $annDm->id, 'is_private' => true, 'name' => 'plan.pdf', 'state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::NOT_AVAILABLE]);
    $annPriv->update(['data' => ['text' => "words of $annId", 'files' => [['id' => "FSH{$s}"]]]]);
    $erase = \App\Actions\Chat\Staff\Slack\EraseSlackDirectWords::make();

    expect(fn () => $erase->request($annId, $clerk))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => $erase->request($annId, $admin, holdReason: 'just_in_case'))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => $erase->request($annId, $admin, holdReason: 'dispute', holdUntil: now()->addYears(7)))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(DB::table('slack_erasure_requests')->where('slack_user_id', $annId)->count())->toBe(0);

    $held = $erase->request($annId, $admin, 'HELP-1', 'dispute', now()->addMonth());
    expect(\App\Models\Chat\SlackMessage::whereIn('id', [$annDm->id, $annDm2->id])->count())->toBe(0)
        ->and(\App\Models\Chat\SlackMessage::withTrashed()->whereIn('id', [$annDm->id, $annDm2->id])->whereNotNull('text')->count())->toBe(2)
        ->and($benDm->refresh()->deleted_at)->toBeNull()
        ->and(\App\Actions\Chat\Staff\Slack\PurgeSlackDirectWords::run($annId))->toBe(0)
        ->and($annDm->refresh()->text)->toBe("words of $annId")
        ->and($erase->due())->toBe(0);

    $this->travel(32)->days();
    expect($erase->due())->toBe(1);
    $this->travelBack();

    $row = DB::table('slack_erasure_requests')->find($held);
    expect($row->status)->toBe('erased')
        ->and($row->messages_erased)->toBe(2)
        ->and($row->files_erased)->toBe(1)
        ->and(\App\Models\Chat\SlackMessage::withTrashed()->whereIn('id', [$annDm->id, $annDm2->id])->exists())->toBeFalse()
        ->and(\App\Models\Chat\SlackMessageVersion::where('slack_message_id', $annDm->id)->exists())->toBeFalse()
        ->and(\App\Models\Chat\SlackMessageReaction::where('slack_message_id', $annDm->id)->exists())->toBeFalse()
        ->and(\App\Models\Chat\SlackFile::whereKey($file->id)->exists())->toBeFalse()
        ->and($shared->refresh()->slack_message_id)->toBe($annPriv->id)
        ->and($benDm->refresh()->text)->toBe("words of $benId")
        ->and($annPriv->refresh()->text)->toBe("words of $annId")
        ->and($annPub->refresh()->text)->toBe("words of $annId")
        ->and($connection->refresh()->private_consented_at)->toBeNull()
        ->and($connection->token)->not->toBeNull()
        ->and($erase->erase($held))->toBe(['messages' => 0, 'files' => 0]);
    \Illuminate\Support\Facades\Notification::assertSentToTimes($ann, \App\Notifications\SlackJoinRequestNotification::class, 2);

    $message($dm, $benId, '1789800005.000100');
    $now = $erase->request($benId, $admin, 'HELP-2');
    expect(DB::table('slack_erasure_requests')->find($now)->messages_erased)->toBe(2)
        ->and(\App\Models\Chat\SlackMessage::withTrashed()->where('slack_channel_id', $dm->id)->exists())->toBeFalse();

    $this->artisan('slack:erase-direct-words', ['person' => $annId, '--approved-by' => $clerk->username])->assertExitCode(1);
});
