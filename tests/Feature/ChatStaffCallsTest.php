<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Call\CloseStaleStaffCalls;
use App\Actions\Chat\Staff\Call\DeclineStaffCall;
use App\Actions\Chat\Staff\Call\EndStaffCall;
use App\Actions\Chat\Staff\Call\IssueStaffCallToken;
use App\Actions\Chat\Staff\Call\JoinStaffCall;
use App\Actions\Chat\Staff\Call\RemoveFromLiveKitRoom;
use App\Actions\Chat\Staff\Call\ReceiveLiveKitWebhook;
use App\Actions\Chat\Staff\Call\StartStaffCall;
use App\Actions\Chat\Staff\StoreStaffConversation;
use App\Events\StaffCallUpdated;
use App\Events\StaffMessageSent;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ChatHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user) = createShop();
    $this->user->update(['status' => true]);

    $makeColleague = fn () => User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => true]);
    $this->callee   = $makeColleague();
    $this->outsider = $makeColleague();

    Config::set('services.livekit', ['url' => 'ws://localhost:7880', 'api_key' => 'devkey', 'api_secret' => 'test-secret-that-is-long-enough-for-hs256']);
    Event::fake([StaffCallUpdated::class, StaffMessageSent::class]);
    Http::fake(['localhost:7880/*' => Http::response([], 200)]);
    Http::preventStrayRequests();

    $this->dm = StoreStaffConversation::run($this->user, ['user_ids' => [$this->callee->id]]);
    StaffCall::where('staff_conversation_id', $this->dm->id)->whereNull('ended_at')->update(['ended_at' => now(), 'outcome' => 'cancelled']);
});

function decodeCallToken(string $token): object
{
    return JWT::decode($token, new Key(config('services.livekit.api_secret'), 'HS256'));
}

function signedLiveKitWebhook(array $payload, string $secret = 'test-secret-that-is-long-enough-for-hs256'): array
{
    $body = json_encode($payload);
    $jwt  = JWT::encode(['iss' => 'devkey', 'exp' => time() + 60, 'sha256' => base64_encode(hash('sha256', $body, true))], $secret, 'HS256');

    return [$body, $jwt];
}

test('the starter gets a short-lived token for that room only, naming them, with microphone, camera and screen', function () {
    $response = actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->assertOk();

    $call   = StaffCall::where('ulid', $response->json('call.ulid'))->firstOrFail();
    $claims = decodeCallToken($response->json('token'));

    expect($claims->sub)->toBe((string) $this->user->id)
        ->and($claims->name)->toBe($this->user->chatName())
        ->and($claims->iss)->toBe('devkey')
        ->and($claims->exp - time())->toBeLessThanOrEqual(StaffCall::TOKEN_TTL_SECONDS)->toBeGreaterThan(60)
        ->and($claims->video->room)->toBe($call->room)
        ->and($claims->video->roomJoin)->toBeTrue()
        ->and($claims->video->canPublish)->toBeTrue()
        ->and($claims->video->canSubscribe)->toBeTrue()
        ->and($claims->video->canPublishSources)->toBe(['microphone', 'camera', 'screen_share', 'screen_share_audio'])
        ->and((array) $claims->video)->not->toHaveKeys(['roomAdmin', 'roomCreate', 'roomList', 'roomRecord'])
        ->and($response->json('url'))->toBe('ws://localhost:7880')
        ->and(json_encode($response->json('call')))->not->toContain($call->room)
        ->and(strlen($call->room))->toBeGreaterThan(40);
});

test('nobody outside the conversation, nobody who left it and no inactive user gets a token', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];

    actingAs($this->outsider)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertForbidden();
    actingAs($this->outsider)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->assertForbidden();

    $this->dm->removeParticipants([$this->callee->id]);
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertForbidden();
    $this->dm->addParticipants([$this->callee->id]);

    $this->callee->update(['status' => false]);
    expect(actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->status())->toBeIn([401, 403])
        ->and($call->refresh()->mayBeJoinedBy($this->callee->refresh()))->toBeFalse();
    $this->callee->update(['status' => true]);

    EndStaffCall::run($call);
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertForbidden();
});

test('calls are held in direct conversations only, never in groups or task and record threads', function () {
    $group = StoreStaffConversation::run($this->user, ['user_ids' => [$this->callee->id, $this->outsider->id], 'name' => 'Calls '.uniqid()]);
    actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $group->ulid))->assertForbidden();

    $this->dm->update(['context_type' => 'StaffTask', 'context_id' => 1]);
    actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->assertForbidden();
    $this->dm->update(['context_type' => null, 'context_id' => null]);
});

test('someone taken off the conversation mid-call is taken out of the media room, which ends a direct call and closes the room', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();
    $this->dm->removeParticipants([$this->callee->id]);

    CloseStaleStaffCalls::make()->handle($this->dm);

    expect($call->refresh()->outcome)->toBe(StaffCall::OUTCOME_ANSWERED)
        ->and($call->presentUsers()->count())->toBe(0);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/twirp/livekit.RoomService/RemoveParticipant')
        && $request['room'] === $call->room && $request['identity'] === (string) $this->callee->id
        && decodeCallToken(substr($request->header('Authorization')[0], 7))->video->roomAdmin === true);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/twirp/livekit.RoomService/DeleteRoom') && $request['room'] === $call->room);
    $this->dm->addParticipants([$this->callee->id]);
});

test('one live call per conversation: a second start joins the first and answers it', function () {
    $first  = StartStaffCall::run($this->dm, $this->user)['call'];
    $second = StartStaffCall::run($this->dm, $this->callee)['call'];

    expect($second->id)->toBe($first->id)
        ->and($second->answered_at)->not->toBeNull()
        ->and(StaffCall::where('staff_conversation_id', $this->dm->id)->whereNull('ended_at')->count())->toBe(1)
        ->and(fn () => StaffCall::create(['group_id' => $this->dm->group_id, 'staff_conversation_id' => $this->dm->id, 'started_by_user_id' => $this->user->id]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('the ring goes to the people in the conversation only, never with the room name', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];

    Event::assertDispatched(StaffCallUpdated::class, function (StaffCallUpdated $event) use ($call) {
        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->sort()->values()->all();
        $payload  = $event->broadcastWith();

        return $event->call->id === $call->id
            && $channels === collect(['private-grp.personal.'.$this->user->id, 'private-grp.personal.'.$this->callee->id])->sort()->values()->all()
            && $event->broadcastAs() === 'staff-call'
            && !str_contains(json_encode($payload), $call->room)
            && $payload['answered_at'] === null;
    });
});

test('accepting answers the call and tells every tab of the callee, so the other rings stop', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk()
        ->assertJsonPath('call.answered_at', fn ($answeredAt) => $answeredAt !== null)
        ->assertJsonPath('call.present_user_ids', fn ($ids) => in_array($this->callee->id, $ids));

    Event::assertDispatched(StaffCallUpdated::class, fn (StaffCallUpdated $event) => $event->broadcastWith()['answered_at'] !== null
        && collect($event->broadcastOn())->contains(fn ($channel) => $channel->name === 'private-grp.personal.'.$this->callee->id));
});

test('declining a direct call ends it as declined with an entry in the conversation', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.decline', $call->ulid))->assertOk();

    $call->refresh();
    $entry = $this->dm->messages()->where('staff_call_id', $call->id)->sole();
    expect($call->outcome)->toBe(StaffCall::OUTCOME_DECLINED)
        ->and($call->declined_by_user_id)->toBe($this->callee->id)
        ->and($entry->body)->toBe('staff-call:declined')
        ->and(StaffCall::entryText($entry->body))->toBe('Call declined')
        ->and($entry->user_id)->toBe($this->user->id);
});

test('the starter hanging up before anyone answers cancels the call', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk()
        ->assertJsonPath('call.outcome', StaffCall::OUTCOME_CANCELLED);
});

test('a direct call nobody answers is missed after the ring time', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    $call->update(['started_at' => now()->subSeconds(StaffCall::RING_SECONDS + 1)]);

    CloseStaleStaffCalls::make()->handle($this->dm);

    expect($call->refresh()->outcome)->toBe(StaffCall::OUTCOME_MISSED)
        ->and($this->dm->messages()->where('staff_call_id', $call->id)->sole()->body)->toBe('staff-call:missed');
});

test('a call whose browsers stopped pinging is closed, and the entry records duration and who joined', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();
    $call->update(['answered_at' => now()->subMinutes(5)]);
    $call->users()->newPivotStatement()->where('staff_call_id', $call->id)->update(['last_seen_at' => now()->subSeconds(StaffCall::STALE_SECONDS + 5)]);

    $this->artisan('staff-calls:close-stale')->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/RemoveParticipant') && $request['identity'] === (string) $this->callee->id);
    $call->refresh();
    $entry = $this->dm->messages()->where('staff_call_id', $call->id)->sole();
    $summary = $call->entrySummary();
    expect($call->outcome)->toBe(StaffCall::OUTCOME_ANSWERED)
        ->and($summary['duration_seconds'])->toBeGreaterThanOrEqual(300)
        ->and($summary['joined'])->toContain($this->user->chatName(), $this->callee->chatName())
        ->and($entry->body)->toBe('staff-call:answered:'.$summary['duration_seconds'])
        ->and(StaffCall::entryText($entry->body))->toBe('Call, 5 min');

    actingAs($this->user)->getJson(route('grp.chat.staff.conversations.messages.index', $this->dm->ulid))->assertOk()
        ->assertJsonPath('data.'.($this->dm->messages()->count() - 1).'.call.outcome', StaffCall::OUTCOME_ANSWERED);
});

test('the starter hanging up an answered direct call ends it for both', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk()->assertJsonPath('call.outcome', StaffCall::OUTCOME_ANSWERED);
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk()->assertJsonPath('call.outcome', StaffCall::OUTCOME_ANSWERED);
});

test('a signed LiveKit webhook closes the call when the room finishes; a forged one is refused', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    $payload = ['event' => 'room_finished', 'room' => ['name' => $call->room]];

    [$body, $forged] = signedLiveKitWebhook($payload, 'some-other-secret-that-is-long-enough');
    $this->call('POST', route('webhooks.livekit'), [], [], [], ['HTTP_AUTHORIZATION' => $forged, 'CONTENT_TYPE' => 'application/webhook+json'], $body)->assertUnauthorized();
    [$body, $signed] = signedLiveKitWebhook($payload);
    $this->call('POST', route('webhooks.livekit'), [], [], [], ['HTTP_AUTHORIZATION' => $signed, 'CONTENT_TYPE' => 'application/webhook+json'], $body.' ')->assertUnauthorized();
    expect($call->refresh()->ended_at)->toBeNull();

    $this->call('POST', route('webhooks.livekit'), [], [], [], ['HTTP_AUTHORIZATION' => $signed, 'CONTENT_TYPE' => 'application/webhook+json'], $body)->assertNoContent();
    expect($call->refresh()->ended_at)->not->toBeNull();
});

test('a webhook never adds someone who did not join through aiku, and a participant leaving (a reconnect) ends nothing', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();

    ReceiveLiveKitWebhook::run(['event' => 'participant_joined', 'room' => ['name' => $call->room], 'participant' => ['identity' => (string) $this->outsider->id]]);
    expect($call->users()->where('users.id', $this->outsider->id)->exists())->toBeFalse();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/RemoveParticipant') && $request['identity'] === (string) $this->outsider->id);

    $this->dm->removeParticipants([$this->callee->id]);
    ReceiveLiveKitWebhook::run(['event' => 'participant_joined', 'room' => ['name' => $call->room], 'participant' => ['identity' => (string) $this->callee->id]]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/RemoveParticipant') && $request['identity'] === (string) $this->callee->id);
    $this->dm->addParticipants([$this->callee->id]);

    ReceiveLiveKitWebhook::run(['event' => 'participant_left', 'room' => ['name' => $call->room], 'participant' => ['identity' => (string) $this->callee->id]]);
    expect($call->refresh()->ended_at)->toBeNull()
        ->and($call->presentUsers()->count())->toBe(2);
});

test('without LiveKit settings there is no call button and every call route answers 404', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    Config::set('services.livekit', ['url' => null, 'api_key' => null, 'api_secret' => null]);

    actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->assertNotFound();
    actingAs($this->user)->getJson(route('grp.chat.staff.calls.live'))->assertNotFound();
    foreach (['join', 'decline', 'leave', 'ping'] as $action) {
        actingAs($this->user)->postJson(route('grp.chat.staff.calls.'.$action, $call->ulid))->assertNotFound();
    }
    $this->postJson(route('webhooks.livekit'), [])->assertNotFound();

    $conversation = collect(actingAs($this->user)->getJson(route('grp.chat.staff.conversations.index'))->json('data'))->firstWhere('ulid', $this->dm->ulid);
    expect($conversation['can_call'])->toBeFalse();
});

test('the live calls list gives a refreshed page the calls the person is in, and only those', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];

    expect(actingAs($this->callee)->getJson(route('grp.chat.staff.calls.live'))->assertOk()->json('data.*.ulid'))->toContain($call->ulid)
        ->and(actingAs($this->outsider)->getJson(route('grp.chat.staff.calls.live'))->assertOk()->json('data.*.ulid'))->not->toContain($call->ulid);

    $conversation = collect(actingAs($this->user)->getJson(route('grp.chat.staff.conversations.index'))->json('data'))->firstWhere('ulid', $this->dm->ulid);
    expect($conversation['can_call'])->toBeTrue()
        ->and(StaffConversation::find($this->dm->id)->isActiveParticipant($this->outsider))->toBeFalse();
});

test('a caller whose ring ran out records a missed call, from a few seconds early to allow for clocks; earlier it is cancelled', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    $call->update(['started_at' => now()->subSeconds(StaffCall::RING_SECONDS - StaffCall::RING_TIMEOUT_GRACE_SECONDS)]);
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $call->ulid), ['reason' => 'ring_timeout'])->assertOk()
        ->assertJsonPath('call.outcome', StaffCall::OUTCOME_MISSED);

    $early = StartStaffCall::run($this->dm, $this->user)['call'];
    $early->update(['started_at' => now()->subSeconds(5)]);
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $early->ulid), ['reason' => 'ring_timeout'])->assertOk()
        ->assertJsonPath('call.outcome', StaffCall::OUTCOME_CANCELLED);

    $third = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $third->ulid), ['reason' => 'anything'])->assertUnprocessable();
    EndStaffCall::run($third);
});

test('one person cannot ring a colleague more than a few times a minute', function () {
    $statuses = [];
    for ($i = 0; $i < 7; $i++) {
        $statuses[] = actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->status();
    }

    expect(array_slice($statuses, 0, 6))->each->toBe(200)
        ->and($statuses[6])->toBe(429);
});

test('pages tell the browser whether calls exist, so an unconfigured server is never asked', function () {
    actingAs($this->user)->get(route('grp.chat.staff.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('staff_calls', true));

    Config::set('services.livekit.url', null);
    actingAs($this->user)->get(route('grp.chat.staff.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('staff_calls', false));
});

test('a secret too short to sign with leaves calls off, and a token that cannot be signed rings nobody', function () {
    Config::set('services.livekit.api_secret', 'secret');
    expect(StaffCall::isEnabled())->toBeFalse();
    actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->assertNotFound();

    Config::set('services.livekit.api_secret', 'test-secret-that-is-long-enough-for-hs256');
    IssueStaffCallToken::shouldRun()->andThrow(new DomainException('cannot sign'));
    expect(fn () => StartStaffCall::run($this->dm, $this->user))->toThrow(DomainException::class);

    Event::assertNotDispatched(StaffCallUpdated::class);
    $call = StaffCall::where('staff_conversation_id', $this->dm->id)->latest('id')->first();
    expect($call->users()->count())->toBe(0);
    IssueStaffCallToken::clearFake();
    EndStaffCall::run($call);
});

test('a join that loses the race with a hang-up is refused and leaves no seat behind', function () {
    $call  = StartStaffCall::run($this->dm, $this->user)['call'];
    $stale = StaffCall::find($call->id);
    EndStaffCall::run($call);

    expect(fn () => JoinStaffCall::run($stale, $this->callee))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and($call->users()->where('users.id', $this->callee->id)->exists())->toBeFalse();
});

test('a decline from another tab never overturns an answered call', function () {
    $call  = StartStaffCall::run($this->dm, $this->user)['call'];
    $stale = StaffCall::find($call->id);
    JoinStaffCall::run($call, $this->callee);

    DeclineStaffCall::run($stale, $this->callee);

    expect($call->refresh()->ended_at)->toBeNull()
        ->and($call->answered_at)->not->toBeNull();
    EndStaffCall::run($call);
});

test('reading live calls never sweeps, and the call server is told in a queued job so a slow server holds nothing up', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    $call->update(['started_at' => now()->subMinutes(5)]);

    expect(actingAs($this->callee)->getJson(route('grp.chat.staff.calls.live'))->json('data.*.ulid'))->toContain($call->ulid)
        ->and($call->refresh()->ended_at)->toBeNull();

    \Illuminate\Support\Facades\Queue::fake();
    EndStaffCall::run($call);
    RemoveFromLiveKitRoom::assertPushed(1);
});

test('only a missed call counts as unread; answered, declined and cancelled entries are quiet, and previews are words', function () {
    $unread = fn () => collect(actingAs($this->callee)->getJson(route('grp.chat.staff.conversations.index'))->json('data'))->firstWhere('ulid', $this->dm->ulid);
    \App\Actions\Chat\Staff\MarkStaffConversationRead::run($this->dm, $this->callee);

    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    EndStaffCall::run($call);
    expect($unread()['unread_count'])->toBe(0)
        ->and($unread()['last_message'])->toBe('Call cancelled');

    $missed = StartStaffCall::run($this->dm, $this->user)['call'];
    EndStaffCall::run($missed, StaffCall::OUTCOME_MISSED);
    expect($unread()['unread_count'])->toBe(1)
        ->and($unread()['last_message'])->toBe('Missed call');
});

test('a direct call ends as soon as either person leaves, so the other is never left in an empty call', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk()
        ->assertJsonPath('call.outcome', StaffCall::OUTCOME_ANSWERED)
        ->assertJsonPath('call.present_user_ids', []);

    Event::assertDispatched(StaffCallUpdated::class, fn (StaffCallUpdated $event) => $event->call->id === $call->id && $event->broadcastWith()['ended_at'] !== null
        && collect($event->broadcastOn())->contains(fn ($channel) => $channel->name === 'private-grp.personal.'.$this->user->id));
});

test('a leave that carries anything but the ring timeout as its reason is refused without ending the call, and a plain leave works', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid), ['reason' => ['isTrusted' => true]])->assertUnprocessable();
    expect($call->refresh()->ended_at)->toBeNull();

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk()->assertJsonPath('call.outcome', StaffCall::OUTCOME_ANSWERED);
});

test('the person still in a call closes it on their next ping once the other side went silent, with no scheduler running', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();
    $call->users()->newPivotStatement()->where('staff_call_id', $call->id)->where('user_id', $this->callee->id)
        ->update(['last_seen_at' => now()->subSeconds(StaffCall::STALE_SECONDS + 5)]);

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.ping', $call->ulid))->assertOk()
        ->assertJsonPath('call.outcome', StaffCall::OUTCOME_ANSWERED);
});

test('the kind of call is stored and broadcast; a screen share is a call of its own kind and an unknown kind is refused', function () {
    $before = StaffCall::where('staff_conversation_id', $this->dm->id)->count();

    actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid), ['kind' => 'hologram'])->assertUnprocessable();
    expect(StaffCall::where('staff_conversation_id', $this->dm->id)->count())->toBe($before);

    $response = actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid), ['kind' => StaffCall::KIND_SCREEN])
        ->assertOk()->assertJsonPath('call.kind', StaffCall::KIND_SCREEN);
    $call = StaffCall::where('ulid', $response->json('call.ulid'))->firstOrFail();

    expect($call->kind)->toBe(StaffCall::KIND_SCREEN)
        ->and(StaffCall::where('staff_conversation_id', $this->dm->id)->count())->toBe($before + 1);
    Event::assertDispatched(StaffCallUpdated::class, fn (StaffCallUpdated $event) => $event->call->id === $call->id && $event->broadcastWith()['kind'] === StaffCall::KIND_SCREEN);
    EndStaffCall::run($call);

    $voice = actingAs($this->user)->postJson(route('grp.chat.staff.conversations.calls.store', $this->dm->ulid))->assertOk();
    expect($voice->json('call.kind'))->toBe(StaffCall::KIND_VOICE);
    EndStaffCall::run(StaffCall::where('ulid', $voice->json('call.ulid'))->first());
});

test('a screen share leaves its own entry, and a missed one counts as unread', function () {
    $call = StartStaffCall::run($this->dm, $this->user, StaffCall::KIND_SCREEN)['call'];
    EndStaffCall::run($call, StaffCall::OUTCOME_MISSED);

    $entry = $this->dm->messages()->where('staff_call_id', $call->id)->sole();
    expect($entry->body)->toBe('staff-call:missed:screen')
        ->and(StaffCall::entryText($entry->body))->toBe('Missed screen share')
        ->and(StaffCall::isMissedEntry($entry->body))->toBeTrue()
        ->and(StaffCall::entryText(StaffCall::entryMarker(StaffCall::OUTCOME_ANSWERED, 300, StaffCall::KIND_SCREEN)))->toBe('Screen share, 5 min')
        ->and($call->refresh()->entrySummary()['kind'])->toBe(StaffCall::KIND_SCREEN);

    $listed = collect(actingAs($this->callee)->getJson(route('grp.chat.staff.conversations.index'))->json('data'))->firstWhere('ulid', $this->dm->ulid);
    expect($listed['unread_count'])->toBeGreaterThanOrEqual(1)
        ->and($listed['last_message'])->toBe('Missed screen share');
});

test('Verba reports count messages, reply time, calls and meetings in the period, against the period before', function () {
    $at       = fn (string $time) => \Illuminate\Support\Carbon::parse('2031-06-10 '.$time, 'UTC');
    $message  = fn (User $user, $createdAt) => \Illuminate\Support\Facades\DB::table('staff_messages')->insert(['staff_conversation_id' => $this->dm->id, 'user_id' => $user->id, 'body' => 'x', 'created_at' => $createdAt, 'updated_at' => $createdAt]);

    $message($this->user, $at('10:00:00'));
    $message($this->user, $at('10:01:00'));
    $message($this->callee, $at('10:05:00'));
    $message($this->user, $at('10:06:00'));
    $message($this->user, $at('10:06:00')->subDays(9));

    $answered = StaffCall::create(['group_id' => $this->dm->group_id, 'staff_conversation_id' => $this->dm->id, 'started_by_user_id' => $this->user->id, 'kind' => StaffCall::KIND_VIDEO, 'started_at' => $at('11:00:00'), 'answered_at' => $at('11:00:10'), 'ended_at' => $at('11:05:10'), 'outcome' => StaffCall::OUTCOME_ANSWERED]);
    foreach ([$this->user, $this->callee, $this->outsider] as $person) {
        $answered->users()->attach($person->id, ['joined_at' => $at('11:00:10'), 'left_at' => $at('11:05:10'), 'last_seen_at' => $at('11:05:10')]);
    }
    StaffCall::create(['group_id' => $this->dm->group_id, 'staff_conversation_id' => $this->dm->id, 'started_by_user_id' => $this->callee->id, 'started_at' => $at('12:00:00'), 'ended_at' => $at('12:00:30'), 'outcome' => StaffCall::OUTCOME_MISSED]);

    $report = \App\Actions\Chat\Staff\UI\ShowStaffChatReports::make()->handle($this->user->group, 7, 'day', $at('18:00:00'));

    expect($report['current'])->toMatchArray([
        'messages'              => 4,
        'people'                => 2,
        'direct_messages'       => 4,
        'reply_median_seconds'  => 180,
        'calls'                 => 2,
        'answered'              => 1,
        'missed'                => 1,
        'video'                 => 1,
        'meetings'              => 1,
        'answer_median_seconds' => 10,
        'call_median_seconds'   => 300,
        'talk_minutes'          => 5,
        'person_minutes'        => 15,
    ])
        ->and($report['previous']['messages'])->toBe(1)
        ->and($report['series']['buckets'])->toHaveCount(7)
        ->and(last($report['series']['messages']))->toBe(4)
        ->and(last($report['series']['calls']))->toBe(2);

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user)->get(route('grp.chat.staff.reports', ['days' => 30]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffChatReports')->where('report.days', 30)->where('report.by', 'day')->has('people.data'));
    actingAs($this->callee)->get(route('grp.chat.staff.reports'))->assertForbidden();
});

test('each ping adds what the browser measured to the person\'s call quality, nonsense is dropped without failing the ping, and the report averages it', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    $ping = fn (array $quality) => actingAs($this->user)->postJson(route('grp.chat.staff.calls.ping', $call->ulid), ['quality' => $quality]);

    $ping(['path' => 'udp', 'connection' => 'excellent', 'rtt_ms' => 40, 'loss_pct' => 0, 'sent_audio_kbps' => 60, 'unknown' => 5])->assertOk();
    $ping(['path' => 'relay', 'connection' => 'poor', 'rtt_ms' => 120.04, 'loss_pct' => 3])->assertOk();
    $seenBefore = $call->users()->newPivotStatement()->where('staff_call_id', $call->id)->where('user_id', $this->user->id)->value('last_seen_at');
    $this->travel(5)->seconds();
    $ping(['rtt_ms' => -1, 'loss_pct' => 'lots', 'path' => 'carrier-pigeon'])->assertOk();
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.ping', $call->ulid), ['quality' => 'garbage'])->assertOk();
    expect($call->users()->newPivotStatement()->where('staff_call_id', $call->id)->where('user_id', $this->user->id)->value('last_seen_at'))->not->toBe($seenBefore);

    $quality = json_decode($call->users()->newPivotStatement()->where('staff_call_id', $call->id)->where('user_id', $this->user->id)->value('quality'), true);
    expect($quality)->toEqual([
        'samples'         => 2,
        'poor_samples'    => 1,
        'paths'           => ['udp' => 1, 'relay' => 1],
        'rtt_ms'          => ['sum' => 160.0, 'n' => 2, 'max' => 120.0],
        'loss_pct'        => ['sum' => 3.0, 'n' => 2, 'max' => 3.0],
        'sent_audio_kbps' => ['sum' => 60.0, 'n' => 1, 'max' => 60.0],
    ]);

    $at   = fn (string $time) => \Illuminate\Support\Carbon::parse('2032-03-10 '.$time, 'UTC');
    $past = StaffCall::create(['group_id' => $this->dm->group_id, 'staff_conversation_id' => $this->dm->id, 'started_by_user_id' => $this->user->id, 'started_at' => $at('11:00:00'), 'answered_at' => $at('11:00:10'), 'ended_at' => $at('11:05:10'), 'outcome' => StaffCall::OUTCOME_ANSWERED]);
    $past->users()->attach($this->user->id, ['joined_at' => $at('11:00:10'), 'left_at' => $at('11:05:10'), 'last_seen_at' => $at('11:05:10'), 'quality' => json_encode($quality)]);
    $past->users()->attach($this->callee->id, ['joined_at' => $at('11:00:10'), 'left_at' => $at('11:05:10'), 'last_seen_at' => $at('11:05:10'), 'quality' => json_encode(['samples' => 2, 'poor_samples' => 0, 'paths' => ['udp' => 2], 'rtt_ms' => ['sum' => 40, 'n' => 2, 'max' => 30]])]);

    $report = \App\Actions\Chat\Staff\UI\ShowStaffChatReports::make()->handle($this->user->group, 7, 'day', $at('18:00:00'));

    expect($report['current'])->toMatchArray([
        'quality_measured'   => 2,
        'quality_rtt_ms'     => 50,
        'quality_rtt_max_ms' => 120,
        'quality_loss_pct'   => 1.5,
        'quality_not_direct' => 1,
        'quality_poor_pct'   => 25.0,
    ]);
});

test('the quick call list offers the people called last, then starred colleagues, active ones of the group only', function () {
    StartStaffCall::run($this->dm, $this->user);
    $retired = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => false]);
    $this->user->teamMembers()->syncWithoutDetaching([$this->outsider->id, $retired->id, $this->callee->id]);

    $people = collect(actingAs($this->user)->getJson(route('grp.chat.staff.calls.live'))->assertOk()->json('quick_people'));

    expect($people->first()['id'])->toBe($this->callee->id)
        ->and($people->pluck('id'))->toContain($this->outsider->id)
        ->and($people->pluck('id'))->not->toContain($retired->id)
        ->and($people->pluck('id')->duplicates())->toBeEmpty()
        ->and($people->firstWhere('id', $this->outsider->id)['starred'])->toBeTrue();
});
