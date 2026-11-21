<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\MarkStaffConversationRead;
use App\Actions\Chat\Staff\MarkStaffConversationsDelivered;
use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\Staff\StoreStaffConversation;
use App\Actions\Chat\Staff\TranslateStaffMessage;
use App\Events\StaffConversationDelivered;
use App\Events\StaffConversationRead;
use App\Events\StaffConversationTyping;
use App\Events\StaffMessageSent;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ChatHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user) = createShop();
    $this->user->update(['status' => true]);

    $makeColleague = fn () => User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'status' => true]);
    $this->other    = $makeColleague();
    $this->outsider = $makeColleague();

    Event::fake([StaffMessageSent::class, StaffConversationTyping::class, StaffConversationDelivered::class, StaffConversationRead::class]);
    fakeActionJobs(TranslateStaffMessage::class);

    $this->dm = StoreStaffConversation::run($this->user, ['user_ids' => [$this->other->id]]);
});

function participantRow($conversation, User $user): object
{
    return DB::table('staff_conversation_participants')->where('staff_conversation_id', $conversation->id)->where('user_id', $user->id)->first();
}

test('typing is broadcast to the other participants only, with no text, and stores nothing', function () {
    $before = participantRow($this->dm, $this->user);

    actingAs($this->user)->postJson(route('grp.chat.staff.conversations.typing', $this->dm->ulid), ['body' => 'secret draft'])->assertOk();

    Event::assertDispatched(StaffConversationTyping::class, function (StaffConversationTyping $event) {
        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        return $channels === ['private-grp.personal.'.$this->other->id]
            && $event->broadcastAs() === 'staff-typing'
            && $event->broadcastWith() === ['conversation_ulid' => $this->dm->ulid, 'user_id' => $this->user->id, 'user_name' => $this->user->chatName()];
    });
    expect((array) participantRow($this->dm, $this->user))->toBe((array) $before);
});

test('typing is refused to someone outside the conversation and to someone who left it', function () {
    actingAs($this->outsider)->postJson(route('grp.chat.staff.conversations.typing', $this->dm->ulid))->assertForbidden();

    $this->dm->removeParticipants([$this->other->id]);
    actingAs($this->other)->postJson(route('grp.chat.staff.conversations.typing', $this->dm->ulid))->assertForbidden();

    Event::assertNotDispatched(StaffConversationTyping::class);
});

test('typing has its own rate limit', function () {
    $statuses = collect(range(1, 31))->map(fn () => actingAs($this->user)->postJson(route('grp.chat.staff.conversations.typing', $this->dm->ulid))->status());

    expect($statuses->take(30)->unique()->all())->toBe([200])
        ->and($statuses->last())->toBe(429);
});

test('the receiving browser acknowledges a burst in one request; the sender is told, and a repeat changes nothing', function () {
    SendStaffMessage::run($this->dm, $this->user, ['body' => 'hello']);

    actingAs($this->other)->postJson(route('grp.chat.staff.conversations.delivered'), ['conversation_ulids' => [$this->dm->ulid, $this->dm->ulid]])
        ->assertOk()->assertJsonPath('delivered', [$this->dm->ulid]);

    expect(participantRow($this->dm, $this->other)->last_delivered_at)->not->toBeNull();
    Event::assertDispatchedTimes(StaffConversationDelivered::class, 1);
    Event::assertDispatched(StaffConversationDelivered::class, fn (StaffConversationDelivered $event) => collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all() === ['private-grp.personal.'.$this->user->id]
        && $event->broadcastAs() === 'staff-conversation-delivered'
        && $event->broadcastWith()['user_id'] === $this->other->id);

    actingAs($this->other)->postJson(route('grp.chat.staff.conversations.delivered'), ['conversation_ulids' => [$this->dm->ulid]])
        ->assertOk()->assertJsonPath('delivered', []);
    Event::assertDispatchedTimes(StaffConversationDelivered::class, 1);
});

test('nobody can acknowledge a conversation they are not in, or their own messages, and the batch is capped', function () {
    SendStaffMessage::run($this->dm, $this->user, ['body' => 'hello']);

    expect(MarkStaffConversationsDelivered::run($this->outsider, [$this->dm->ulid]))->toBe([])
        ->and(MarkStaffConversationsDelivered::run($this->user, [$this->dm->ulid]))->toBe([]);

    actingAs($this->other)->postJson(route('grp.chat.staff.conversations.delivered'), ['conversation_ulids' => array_fill(0, MarkStaffConversationsDelivered::MAX_CONVERSATIONS + 1, 'x')])
        ->assertUnprocessable();
    Event::assertNotDispatched(StaffConversationDelivered::class);
});

test('reading marks the conversation received too, and the read broadcast still goes out', function () {
    SendStaffMessage::run($this->dm, $this->user, ['body' => 'hello']);

    MarkStaffConversationRead::run($this->dm, $this->other);

    $row = participantRow($this->dm, $this->other);
    expect($row->last_delivered_at)->toBe($row->last_read_at)
        ->and(MarkStaffConversationsDelivered::run($this->other, [$this->dm->ulid]))->toBe([]);
    Event::assertDispatched(StaffConversationRead::class);
});

test('the conversation list gives each participant their received time and says who wrote the last message', function () {
    SendStaffMessage::run($this->dm, $this->user, ['body' => 'hello']);
    MarkStaffConversationsDelivered::run($this->other, [$this->dm->ulid]);

    $conversation = collect(actingAs($this->user)->getJson(route('grp.chat.staff.conversations.index'))->json('data'))->firstWhere('ulid', $this->dm->ulid);
    $other        = collect($conversation['participants'])->firstWhere('id', $this->other->id);

    expect($conversation['last_message_user_id'])->toBe($this->user->id)
        ->and($other['last_delivered_at'])->not->toBeNull()
        ->and($other['last_read_at'])->toBeNull();
});

test('acknowledging received messages has its own rate limit', function () {
    $statuses = collect(range(1, 61))->map(fn () => actingAs($this->other)->postJson(route('grp.chat.staff.conversations.delivered'), ['conversation_ulids' => [$this->dm->ulid]])->status());

    expect($statuses->last())->toBe(429);
});
