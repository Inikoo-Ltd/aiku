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
use Illuminate\Support\Facades\Storage;
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

function meetingGuest(\App\Models\Chat\StaffMeeting $meeting, string $name = 'Ana from the supplier'): array
{
    $response = test()->postJson(route('grp.meet.knock', $meeting->code), ['name' => $name])->assertOk();

    return [\App\Models\Chat\StaffMeetingGuest::where('ulid', $response->json('guest'))->firstOrFail(), $response->json('secret')];
}

test('a link copied from a live call lets any active colleague in, and the call no longer ends when one of the two leaves', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();

    actingAs($this->outsider)->postJson(route('grp.chat.staff.calls.link', $call->ulid))->assertForbidden();
    $url  = actingAs($this->user)->postJson(route('grp.chat.staff.calls.link', $call->ulid))->assertOk()->json('url');
    $code = basename($url);

    expect(actingAs($this->callee)->postJson(route('grp.chat.staff.calls.link', $call->ulid))->json('url'))->toBe($url)
        ->and($code)->toHaveLength(24)
        ->and($url)->not->toContain($call->ulid);

    $joined = actingAs($this->outsider)->postJson(route('grp.meet.join', $code))->assertOk();
    expect($joined->json('call.ulid'))->toBe($call->ulid)
        ->and(decodeCallToken($joined->json('token'))->sub)->toBe((string) $this->outsider->id);

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk();
    expect($call->refresh()->ended_at)->toBeNull();

    EndStaffCall::run($call);
    expect($call->meeting->refresh()->ended_at)->not->toBeNull();
    actingAs($this->outsider)->postJson(route('grp.meet.join', $code))->assertForbidden();
});

test('a meeting link made ahead of time starts a call with no conversation when the first colleague arrives, and lapses after 30 idle days', function () {
    $code = actingAs($this->user)->postJson(route('grp.chat.staff.meetings.store'), ['title' => 'Supplier catch-up'])->assertOk()->json('code');
    $meeting = \App\Models\Chat\StaffMeeting::where('code', $code)->firstOrFail();

    $first = actingAs($this->outsider)->postJson(route('grp.meet.join', $code))->assertOk();
    $call  = StaffCall::where('ulid', $first->json('call.ulid'))->firstOrFail();
    expect($call->staff_conversation_id)->toBeNull()
        ->and($call->staff_meeting_id)->toBe($meeting->id)
        ->and($first->json('call.conversation_type'))->toBe('meeting')
        ->and($first->json('call.conversation_name'))->toBe('Supplier catch-up');

    expect(actingAs($this->callee)->postJson(route('grp.meet.join', $code))->json('call.ulid'))->toBe($call->ulid);

    actingAs($this->outsider)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk();
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk();
    expect($call->refresh()->ended_at)->not->toBeNull()
        ->and($meeting->refresh()->ended_at)->toBeNull();

    $meeting->update(['last_used_at' => now()->subDays(\App\Models\Chat\StaffMeeting::IDLE_DAYS + 1)]);
    actingAs($this->callee)->postJson(route('grp.meet.join', $code))->assertForbidden();
    $this->get(route('grp.meet.show', $code))->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMeeting', false)->where('meeting.is_open', false));
});

test('the meeting page tells a visitor only the title, never who is in it', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user, 'Weekly');
    \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user);

    $page = $this->get(route('grp.meet.show', $meeting->code))->assertOk();
    $page->assertInertia(fn (AssertableInertia $inertia) => $inertia->component('Chat/StaffMeeting', false)
        ->where('meeting.title', 'Weekly')
        ->where('meeting.is_open', true)
        ->where('is_colleague', false));
    expect($page->getContent())->not->toContain($this->user->chatName());

    actingAs($this->callee)->get(route('grp.meet.show', $meeting->code))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('is_colleague', true));
    $this->get('/meet/'.str_repeat('a', 24))->assertNotFound();
});

test('a guest knocks, waits, is let in by someone in the call and gets a token for that room only, under a guest identity', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];

    [$guest, $secret] = meetingGuest($meeting, "  <b>Ana</b>\u{202E} Lopez ");
    expect($guest->name)->toBe('bAna/b Lopez')
        ->and($guest->secret_hash)->not->toBe($secret)
        ->and($call->refresh()->toBroadcast()['guests_waiting'])->toBe([['ulid' => $guest->ulid, 'name' => 'bAna/b Lopez']]);

    $this->postJson(route('grp.meet.guests.poll', [$meeting->code, $guest->ulid]), ['secret' => 'wrong'])->assertNotFound();
    $this->postJson(route('grp.meet.guests.poll', [$meeting->code, $guest->ulid]), ['secret' => $secret])->assertOk()->assertJson(['status' => 'waiting', 'call' => null]);
    $this->postJson(route('grp.meet.guests.token', [$meeting->code, $guest->ulid]), ['secret' => $secret])->assertForbidden();

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.guests.decide', [$call->ulid, $guest->ulid]), ['decision' => 'admit'])->assertForbidden();
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.guests.decide', [$call->ulid, $guest->ulid]), ['decision' => 'admit'])->assertOk();
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.guests.decide', [$call->ulid, $guest->ulid]), ['decision' => 'deny'])->assertOk();
    expect($guest->refresh()->status)->toBe('admitted');

    auth()->logout();
    $token  = $this->postJson(route('grp.meet.guests.token', [$meeting->code, $guest->ulid]), ['secret' => $secret])->assertOk();
    $claims = decodeCallToken($token->json('token'));
    expect($claims->sub)->toBe('guest-'.$guest->ulid)
        ->and($claims->name)->toBe('bAna/b Lopez')
        ->and($claims->video->room)->toBe($call->room)
        ->and((array) $claims->video)->not->toHaveKeys(['roomAdmin', 'roomCreate', 'roomList', 'roomRecord'])
        ->and(json_encode($token->json('call')))->not->toContain($this->user->chatName())
        ->and(json_encode($token->json('call')))->not->toContain($call->room);

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.guests.decide', [$call->ulid, $guest->ulid]), ['decision' => 'remove'])->assertOk();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/RemoveParticipant') && $request['identity'] === 'guest-'.$guest->ulid);
    auth()->logout();
    $this->postJson(route('grp.meet.guests.token', [$meeting->code, $guest->ulid]), ['secret' => $secret])->assertForbidden();
    expect($this->postJson(route('grp.meet.guests.poll', [$meeting->code, $guest->ulid]), ['secret' => $secret])->json('call.ended_at'))->not->toBeNull();
});

test('a denied guest never gets a token, and a locked meeting turns away the waiting and takes no new knocks', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];

    [$denied, $deniedSecret] = meetingGuest($meeting, 'First');
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.guests.decide', [$call->ulid, $denied->ulid]), ['decision' => 'deny'])->assertOk();
    auth()->logout();
    $this->postJson(route('grp.meet.guests.token', [$meeting->code, $denied->ulid]), ['secret' => $deniedSecret])->assertForbidden();

    [$waiting] = meetingGuest($meeting, 'Second');
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.lock', $call->ulid), ['locked' => true])->assertOk()->assertJsonPath('call.meeting_locked', true);
    auth()->logout();

    expect($waiting->refresh()->status)->toBe('denied');
    $this->postJson(route('grp.meet.knock', $meeting->code), ['name' => 'Third'])->assertUnprocessable();
});

test('the media server drops a guest identity nobody let in, and every guest when the call ends', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];
    [$guest] = meetingGuest($meeting);

    ReceiveLiveKitWebhook::run(['event' => 'participant_joined', 'room' => ['name' => $call->room], 'participant' => ['identity' => 'guest-'.$guest->ulid]]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/RemoveParticipant') && $request['identity'] === 'guest-'.$guest->ulid);

    \App\Actions\Chat\Staff\Meeting\DecideStaffMeetingGuest::run($call, $guest, $this->user, 'admit');
    ReceiveLiveKitWebhook::run(['event' => 'participant_joined', 'room' => ['name' => $call->room], 'participant' => ['identity' => 'guest-'.$guest->ulid]]);
    expect($guest->refresh()->joined_at)->not->toBeNull();

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk();
    expect($call->refresh()->ended_at)->not->toBeNull()
        ->and($guest->refresh()->status)->toBe('left');
});

test('a guest whose browser goes quiet leaves the lobby, and knocking is throttled per address', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    [$guest] = meetingGuest($meeting, 'Quiet');
    $guest->update(['last_seen_at' => now()->subMinutes(5)]);

    CloseStaleStaffCalls::make()->handle();
    expect($guest->refresh()->status)->toBe('left');

    $statuses = collect(range(1, 6))->map(fn ($i) => $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])
        ->postJson(route('grp.meet.knock', $meeting->code), ['name' => 'Knock '.$i])->status());
    expect($statuses->last())->toBe(429);
});

test('a ringing direct call cannot be shared, a meeting call cannot be declined, and a reusable meeting unlocks when its call ends', function () {
    $ringing = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.link', $ringing->ulid))->assertStatus(409);
    EndStaffCall::run($ringing);

    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.decline', $call->ulid))->assertForbidden();

    \App\Actions\Chat\Staff\Meeting\LockStaffMeeting::run($call, true);
    EndStaffCall::run($call);
    expect($meeting->refresh()->locked_at)->toBeNull()
        ->and($meeting->ended_at)->toBeNull();
});

test('a guest still marked in a call that ended is told so and leaves, and the ping of a meeting call sweeps only that call', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];
    [$guest, $secret] = meetingGuest($meeting);
    \App\Actions\Chat\Staff\Meeting\DecideStaffMeetingGuest::run($call, $guest, $this->user, 'admit');

    $call->update(['ended_at' => now(), 'outcome' => 'answered']);
    auth()->logout();
    $this->postJson(route('grp.meet.guests.poll', [$meeting->code, $guest->ulid]), ['secret' => $secret])->assertJson(['status' => 'left', 'call_live' => false]);

    $other = StartStaffCall::run($this->dm, $this->user)['call'];
    $other->users()->newPivotStatement()->where('staff_call_id', $other->id)->update(['last_seen_at' => now()->subMinutes(5)]);
    $live = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->callee)['call'];
    \App\Actions\Chat\Staff\Call\PingStaffCall::run($live, $this->callee);
    expect($other->refresh()->ended_at)->toBeNull();
    EndStaffCall::run($other);
});

test('a linked direct call ends when its two people have gone, or when one is left alone, never outliving them', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $call->ulid))->assertOk();
    $code = basename(actingAs($this->user)->postJson(route('grp.chat.staff.calls.link', $call->ulid))->json('url'));

    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $call->ulid))->assertOk();
    expect($call->refresh()->ended_at)->not->toBeNull();

    $second = StartStaffCall::run($this->dm, $this->user)['call'];
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.join', $second->ulid))->assertOk();
    $code = basename(actingAs($this->user)->postJson(route('grp.chat.staff.calls.link', $second->ulid))->json('url'));
    actingAs($this->outsider)->postJson(route('grp.meet.join', $code))->assertOk();

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.leave', $second->ulid))->assertOk();
    expect($second->refresh()->ended_at)->toBeNull();
    actingAs($this->callee)->postJson(route('grp.chat.staff.calls.leave', $second->ulid))->assertOk();
    expect($second->refresh()->ended_at)->not->toBeNull();
});

test('only whoever made a meeting link can end it, which ends its call and shows the ended page', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->callee)['call'];

    actingAs($this->callee)->deleteJson(route('grp.chat.staff.meetings.destroy', $meeting->code))->assertForbidden();
    actingAs($this->user)->deleteJson(route('grp.chat.staff.meetings.destroy', $meeting->code))->assertOk();

    expect($call->refresh()->ended_at)->not->toBeNull();
    actingAs($this->callee)->postJson(route('grp.meet.join', $meeting->code))->assertForbidden();
});

test('a removal the call server did not confirm fails the job so it is tried again', function () {
    $call = StartStaffCall::run($this->dm, $this->user)['call'];
    Http::fake(['localhost:7880/*' => Http::response([], 503)]);

    expect(fn () => RemoveFromLiveKitRoom::make()->asJob($call, 'guest-x'))->toThrow(RuntimeException::class)
        ->and(RemoveFromLiveKitRoom::make()->jobTries)->toBe(5);
    Http::fake(['localhost:7880/*' => Http::response([], 200)]);
    EndStaffCall::run($call);
});

/**
 * A calls server with Egress: lists the given participants, starts one egress per track, and records every request.
 *
 * @param array<int, array{identity: string, name: string, tracks: array<int, array{sid: string, source: string}>}> $participants
 */
function fakeRecordingCallServer(array $participants = [], bool $metadataWorks = true, bool $stopWorks = true, bool $egressWorks = true): void
{
    Config::set('services.livekit.recording', true);
    Config::set('services.openai.api_key', 'test-openai-key');
    \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($participants, $metadataWorks, $stopWorks, $egressWorks) {
        return match (true) {
            str_ends_with($request->url(), 'RoomService/ListParticipants')   => Http::response(['participants' => $participants]),
            str_ends_with($request->url(), 'RoomService/UpdateRoomMetadata') => $metadataWorks ? Http::response([]) : Http::response([], 503),
            str_ends_with($request->url(), 'Egress/StartTrackEgress')        => $egressWorks ? Http::response(['egress_id' => 'EG_'.$request['track_id'].'_'.\Illuminate\Support\Str::random(8)]) : Http::response([], 503),
            str_ends_with($request->url(), 'Egress/ListEgress')              => Http::response(['items' => $stopWorks ? [] : [['egress_id' => 'EG_running']]]),
            str_ends_with($request->url(), 'Egress/StopEgress')               => $stopWorks ? Http::response([]) : Http::response([], 503),
            str_contains($request->url(), 'api.openai.com')                   => Http::response(['segments' => [
                ['start' => 3.2, 'text' => str_contains($request->body(), 'SECOND-PIECE') ? ' Later' : ' Hello', 'no_speech_prob' => 0.01, 'avg_logprob' => -0.2],
                ['start' => 40.0, 'text' => ' Thank you for watching.', 'no_speech_prob' => 0.92, 'avg_logprob' => -1.4],
            ]]),
            default                                                           => Http::response([]),
        };
    });
    Http::preventStrayRequests();
}

function recordingCallWithTwo(object $test): StaffCall
{
    $call = StartStaffCall::run($test->dm, $test->user)['call'];
    JoinStaffCall::run($call, $test->callee);

    return $call->refresh();
}

test('a colleague in the call starts recording: the room is marked first, then each speaker\'s audio is recorded on its own, path only', function () {
    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer([
        ['identity' => (string) $this->user->id, 'name' => 'Ana', 'tracks' => [['sid' => 'TR_mic1', 'source' => 'MICROPHONE'], ['sid' => 'TR_cam1', 'type' => 'VIDEO', 'source' => 'CAMERA']]],
        ['identity' => (string) $this->callee->id, 'name' => 'Ben', 'tracks' => [['sid' => 'TR_mic2', 'source' => 'MICROPHONE']]],
    ]);

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.recording.start', $call->ulid), ['keep_audio_months' => 3])
        ->assertOk()->assertJsonPath('call.recording_by', $this->user->chatName());

    $recording = \App\Models\Chat\StaffCallRecording::where('staff_call_id', $call->id)->sole();
    $requests  = Http::recorded()->map(fn ($pair) => $pair[0]);
    $mark      = $requests->first(fn ($request) => str_ends_with($request->url(), 'UpdateRoomMetadata'));
    $egresses  = $requests->filter(fn ($request) => str_ends_with($request->url(), 'StartTrackEgress'))->values();

    expect($recording->status)->toBe('recording')
        ->and($recording->keep_audio_months)->toBe(3)
        ->and(json_decode($mark['metadata'], true))->toBe(['recording' => ['by' => $this->user->chatName()]])
        ->and($requests->search($mark))->toBeLessThan($requests->search($egresses->first()))
        ->and($egresses->pluck('track_id')->all())->toBe(['TR_mic1', 'TR_mic2'])
        ->and($egresses->every(fn ($request) => array_keys($request['file']) === ['filepath'] && str_starts_with($request['file']['filepath'], 'call-recordings/'.$recording->ulid.'/')))->toBeTrue()
        ->and(decodeCallToken(str_replace('Bearer ', '', $egresses->first()->header('Authorization')[0]))->video->roomRecord)->toBeTrue()
        ->and($recording->tracks()->pluck('speaker', 'track_sid')->all())->toBe(['TR_mic1' => $this->user->chatName(), 'TR_mic2' => $this->callee->chatName()])
        ->and($recording->tracks()->where('status', 'active')->whereNotNull('egress_id')->count())->toBe(2);
});

test('nothing is recorded when the room cannot be marked, and only staff present in the call may record', function () {
    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer([['identity' => (string) $this->user->id, 'name' => 'Ana', 'tracks' => [['sid' => 'TR_mic1', 'source' => 'MICROPHONE']]]], metadataWorks: false);

    actingAs($this->outsider)->postJson(route('grp.chat.staff.calls.recording.start', $call->ulid))->assertForbidden();
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.recording.start', $call->ulid))->assertUnprocessable();

    expect(\App\Models\Chat\StaffCallRecording::where('staff_call_id', $call->id)->count())->toBe(0)
        ->and(Http::recorded(fn ($request) => str_ends_with($request->url(), 'StartTrackEgress')))->toHaveCount(0);

    Config::set('services.livekit.recording', false);
    actingAs($this->user)->postJson(route('grp.chat.staff.calls.recording.start', $call->ulid))->assertNotFound();
});

test('someone who starts talking or shares their screen\'s sound while recording is recorded too', function () {
    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer();
    $recording = \App\Actions\Chat\Staff\Call\Recording\StartStaffCallRecording::run($call, $this->user);

    ReceiveLiveKitWebhook::run(['event' => 'track_published', 'room' => ['name' => $call->room], 'participant' => ['identity' => (string) $this->callee->id, 'name' => 'Ben'], 'track' => ['sid' => 'TR_screen', 'source' => 'SCREEN_SHARE_AUDIO']]);
    ReceiveLiveKitWebhook::run(['event' => 'track_published', 'room' => ['name' => $call->room], 'participant' => ['identity' => (string) $this->callee->id, 'name' => 'Ben'], 'track' => ['sid' => 'TR_cam', 'type' => 'VIDEO', 'source' => 'CAMERA']]);
    ReceiveLiveKitWebhook::run(['event' => 'track_published', 'room' => ['name' => $call->room], 'participant' => ['identity' => (string) $this->callee->id, 'name' => 'Ben'], 'track' => ['sid' => 'TR_screen', 'source' => 'SCREEN_SHARE_AUDIO']]);

    expect($recording->tracks()->pluck('speaker')->all())->toBe([$this->callee->chatName().' (screen)'])
        ->and(Http::recorded(fn ($request) => str_ends_with($request->url(), 'StartTrackEgress')))->toHaveCount(1);
});

test('after the call, each speaker is transcribed on their own and merged by time, the summary is sent to the people in the call, and the audio is deleted', function () {
    Storage::fake('staff-chat-r2');
    \Illuminate\Support\Facades\Process::fake(function (\Illuminate\Process\PendingProcess $process) {
        file_put_contents($process->path.'/piece-000.ogg', 'FIRST-PIECE');
        file_put_contents($process->path.'/piece-001.ogg', 'SECOND-PIECE');

        return \Illuminate\Support\Facades\Process::result();
    });
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->once()->andReturn('Sure: {"summary": "We agreed the samples ship Monday.", "decisions": ["Ship Monday"], "action_items": ["Ben: book the courier"]}');
    $sentTo = [];
    \App\Actions\Chat\Staff\SendStaffBotMessage::shouldRun()->twice()->andReturnUsing(function (User $user, string $title, string $body, string $url) use (&$sentTo) {
        $sentTo[] = [$user->id, $body, $url];

        return null;
    });

    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer([
        ['identity' => (string) $this->user->id, 'name' => 'Ana', 'tracks' => [['sid' => 'TR_mic1', 'source' => 'MICROPHONE']]],
        ['identity' => (string) $this->callee->id, 'name' => 'Ben', 'tracks' => [['sid' => 'TR_mic2', 'source' => 'MICROPHONE']]],
    ]);
    $recording = \App\Actions\Chat\Staff\Call\Recording\StartStaffCallRecording::run($call, $this->user);
    foreach ($recording->tracks as $track) {
        Storage::disk('staff-chat-r2')->put($track->path, 'ogg');
    }

    EndStaffCall::run($call);
    expect($recording->refresh()->status)->toBe('stopped');

    $startedNs = fn (int $seconds) => (string) ($recording->started_at->copy()->addSeconds($seconds)->getTimestampMs() * 1_000_000);
    $egressOf = $recording->tracks()->pluck('egress_id', 'track_sid');
    ReceiveLiveKitWebhook::run(['event' => 'egress_ended', 'egressInfo' => ['egressId' => $egressOf['TR_mic1'], 'status' => 'EGRESS_COMPLETE', 'fileResults' => [['startedAt' => $startedNs(0)]]]]);
    expect($recording->refresh()->status)->toBe('stopped');
    ReceiveLiveKitWebhook::run(['event' => 'egress_ended', 'egressInfo' => ['egressId' => $egressOf['TR_mic2'], 'status' => 'EGRESS_COMPLETE', 'fileResults' => [['startedAt' => $startedNs(10)]]]]);

    $recording->refresh();
    expect($recording->status)->toBe('done')
        ->and($recording->transcript)->toEqual([
            ['at' => 3, 'speaker' => $this->user->chatName(), 'text' => 'Hello'],
            ['at' => 13, 'speaker' => $this->callee->chatName(), 'text' => 'Hello'],
            ['at' => 1203, 'speaker' => $this->user->chatName(), 'text' => 'Later'],
            ['at' => 1213, 'speaker' => $this->callee->chatName(), 'text' => 'Later'],
        ])
        ->and($recording->summary)->toEqual(['summary' => 'We agreed the samples ship Monday.', 'decisions' => ['Ship Monday'], 'action_items' => ['Ben: book the courier']])
        ->and($recording->audio_deleted_at)->not->toBeNull()
        ->and(Storage::disk('staff-chat-r2')->allFiles($recording->audioDirectory()))->toBe([])
        ->and(collect($sentTo)->pluck(0)->sort()->values()->all())->toBe(collect([$this->user->id, $this->callee->id])->sort()->values()->all())
        ->and($sentTo[0][1])->toBe('We agreed the samples ship Monday.')
        ->and($sentTo[0][2])->toBe(route('grp.chat.staff.calls.recordings.show', $recording->ulid));
});

test('kept audio stays until its date and the sweep deletes it then; the transcript and summary stay', function () {
    Storage::fake('staff-chat-r2');
    $call      = recordingCallWithTwo($this);
    $recording = \App\Models\Chat\StaffCallRecording::create(['group_id' => $call->group_id, 'staff_call_id' => $call->id, 'status' => 'done', 'started_by_user_id' => $this->user->id, 'keep_audio_months' => 1, 'transcript' => [['at' => 0, 'speaker' => 'Ana', 'text' => 'Hi']], 'summary' => ['summary' => 'Hi'], 'delete_audio_at' => now()->addMonth()]);
    Storage::disk('staff-chat-r2')->put($recording->audioDirectory().'/a.ogg', 'ogg');

    \App\Actions\Chat\Staff\Call\Recording\SweepStaffCallRecordings::run();
    expect(Storage::disk('staff-chat-r2')->exists($recording->audioDirectory().'/a.ogg'))->toBeTrue();

    $this->travel(32)->days();
    \App\Actions\Chat\Staff\Call\Recording\SweepStaffCallRecordings::run();
    expect(Storage::disk('staff-chat-r2')->exists($recording->audioDirectory().'/a.ogg'))->toBeFalse()
        ->and($recording->refresh()->audio_deleted_at)->not->toBeNull()
        ->and($recording->transcript)->toEqual([['at' => 0, 'speaker' => 'Ana', 'text' => 'Hi']]);
});

test('only the people in the call can open its summary and transcript', function () {
    $call      = recordingCallWithTwo($this);
    $recording = \App\Models\Chat\StaffCallRecording::create(['group_id' => $call->group_id, 'staff_call_id' => $call->id, 'status' => 'done', 'started_by_user_id' => $this->user->id, 'transcript' => [['at' => 0, 'speaker' => 'Ana', 'text' => 'Secret plan']], 'summary' => ['summary' => 'Secret plan']]);

    actingAs($this->outsider)->get(route('grp.chat.staff.calls.recordings.show', $recording->ulid))->assertForbidden();
    actingAs($this->callee)->get(route('grp.chat.staff.calls.recordings.show', $recording->ulid))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffCallRecording', false)->where('recording.transcript.0.text', 'Secret plan'));
});

test('the recorder joining the room is never taken for an intruder', function () {
    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer();

    ReceiveLiveKitWebhook::run(['event' => 'participant_joined', 'room' => ['name' => $call->room], 'participant' => ['identity' => 'EG_abc123', 'kind' => 'EGRESS']]);
    ReceiveLiveKitWebhook::run(['event' => 'participant_joined', 'room' => ['name' => $call->room], 'participant' => ['identity' => 'EG_fake', 'kind' => 'STANDARD']]);

    expect(Http::recorded(fn ($request) => str_ends_with($request->url(), 'RemoveParticipant'))->map(fn ($pair) => $pair[0]['identity'])->values()->all())->toBe(['EG_fake']);
});

test('the red mark stays on until every recorder has really stopped', function () {
    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer([['identity' => (string) $this->user->id, 'name' => 'Ana', 'tracks' => [['sid' => 'TR_mic1', 'source' => 'MICROPHONE']]]], stopWorks: false);
    $recording = \App\Actions\Chat\Staff\Call\Recording\StartStaffCallRecording::run($call, $this->user);
    $cleared   = fn () => Http::recorded(fn ($request) => str_ends_with($request->url(), 'UpdateRoomMetadata') && $request['metadata'] === '')->count();

    actingAs($this->user)->deleteJson(route('grp.chat.staff.calls.recording.stop', $call->ulid))->assertOk();
    expect($recording->refresh()->status)->toBe('stopped')->and($cleared())->toBe(0);

    fakeRecordingCallServer();
    ReceiveLiveKitWebhook::run(['event' => 'egress_ended', 'egressInfo' => ['egressId' => $recording->tracks()->value('egress_id'), 'status' => 'EGRESS_COMPLETE']]);
    expect($cleared())->toBe(1);
});

test('nothing looks recorded when the calls box cannot record anyone', function () {
    $call = recordingCallWithTwo($this);
    fakeRecordingCallServer([['identity' => (string) $this->user->id, 'name' => 'Ana', 'tracks' => [['sid' => 'TR_mic1', 'source' => 'MICROPHONE']]]], egressWorks: false);

    actingAs($this->user)->postJson(route('grp.chat.staff.calls.recording.start', $call->ulid))->assertUnprocessable();

    expect(\App\Models\Chat\StaffCallRecording::where('staff_call_id', $call->id)->count())->toBe(0)
        ->and(Http::recorded(fn ($request) => str_ends_with($request->url(), 'UpdateRoomMetadata'))->map(fn ($pair) => $pair[0]['metadata'])->last())->toBe('');
});

test('a guest is named as a guest in the transcript, whatever name they typed', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];
    [$guest] = meetingGuest($meeting);
    \App\Actions\Chat\Staff\Meeting\DecideStaffMeetingGuest::run($call, $guest, $this->user, 'admit');
    fakeRecordingCallServer([['identity' => $guest->identity(), 'name' => $this->callee->chatName(), 'tracks' => [['sid' => 'TR_guest', 'source' => 'MICROPHONE']]]]);

    $recording = \App\Actions\Chat\Staff\Call\Recording\StartStaffCallRecording::run($call->refresh(), $this->user);

    expect($recording->tracks()->value('speaker'))->toBe($guest->refresh()->name.' (guest)');
    EndStaffCall::run($call);
});

test('the summary is for those in the call while it was recorded: not who left before Record and came back after Stop, nor who first came after Stop', function () {
    $meeting = \App\Actions\Chat\Staff\Meeting\StoreStaffMeeting::run($this->user);
    $call    = \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->user)['call'];
    \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->outsider);
    \App\Actions\Chat\Staff\Call\LeaveStaffCall::run($call, $this->outsider);
    $this->travel(1)->minutes();
    fakeRecordingCallServer();

    $recording = \App\Actions\Chat\Staff\Call\Recording\StartStaffCallRecording::run($call->refresh(), $this->user);
    \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->callee);
    \App\Actions\Chat\Staff\Call\Recording\StopStaffCallRecording::run($call, $this->user);
    $this->travel(1)->minutes();
    \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $this->outsider);
    $latecomer = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => true]);
    \App\Actions\Chat\Staff\Meeting\JoinStaffMeeting::run($meeting, $latecomer);

    $recording->refresh();
    expect($recording->peopleRecorded()->pluck('users.id')->sort()->values()->all())->toBe(collect([$this->user->id, $this->callee->id])->sort()->values()->all())
        ->and($recording->isVisibleTo($this->outsider))->toBeFalse()
        ->and($recording->isVisibleTo($latecomer))->toBeFalse()
        ->and($recording->isVisibleTo($this->callee))->toBeTrue()
        ->and($recording->isVisibleTo($this->user))->toBeTrue();
    EndStaffCall::run($call);
});
