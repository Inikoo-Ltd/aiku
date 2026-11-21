<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ChatHelpers.php';

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

    \Illuminate\Support\Facades\Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $this->s        = Str::upper(Str::random(6));
    $this->group    = $this->organisation->group;
    $newUser        = fn () => User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    $this->viewer   = $newUser();
    $this->stranger = $newUser();
    SlackUser::create(['slack_id' => "UVIEW{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'Viewer Person']);
    SlackUser::create(['slack_id' => "UANN{$this->s}", 'real_name' => 'Ann Author']);

    $this->channel = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLIVE{$this->s}", 'name' => "live-{$this->s}", 'is_member' => true]);
    $this->hidden  = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLIVEIGN{$this->s}", 'name' => "ignored-{$this->s}", 'is_ignored' => true]);

    $start      = now()->subHour()->startOfMinute();
    $this->ts   = fn (int $i) => sprintf('%d.%06d', $start->timestamp + $i * 60, $i);
    $this->post = fn (int $i, array $extra = [], ?SlackChannel $channel = null) => StoreSlackMessage::make()->handle(array_merge([
        'type'         => 'message',
        'channel'      => ($channel ?? $this->channel)->slack_id,
        'channel_type' => 'channel',
        'user'         => "UANN{$this->s}",
        'text'         => "Message $i",
        'ts'           => ($this->ts)($i),
    ], $extra));
    $this->unread = function (User $user): array {
        $counts = [];
        actingAs($user)->get(route('grp.chat.staff.slack.index'))->assertOk()
            ->assertInertia(function (AssertableInertia $inertia) use (&$counts) {
                $counts = collect($inertia->toArray()['props']['slack_channels'])->keyBy('id')->all();
            });

        return $counts;
    };
});

test('Slack live: a webhook message, edit, deletion and reaction broadcast to the group Slack channel, which only people who may see Slack channels join; nothing for an ignored or gone channel or a history sync, INI-440', function () {
    Event::fake([SlackChannelMessageChanged::class]);
    $ts = $this->ts;

    ($this->post)(1);
    ($this->post)(1);
    ($this->post)(2, ['thread_ts' => $ts(1)]);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'message' => ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => 'Message 1 edited', 'ts' => $ts(1), 'thread_ts' => $ts(1), 'edited' => ['ts' => $ts(3)]]]);
    StoreSlackMessageReaction::make()->handle(['type' => 'reaction_added', 'user' => "UVIEW{$this->s}", 'reaction' => 'tada', 'item' => ['type' => 'message', 'channel' => $this->channel->slack_id, 'ts' => $ts(1)], 'event_ts' => $ts(4)]);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'deleted_ts' => $ts(2)]);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'deleted_ts' => $ts(2)]);

    $events = Event::dispatched(SlackChannelMessageChanged::class)->map(fn (array $args) => $args[0]);
    expect($events->map(fn (SlackChannelMessageChanged $event) => $event->kind.':'.$event->message->ts)->all())
        ->toBe(['new:'.$ts(1), 'changed:'.$ts(1), 'new:'.$ts(2), 'changed:'.$ts(1), 'reaction:'.$ts(1), 'deleted:'.$ts(2)])
        ->and($events->every(fn (SlackChannelMessageChanged $event) => $event->broadcastWhen()))->toBeTrue()
        ->and($events->first()->broadcastOn()[0]->name)->toBe('private-grp.slack.'.$this->group->id);

    $reply = $events->firstWhere('kind', 'new')->broadcastAs();
    expect($reply)->toBe('slack-message');

    $reaction = $events->firstWhere('kind', 'reaction')->broadcastWith();
    expect($reaction['channel_id'])->toBe($this->channel->id)
        ->and($reaction['message']['ts'])->toBe($ts(1))
        ->and($reaction['message']['reactions'][0]['name'])->toBe('tada')
        ->and($reaction['reactors'])->toBe(['tada' => [$this->viewer->id]])
        ->and($reaction['message']['my_reactions'])->toBe([])
        ->and($reaction['parent'])->toBeNull()
        ->and(json_encode($reaction))->not->toContain('"data"');

    $deleted = $events->last()->broadcastWith();
    expect($deleted['message']['is_deleted'])->toBeTrue()
        ->and($deleted['message']['nodes'])->toBe([])
        ->and($deleted['parent']['ts'])->toBe($ts(1))
        ->and($deleted['parent']['reply_count'])->toBe(0);

    $authorise = Broadcast::driver()->getChannels()['grp.slack.{groupId}'];
    $otherGroup = Group::where('id', '!=', $this->group->id)->value('id') ?? $this->group->id + 1000;
    expect($authorise($this->viewer, $this->group->id))->toBeTrue()
        ->and($authorise($this->stranger, $this->group->id))->toBeFalse()
        ->and($authorise($this->viewer, $otherGroup))->toBeFalse();
    $this->viewer->update(['status' => false]);
    expect($authorise($this->viewer->fresh(), $this->group->id))->toBeFalse();

    Event::fake([SlackChannelMessageChanged::class]);
    ($this->post)(5, [], $this->hidden);
    ($this->post)(6, ['channel_type' => 'group']);
    StoreSlackMessage::make()->store($this->channel, ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => 'From the history', 'ts' => $ts(7)]);
    StoreSlackMessage::make()->store($this->channel, ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => 'From the history, edited', 'ts' => $ts(7), 'edited' => ['ts' => $ts(8)], 'reactions' => [['name' => 'eyes', 'users' => ["UANN{$this->s}"], 'count' => 1]]]);
    Event::assertNotDispatched(SlackChannelMessageChanged::class);
    expect(SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(7))->exists())->toBeTrue();

    $this->channel->update(['is_public' => false]);
    ($this->post)(10);
    StoreSlackMessageReaction::make()->handle(['type' => 'reaction_added', 'user' => "UANN{$this->s}", 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => $this->channel->slack_id, 'ts' => $ts(1)], 'event_ts' => $ts(10)]);
    Event::assertNotDispatched(SlackChannelMessageChanged::class);
    expect(SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(10))->exists())->toBeFalse();

    $this->channel->update(['is_public' => true, 'deleted_in_slack_at' => now()]);
    StoreSlackMessageReaction::make()->handle(['type' => 'reaction_added', 'user' => "UANN{$this->s}", 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => $this->channel->slack_id, 'ts' => $ts(1)], 'event_ts' => $ts(9)]);
    Event::assertNotDispatched(SlackChannelMessageChanged::class);
    expect(\App\Models\Chat\SlackMessageReaction::where('slack_message_id', SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(1))->value('id'))->where('name', 'eyes')->exists())->toBeFalse();
});

test('Slack unread: a first sight starts read, counts skip own, system, thread, deleted and repeated messages but keep bots, the divider position, mark read only forward, mark all, refused when the channel is not visible, INI-443', function () {
    Event::fake([SlackChannelMessageChanged::class]);
    $ts = $this->ts;
    ($this->post)(1);
    $empty = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLIVEEMPTY{$this->s}", 'name' => "empty-{$this->s}", 'is_member' => true]);

    $first = ($this->unread)($this->viewer);
    StoreSlackMessage::make()->store($empty, ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => 'Old history', 'ts' => $ts(2)]);
    expect(($this->unread)($this->viewer)[$empty->id]['unread_count'])->toBe(0)
        ->and($first[$this->channel->id]['unread_count'])->toBe(0)
        ->and($first[$this->channel->id]['last_read_ts'])->toBe($ts(1))
        ->and($first)->not->toHaveKey($this->hidden->id);

    ($this->post)(2);
    ($this->post)(2);
    ($this->post)(3, ['user' => null, 'subtype' => 'bot_message', 'bot_id' => 'B1']);
    ($this->post)(4, ['user' => "UVIEW{$this->s}"]);
    ($this->post)(5, ['subtype' => 'channel_join']);
    ($this->post)(6, ['thread_ts' => $ts(2)]);
    ($this->post)(7, ['thread_ts' => $ts(2), 'subtype' => 'thread_broadcast']);
    ($this->post)(8);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'deleted_ts' => $ts(8)]);
    ($this->post)(9, [], $this->hidden);
    DB::table('slack_channel_reads')->insert(['user_id' => $this->viewer->id, 'slack_channel_id' => $this->hidden->id, 'last_read_ts' => '0', 'created_at' => now(), 'updated_at' => now()]);

    $counts = ($this->unread)($this->viewer);
    expect($counts[$this->channel->id]['unread_count'])->toBe(3)
        ->and($counts)->not->toHaveKey($this->hidden->id);

    actingAs($this->viewer)->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->channel->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('slack_channel.last_read_ts', $ts(1))->where('slack_live_channel', 'grp.slack.'.$this->group->id));

    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]), ['ts' => $ts(3)])->assertOk();
    expect(($this->unread)($this->viewer)[$this->channel->id]['unread_count'])->toBe(1);
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]), ['ts' => $ts(1)])->assertOk();
    expect(($this->unread)($this->viewer)[$this->channel->id])->toMatchArray(['unread_count' => 1, 'last_read_ts' => $ts(3)]);
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]), ['ts' => 'javascript:1'])->assertUnprocessable();
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]))->assertOk();
    expect(($this->unread)($this->viewer)[$this->channel->id])->toMatchArray(['unread_count' => 0, 'last_read_ts' => $ts(8)]);

    ($this->post)(10);
    expect(($this->unread)($this->viewer)[$this->channel->id]['unread_count'])->toBe(1);
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read-all'))->assertOk();
    expect(($this->unread)($this->viewer)[$this->channel->id]['unread_count'])->toBe(0)
        ->and(DB::table('slack_channel_reads')->where('user_id', $this->viewer->id)->where('slack_channel_id', $this->hidden->id)->value('last_read_ts'))->toBe('0');

    actingAs($this->stranger)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]))->assertForbidden();
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->hidden->id]))->assertForbidden();
    actingAs($this->stranger)->postJson(route('grp.chat.staff.slack.read-all'))->assertOk();
    expect(DB::table('slack_channel_reads')->where('user_id', $this->stranger->id)->exists())->toBeFalse();
});

test('Slack live hardening: an edit of a message aiku never held is a change and notifies nobody, a failing search refresh, broadcast or notification keeps the message, a channel deleted in Slack stores nothing, the read position is clamped, partial reloads skip the read queries, INI-440 INI-443', function () {
    Event::fake([SlackChannelMessageChanged::class]);
    $ts       = $this->ts;
    $notified = [];
    \App\Actions\Chat\Staff\Slack\NotifySlackMentions::mock()->shouldReceive('handle')->andReturnUsing(function (SlackMessage $message) use (&$notified) {
        $notified[] = $message->ts;
    });

    ($this->post)(1);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'message' => ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => "Hi <@UVIEW{$this->s}>", 'ts' => $ts(2), 'edited' => ['ts' => $ts(3)]]]);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'message' => ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => "Message 1 <@UVIEW{$this->s}>", 'ts' => $ts(1), 'edited' => ['ts' => $ts(4)]]]);

    expect(Event::dispatched(SlackChannelMessageChanged::class)->map(fn (array $args) => $args[0]->kind.':'.$args[0]->message->ts)->all())
        ->toBe(['new:'.$ts(1), 'changed:'.$ts(2), 'changed:'.$ts(1)])
        ->and($notified)->toBe([$ts(1), $ts(1)])
        ->and(SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(2))->exists())->toBeTrue();

    \App\Actions\Chat\Staff\Slack\RefreshSlackMessageSearch::mock()->shouldReceive('handle')->andReturnUsing(fn () => DB::statement('SELECT * FROM no_such_table_for_slack_search'));
    expect(($this->post)(5)?->ts)->toBe($ts(5))
        ->and(SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(5))->exists())->toBeTrue();

    \App\Actions\Chat\Staff\Slack\NotifySlackMentions::mock()->shouldReceive('handle')->andThrow(new RuntimeException('notify failed'));
    Event::listen(SlackChannelMessageChanged::class, fn () => throw new RuntimeException('soketi down'));
    expect(($this->post)(6)?->ts)->toBe($ts(6))
        ->and(SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(6))->exists())->toBeTrue();

    $gone = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLIVEGONE{$this->s}", 'name' => "gone-{$this->s}", 'deleted_in_slack_at' => now()]);
    expect(($this->post)(7, [], $gone))->toBeNull()
        ->and(SlackMessage::where('slack_channel_id', $gone->id)->exists())->toBeFalse();

    ($this->unread)($this->viewer);
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]), ['ts' => '9.0'])->assertUnprocessable();
    actingAs($this->viewer)->postJson(route('grp.chat.staff.slack.read', ['slackChannel' => $this->channel->id]), ['ts' => '9999999999.000000'])->assertOk();
    expect(($this->unread)($this->viewer)[$this->channel->id]['last_read_ts'])->toBe($ts(6));
    ($this->post)(8);
    expect(($this->unread)($this->viewer)[$this->channel->id]['unread_count'])->toBe(1);

    $newcomer = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    SlackUser::create(['slack_id' => "UNEW{$this->s}", 'user_id' => $newcomer->id, 'email' => $newcomer->email, 'real_name' => 'New Person']);
    actingAs($newcomer)->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => \Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Chat/StaffMessaging',
        'X-Inertia-Partial-Data'      => 'slack_messages',
    ])->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->channel->id]))->assertOk();
    expect(DB::table('slack_channel_reads')->where('user_id', $newcomer->id)->exists())->toBeFalse();

    $this->flushHeaders();
    actingAs($this->stranger)->get(route('grp.chat.staff.slack.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('slack_live_channel', null)->where('slack_channels', []));
});
