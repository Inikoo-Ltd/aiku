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

function slackDirectSetup(object $test): void
{
    \Illuminate\Support\Facades\Config::set('services.slack.signing_secret', 'shh');
    \Illuminate\Support\Facades\Config::set('services.slack.team_id', 'TDM');
    $test->bee = User::factory()->create(['group_id' => $test->group->id, 'language_id' => $test->user->language_id]);
    SlackUser::create(['slack_id' => "UBEE{$test->s}", 'user_id' => $test->bee->id, 'email' => $test->bee->email, 'real_name' => 'Bee Person']);
    $test->connectPrivately = function (User $user, string $slackId): \App\Models\Chat\SlackConnection {
        $connection = \App\Models\Chat\SlackConnection::updateOrCreate(['user_id' => $user->id], [
            'slack_user_id' => $slackId,
            'team_id'       => 'TDM',
            'token'         => 'xoxp-test-'.$slackId,
            'scopes'        => implode(',', [...\App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess::USER_SCOPES, ...\App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess::PRIVATE_SCOPES]),
            'connected_at'  => now(),
            'revoked_at'    => null,
        ]);
        $connection->grantPrivateConsent(\App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess::PRIVATE_CONSENT_VERSION, \App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess::PRIVATE_TERMS_VERSION);

        return $connection;
    };
    $test->webhook = function (array $event, string $authorizedBy, bool $isBot = false) use ($test) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event, 'authorizations' => [['team_id' => 'TDM', 'user_id' => $authorizedBy, 'is_bot' => $isBot]]]);
        $timestamp = (string) time();
        $server    = ['HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp, 'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'), 'CONTENT_TYPE' => 'application/json'];

        return $test->call('POST', route('webhooks.slack_events'), [], [], [], $server, $body);
    };
    $test->dm = fn (string $author, int $i, array $extra = []) => array_merge([
        'type' => 'message', 'channel' => "DDM{$test->s}", 'channel_type' => 'im', 'user' => $author, 'text' => "secret words $i of $author", 'ts' => ($test->ts)($i),
    ], $extra);
}

test('Slack DMs: an event delivered through A\'s token keeps A\'s words and stores B\'s message as a gap with no text, data or files; bot-delivered, repeated and bot-authored events add nothing; B gets one invite; members read it, nobody else on any path, INI-431', function () {
    slackDirectSetup($this);
    $a = "UVIEW{$this->s}";
    $b = "UBEE{$this->s}";
    ($this->connectPrivately)($this->viewer, $a);

    ($this->webhook)(($this->dm)($b, 1, ['files' => [['id' => "FDM{$this->s}", 'name' => 'payslip.pdf']]]), $a)->assertOk();
    ($this->webhook)(($this->dm)($b, 1), $a)->assertOk();
    ($this->webhook)(($this->dm)($a, 2, ['subtype' => 'thread_broadcast', 'thread_ts' => ($this->ts)(1), 'root' => ['text' => "root words of $b"], 'attachments' => [['is_share' => true, 'text' => "shared words of $b"], ['title' => 'link preview']]]), $a)->assertOk();
    ($this->webhook)(($this->dm)($b, 3), $a, isBot: true)->assertOk();
    ($this->webhook)(($this->dm)($b, 4), "UNOBODY{$this->s}")->assertOk();
    ($this->webhook)(($this->dm)('USLACKBOT', 5, ['bot_id' => 'B1']), $a)->assertOk();
    ($this->webhook)(($this->dm)($b, 6), $a)->assertOk();
    ($this->webhook)(['type' => 'message', 'subtype' => 'message_changed', 'channel' => "DDM{$this->s}", 'channel_type' => 'im', 'message' => ($this->dm)($b, 6, ['text' => 'edited secret', 'edited' => ['ts' => ($this->ts)(7)]])], $a)->assertOk();

    $dm   = SlackChannel::where('slack_id', "DDM{$this->s}")->sole();
    $rows = SlackMessage::where('slack_channel_id', $dm->id)->orderBy('ts')->get();
    expect($dm->only(['direct_type', 'is_private', 'is_public', 'is_member']))->toBe(['direct_type' => 'im', 'is_private' => true, 'is_public' => false, 'is_member' => false])
        ->and($rows->pluck('ts')->all())->toBe([($this->ts)(1), ($this->ts)(2), ($this->ts)(6)])
        ->and($rows[0]->only(['slack_user_id', 'user_id', 'text', 'data', 'subtype']))->toBe(['slack_user_id' => $b, 'user_id' => $this->bee->id, 'text' => null, 'data' => null, 'subtype' => null])
        ->and($rows[2]->text)->toBeNull()
        ->and($rows[2]->data)->toBeNull()
        ->and($rows[2]->versions()->count())->toBe(0)
        ->and($rows[1]->text)->toBe("secret words 2 of $a")
        ->and($rows[1]->data)->not->toHaveKey('root')
        ->and($rows[1]->data['attachments'])->toBe([['title' => 'link preview']])
        ->and(SlackChannel::kept()->whereKey($dm->id)->exists())->toBeFalse()
        ->and(SlackChannel::keptPrivate()->whereKey($dm->id)->exists())->toBeFalse()
        ->and(\Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $this->bee->id)->where('type', \App\Notifications\SlackDirectInviteNotification::class)->count())->toBe(1)
        ->and(\Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $this->viewer->id)->where('type', \App\Notifications\SlackDirectInviteNotification::class)->count())->toBe(0)
        ->and(\App\Actions\Chat\Staff\Slack\StoreSlackMessage::make()->handle(($this->dm)($b, 8)))->toBeNull();

    actingAs($this->viewer)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.is_direct', true)->where('slack_channel.write', null)->where('slack_channel.name', 'Bee Person'));
    $stream = slackDirectStream($dm, $this->viewer);
    expect(collect($stream)->pluck('is_gap', 'ts')->all())->toBe([($this->ts)(6) => true, ($this->ts)(2) => false, ($this->ts)(1) => true])
        ->and(collect($stream)->firstWhere('ts', ($this->ts)(1))['files'])->toBe([]);
    actingAs($this->viewer)->get(route('grp.chat.staff.slack.show', $dm))->assertRedirect(route('grp.chat.staff.direct.slack.show', $dm));
    actingAs($this->bee)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertOk();

    $props = function (User $user): array {
        $props = [];
        actingAs($user)->get(route('grp.chat.staff.slack.index'))->assertOk()
            ->assertInertia(function (AssertableInertia $inertia) use (&$props) {
                $props = $inertia->toArray()['props'];
            });

        return $props;
    };
    expect(collect($props($this->viewer)['slack_direct_channels'])->pluck('id')->all())->toContain($dm->id)
        ->and(collect($props($this->viewer)['slack_channels'])->pluck('id')->all())->not->toContain($dm->id);

    $stranger = $this->stranger;
    SlackUser::create(['slack_id' => "USTR{$this->s}", 'user_id' => $stranger->id, 'email' => $stranger->email, 'real_name' => 'Stranger']);
    $strangerProps = $props($stranger);
    expect(collect([...$strangerProps['slack_direct_channels'], ...$strangerProps['slack_private_channels'], ...$strangerProps['slack_channels']])->pluck('id')->all())->not->toContain($dm->id);
    actingAs($stranger)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertForbidden();
    actingAs($stranger)->get(route('grp.chat.staff.slack.show', $dm))->assertForbidden();
    actingAs($stranger)->get(route('grp.chat.staff.groups.slack.show', $dm))->assertForbidden();
    actingAs($stranger)->getJson(route('grp.chat.staff.slack.thread', [$dm, 'ts' => ($this->ts)(2)]))->assertForbidden();
    actingAs($stranger)->getJson(route('grp.chat.staff.slack.versions', [$dm, 'ts' => ($this->ts)(2)]))->assertNotFound();
    actingAs($stranger)->getJson(route('grp.chat.staff.slack.pins', $dm))->assertForbidden();
    actingAs($stranger)->getJson(route('grp.chat.staff.slack.files-links', $dm))->assertForbidden();
    actingAs($stranger)->postJson(route('grp.chat.staff.slack.read', $dm), ['ts' => ($this->ts)(2)])->assertForbidden();
    foreach ([$stranger, $this->viewer] as $searcher) {
        expect(actingAs($searcher)->getJson(route('grp.chat.staff.slack.search', ['q' => 'secret words']))->assertOk()->json('data'))->toBe([]);
    }
});

test('Slack DMs: B connecting fills B\'s gaps with B\'s own token and keeps the others\' text out; live events by B then keep B\'s words; nothing is duplicated, INI-431', function () {
    slackDirectSetup($this);
    $a = "UVIEW{$this->s}";
    $b = "UBEE{$this->s}";
    ($this->connectPrivately)($this->viewer, $a);
    ($this->webhook)(($this->dm)($b, 1), $a)->assertOk();
    ($this->webhook)(($this->dm)($b, 2, ['thread_ts' => ($this->ts)(1)]), $a)->assertOk();
    $dm = SlackChannel::where('slack_id', "DDM{$this->s}")->sole();

    \Illuminate\Support\Facades\Http::fake([
        'slack.com/api/conversations.members' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'members' => [$a, $b]]),
        'slack.com/api/conversations.history' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'messages' => [($this->dm)($b, 1), ($this->dm)($a, 9, ['text' => 'A text seen through B'])]]),
        'slack.com/api/conversations.replies' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'messages' => [($this->dm)($b, 1), ($this->dm)($b, 2, ['thread_ts' => ($this->ts)(1)])]]),
    ]);
    ($this->connectPrivately)($this->bee, $b);
    $totals = \App\Actions\Chat\Staff\Slack\FillSlackDirectGaps::run($this->bee);
    \App\Actions\Chat\Staff\Slack\FillSlackDirectGaps::run($this->bee);

    $rows = SlackMessage::where('slack_channel_id', $dm->id)->orderBy('ts')->get();
    expect($totals)->toBe(['channels' => 1, 'filled' => 2, 'complete' => true])
        ->and($rows->pluck('text', 'ts')->all())->toBe([($this->ts)(1) => "secret words 1 of $b", ($this->ts)(2) => "secret words 2 of $b"])
        ->and($rows->every(fn (SlackMessage $row) => $row->versions()->doesntExist()))->toBeTrue()
        ->and($rows[1]->thread_ts)->toBe(($this->ts)(1));
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'conversations.history') && $request->header('Authorization')[0] === "Bearer xoxp-test-$b");

    ($this->webhook)(($this->dm)($b, 3), $a)->assertOk();
    ($this->webhook)(($this->dm)($b, 3), $b)->assertOk();
    expect(SlackMessage::where('slack_channel_id', $dm->id)->where('ts', ($this->ts)(3))->sole()->text)->toBe("secret words 3 of $b");
});

test('Slack DMs: B switching off truly deletes B\'s DM words and earlier versions, deleted ones included, and they become gaps; A\'s words stay; deactivating a leaver deletes nothing, INI-431', function () {
    slackDirectSetup($this);
    $a = "UVIEW{$this->s}";
    $b = "UBEE{$this->s}";
    ($this->connectPrivately)($this->viewer, $a);
    ($this->connectPrivately)($this->bee, $b);
    ($this->webhook)(($this->dm)($b, 1), $a)->assertOk();
    ($this->webhook)(['type' => 'message', 'subtype' => 'message_changed', 'channel' => "DDM{$this->s}", 'channel_type' => 'im', 'message' => ($this->dm)($b, 1, ['text' => 'second wording', 'edited' => ['ts' => ($this->ts)(2)]])], $a)->assertOk();
    ($this->webhook)(($this->dm)($b, 3), $a)->assertOk();
    ($this->webhook)(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => "DDM{$this->s}", 'channel_type' => 'im', 'deleted_ts' => ($this->ts)(3)], $a)->assertOk();
    ($this->webhook)(($this->dm)($a, 4), $a)->assertOk();
    $dm      = SlackChannel::where('slack_id', "DDM{$this->s}")->sole();
    $edited  = SlackMessage::where('slack_channel_id', $dm->id)->where('ts', ($this->ts)(1))->sole();
    expect($edited->text)->toBe('second wording')
        ->and($edited->versions()->count())->toBe(1)
        ->and(SlackMessage::onlyTrashed()->where('slack_channel_id', $dm->id)->where('ts', ($this->ts)(3))->value('text'))->toBe("secret words 3 of $b");

    \Illuminate\Support\Facades\Http::fake(['slack.com/api/auth.revoke' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);
    actingAs($this->bee)->withHeaders(['X-Inertia' => 'true'])->delete(route('grp.chat.staff.slack.connect.private.delete'));

    $rows = SlackMessage::withTrashed()->where('slack_channel_id', $dm->id)->orderBy('ts')->get()->keyBy('ts');
    expect($rows[($this->ts)(1)]->only(['text', 'data', 'slack_user_id']))->toBe(['text' => null, 'data' => null, 'slack_user_id' => $b])
        ->and($rows[($this->ts)(1)]->versions()->count())->toBe(0)
        ->and($rows[($this->ts)(3)]->text)->toBeNull()
        ->and($rows[($this->ts)(3)]->trashed())->toBeTrue()
        ->and($rows[($this->ts)(4)]->text)->toBe("secret words 4 of $a")
        ->and(collect(slackDirectStream($dm, $this->viewer))->firstWhere('ts', ($this->ts)(1))['is_gap'])->toBeTrue();

    ($this->webhook)(($this->dm)($b, 5), $a)->assertOk();
    expect(SlackMessage::where('slack_channel_id', $dm->id)->where('ts', ($this->ts)(5))->value('text'))->toBeNull();

    \App\Actions\Chat\Staff\Slack\Write\DisconnectSlackAccount::run($this->viewer, false);
    expect(SlackMessage::where('slack_channel_id', $dm->id)->where('ts', ($this->ts)(4))->value('text'))->toBe("secret words 4 of $a");

    actingAs($this->viewer)->delete(route('grp.chat.staff.slack.connect.delete'))->assertRedirect();
    expect(SlackMessage::where('slack_channel_id', $dm->id)->where('ts', ($this->ts)(4))->value('text'))->toBeNull();
});

function slackDirectHistoryFake(object $test, array $historyByChannel, ?Closure $onHistory = null): void
{
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Sleep::fake();
    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) use ($test, $historyByChannel, $onHistory) {
        $method  = \Illuminate\Support\Str::afterLast(parse_url($request->url(), PHP_URL_PATH), '/');
        $data    = $request->data();
        $channel = $data['channel'] ?? null;
        $test->slackCalls[] = [$method, $channel, $request->header('Authorization')[0] ?? null];
        if ($method === 'users.conversations') {
            return \Illuminate\Support\Facades\Http::response(['ok' => true, 'channels' => array_map(fn (string $id) => ['id' => $id, str_starts_with($id, 'G') ? 'is_mpim' : 'is_im' => true], array_keys($historyByChannel))]);
        }
        if ($method === 'conversations.members') {
            return \Illuminate\Support\Facades\Http::response(['ok' => true, 'members' => ["UVIEW{$test->s}", "UBEE{$test->s}"]]);
        }
        if ($method === 'conversations.history' && $onHistory && ($answer = $onHistory($channel, $data))) {
            return $answer;
        }
        if ($method === 'conversations.history') {
            return \Illuminate\Support\Facades\Http::response(['ok' => true, 'messages' => $historyByChannel[$channel] ?? []]);
        }
        if ($method === 'conversations.replies') {
            return \Illuminate\Support\Facades\Http::response(['ok' => true, 'messages' => collect($historyByChannel[$channel] ?? [])->where('thread_ts', $data['ts'])->values()->all()]);
        }

        return \Illuminate\Support\Facades\Http::response(['ok' => true]);
    });
}

test('Slack DM history: A\'s walk keeps A\'s words and stores B\'s as gaps with no text, skips bots, reads threads, shows A the progress; B\'s walk fills B\'s gaps; members only, INI-433', function () {
    slackDirectSetup($this);
    $a  = "UVIEW{$this->s}";
    $b  = "UBEE{$this->s}";
    $dm = "DDM{$this->s}";
    $gm = "GMP{$this->s}";
    $history = [
        $dm => [($this->dm)($a, 1, ['reply_count' => 1]), ($this->dm)($b, 2), ($this->dm)('USLACKBOT', 3, ['bot_id' => 'B1'])],
        $gm => [($this->dm)($b, 4, ['channel' => $gm])],
    ];
    $history[$dm][] = ($this->dm)($b, 5, ['thread_ts' => ($this->ts)(1)]);
    slackDirectHistoryFake($this, $history);
    $connection = ($this->connectPrivately)($this->viewer, $a);

    $totals = \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::run($this->viewer);

    $channel = SlackChannel::where('slack_id', $dm)->sole();
    $rows    = SlackMessage::where('slack_channel_id', $channel->id)->orderBy('ts')->get()->keyBy('ts');
    expect($totals['finished'])->toBeTrue()
        ->and($rows->keys()->all())->toBe([($this->ts)(1), ($this->ts)(2), ($this->ts)(5)])
        ->and($rows[($this->ts)(1)]->text)->toBe("secret words 1 of $a")
        ->and($rows[($this->ts)(2)]->only(['text', 'data', 'slack_user_id']))->toBe(['text' => null, 'data' => null, 'slack_user_id' => $b])
        ->and($rows[($this->ts)(5)]->text)->toBeNull()
        ->and(SlackChannel::where('slack_id', $gm)->sole()->direct_type)->toBe('mpim')
        ->and(\App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::progress($connection->refresh()))->toBe(['done' => 2, 'total' => 2, 'finished' => true])
        ->and(SlackChannel::directVisibleTo($this->viewer)->whereKey($channel->id)->exists())->toBeTrue()
        ->and(SlackChannel::directVisibleTo($this->stranger)->whereKey($channel->id)->exists())->toBeFalse();
    $slackSection = collect(actingAs($this->viewer)->withoutVite()->get(route('grp.profile.edit'))->assertOk()->inertiaProps()['formData']['blueprint'])->firstWhere('key', 'slack');
    expect($slackSection['fields']['slack_connection']['connection']['private']['history'])->toBe(['done' => 2, 'total' => 2, 'finished' => true]);

    ($this->connectPrivately)($this->bee, $b);
    \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::run($this->bee);

    $rows = SlackMessage::where('slack_channel_id', $channel->id)->orderBy('ts')->get()->keyBy('ts');
    expect($rows->pluck('text', 'ts')->all())->toBe([($this->ts)(1) => "secret words 1 of $a", ($this->ts)(2) => "secret words 2 of $b", ($this->ts)(5) => "secret words 5 of $b"])
        ->and(SlackMessage::where('slack_channel_id', SlackChannel::where('slack_id', $gm)->value('id'))->sole()->text)->toBe("secret words 4 of $b");
});

test('Slack DM history: a revoked token ends the walk, asks to reconnect and hands over to the next person; after reconnecting it resumes without re-reading what was done, INI-433', function () {
    slackDirectSetup($this);
    $a        = "UVIEW{$this->s}";
    $first    = "DONE{$this->s}";
    $second   = "DTWO{$this->s}";
    $revoked  = true;
    slackDirectHistoryFake($this, [$first => [($this->dm)($a, 1, ['channel' => $first])], $second => [($this->dm)($a, 2, ['channel' => $second])]], function (?string $channel) use ($second, &$revoked) {
        return $channel === $second && $revoked ? \Illuminate\Support\Facades\Http::response(['ok' => false, 'error' => 'token_revoked']) : null;
    });
    $connection = ($this->connectPrivately)($this->viewer, $a);
    ($this->connectPrivately)($this->bee, "UBEE{$this->s}");

    $totals = \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::run($this->viewer);
    expect($totals['finished'])->toBeFalse()
        ->and($totals['failed'])->not->toBe([])
        ->and($connection->refresh()->needs_reconnect)->toBeTrue()
        ->and(\App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::progress($connection))->toBe(['done' => 1, 'total' => 2, 'finished' => false]);
    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory && $job->getParameters() === [$this->bee->id]);

    $revoked = false;
    $connection->forceFill(['needs_reconnect' => false])->save();
    $this->slackCalls = [];
    $totals = \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::run($this->viewer);

    $historyReads = collect($this->slackCalls)->where(0, 'conversations.history')->pluck(1)->all();
    expect($totals['finished'])->toBeTrue()
        ->and($historyReads)->toBe([$second])
        ->and(SlackMessage::where('slack_channel_id', SlackChannel::where('slack_id', $second)->value('id'))->sole()->text)->toBe("secret words 2 of $a");
});

test('Slack DM history: switching off mid-walk stops it at the next page and keeps nothing from that page; a slice that runs out of time queues itself again, INI-433', function () {
    slackDirectSetup($this);
    $a     = "UVIEW{$this->s}";
    $dm    = "DDM{$this->s}";
    $other = "DOTH{$this->s}";
    slackDirectHistoryFake($this, [$dm => [], $other => []], function (?string $channel) use ($a, $dm) {
        if ($channel !== $dm) {
            return null;
        }
        \App\Models\Chat\SlackConnection::where('slack_user_id', $a)->first()->endPrivateConsent('withdrawn');

        return \Illuminate\Support\Facades\Http::response(['ok' => true, 'messages' => [($this->dm)($a, 1)], 'response_metadata' => ['next_cursor' => 'next']]);
    });
    ($this->connectPrivately)($this->viewer, $a);

    $totals = \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::run($this->viewer);

    expect($totals['finished'])->toBeFalse()
        ->and(SlackMessage::where('slack_channel_id', SlackChannel::where('slack_id', $dm)->value('id'))->count())->toBe(0)
        ->and(collect($this->slackCalls)->where(0, 'conversations.history')->count())->toBe(1);
    \Illuminate\Support\Facades\Queue::assertNothingPushed();

    slackDirectHistoryFake($this, [$dm => [], $other => []]);
    ($this->connectPrivately)($this->viewer, $a);
    $totals = \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory::run($this->viewer, 0);
    expect($totals['timed_out'])->toBeTrue();
    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Chat\Staff\Slack\BringSlackDirectHistory && $job->getParameters() === [$this->viewer->id]);
});

function slackDirectStream(SlackChannel $channel, User $viewer): array
{
    $messages = \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::streamQuery($channel)->with(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::relations())->orderByDesc('ts')->get(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::COLUMNS);

    return \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::make()->rows($channel, $messages, $viewer);
}

function slackPrivateFilesSetup(object $test): void
{
    slackDirectSetup($test);
    app()->instance('group', $test->group);
    setPermissionsTeamId($test->group->id);
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Config::set('filesystems.disks.staff-chat-r2.bucket', null);
    \Illuminate\Support\Facades\Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-bot');
    $s = $test->s;
    ($test->connectPrivately)($test->viewer, "UVIEW$s");
    \Illuminate\Support\Facades\Http::fake([
        "files.slack.com/files-pri/T1-FPCH$s/*" => fn () => \Illuminate\Support\Facades\Http::response('channel bytes', 200, ['Content-Type' => 'application/pdf']),
        "files.slack.com/files-pri/T1-FPDM$s/*" => \Illuminate\Support\Facades\Http::response('dm bytes', 200, ['Content-Type' => 'image/png']),
        "files.slack.com/files-pri/T1-FPBEE$s/*" => \Illuminate\Support\Facades\Http::response('bee bytes', 200, ['Content-Type' => 'image/png']),
        'slack.com/api/files.info*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'file' => ['id' => "FPDM$s", 'name' => 'payslip march.png', 'mimetype' => 'image/png', 'size' => 8, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FPDM$s/download/payslip-march.png"]]),
    ]);
    $test->privateChannel = SlackChannel::create(['group_id' => $test->group->id, 'slack_id' => "GPF$s", 'name' => "secret-files-$s", 'is_public' => false, 'is_private' => true, 'is_member' => true]);
    $test->privateChannel->members()->create(['slack_user_id' => "UVIEW$s"]);
    $test->channelFile = ['id' => "FPCH$s", 'name' => 'contract.pdf', 'mimetype' => 'application/pdf', 'size' => 13, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FPCH$s/download/contract.pdf"];
    $test->channelMessage = \App\Actions\Chat\Staff\Slack\StoreSlackPrivateMessage::run(['type' => 'message', 'channel' => "GPF$s", 'channel_type' => 'group', 'user' => "UVIEW$s", 'text' => 'the contract', 'ts' => ($test->ts)(1), 'files' => [$test->channelFile]]);
    ($test->webhook)(($test->dm)("UVIEW$s", 2, ['files' => [['id' => "FPDM$s", 'file_access' => 'check_file_info']]]), "UVIEW$s")->assertOk();
    $test->dmChannel = SlackChannel::where('slack_id', "DDM$s")->sole();
    $test->dmMessage = SlackMessage::where('slack_channel_id', $test->dmChannel->id)->where('ts', ($test->ts)(2))->sole();
    $test->fileUrl   = fn (SlackMessage $message, string $slackFileId, array $query = []) => route('grp.chat.staff.slack.files.show', ['slackMessage' => $message->id, 'slackFile' => $slackFileId, ...$query]);
}

test('Slack private files: a private channel file (bot in it) and a DM file (author\'s own token) are kept on a private disk under an opaque name, never in the media library, and stream to members only with no-store; a non-member, a group admin and an engineer get 404; a public message sharing the id never serves the private copy, nor a private message the public one, INI-441', function () {
    slackPrivateFilesSetup($this);
    $s = $this->s;

    $channelCopy = \App\Models\Chat\SlackFile::where('slack_id', "FPCH$s")->sole();
    $dmCopy      = \App\Models\Chat\SlackFile::where('slack_id', "FPDM$s")->sole();
    foreach ([[$channelCopy, 'channel bytes'], [$dmCopy, 'dm bytes']] as [$copy, $bytes]) {
        expect($copy->only(['state', 'is_private', 'media_id', 'disk']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED, 'is_private' => true, 'media_id' => null, 'disk' => 'local'])
            ->and($copy->path)->toMatch('/^slack-private-files\/[0-9a-z]{26}$/')
            ->and($copy->getMedia('slack_files'))->toHaveCount(0)
            ->and($copy->temporaryUrl())->toBeNull()
            ->and(\Illuminate\Support\Facades\Storage::disk('local')->get($copy->path))->toBe($bytes);
    }
    expect($dmCopy->name)->toBe('payslip march.png');
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), "T1-FPCH$s") && $request->hasHeader('Authorization', 'Bearer xoxb-bot'));
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'files.info') && $request->hasHeader('Authorization', "Bearer xoxp-test-UVIEW$s"));
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), "T1-FPDM$s") && $request->hasHeader('Authorization', "Bearer xoxp-test-UVIEW$s"));
    \Illuminate\Support\Facades\Http::assertNotSent(fn ($request) => (str_contains($request->url(), 'files.info') || str_contains($request->url(), "T1-FPDM$s")) && $request->hasHeader('Authorization', 'Bearer xoxb-bot'));

    foreach ([[$this->channelMessage, "FPCH$s", 'channel bytes', 'contract.pdf'], [$this->dmMessage, "FPDM$s", 'dm bytes', 'payslip march.png']] as [$message, $slackFileId, $bytes, $name]) {
        $response = actingAs($this->viewer)->get(($this->fileUrl)($message, $slackFileId))->assertOk();
        expect($response->streamedContent())->toBe($bytes)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('Content-Type'))->toBe('application/octet-stream')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain($name);
        actingAs($this->viewer)->get(($this->fileUrl)($message, $slackFileId, ['variant' => 'thumbnail']))->assertNotFound();
    }
    expect(slackDirectStream($this->privateChannel, $this->viewer)[0]['files'][0])->toMatchArray(['state' => 'ready', 'is_image' => false, 'opens_in_tab' => false, 'thumbnail_url' => null, 'url' => ($this->fileUrl)($this->channelMessage, "FPCH$s")])
        ->and(collect(slackDirectStream($this->dmChannel, $this->viewer))->firstWhere('ts', ($this->ts)(2))['files'][0])->toMatchArray(['state' => 'ready', 'is_image' => false, 'thumbnail_url' => null]);

    $person = function (string $key, ?string $role = null) use ($s): User {
        $user = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
        if ($role) {
            $user->assignRole($role);
        }
        SlackUser::create(['slack_id' => "U$key$s", 'user_id' => $user->id, 'email' => $user->email, 'real_name' => $key]);

        return $user->refresh();
    };
    $engineer = $person('ENGR', 'help-desk-clerk');
    expect(\App\Models\Helpers\Ticket::canBeManagedBy($engineer))->toBeTrue();
    foreach ([$person('OUTS'), $person('ADMN', 'group-admin'), $engineer, $this->bee] as $refused) {
        actingAs($refused)->get(($this->fileUrl)($this->channelMessage, "FPCH$s"))->assertNotFound();
        actingAs($refused)->get(($this->fileUrl)($this->dmMessage, "FPDM$s"))->assertNotFound();
    }

    $publicTwin = SlackMessage::create(['slack_channel_id' => $this->channel->id, 'ts' => ($this->ts)(3), 'slack_user_id' => "UANN$s", 'text' => 'same file in public', 'sent_at' => now(), 'data' => ['files' => [$this->channelFile]]]);
    actingAs($this->viewer)->get(($this->fileUrl)($publicTwin, "FPCH$s"))->assertNotFound();
    expect(\App\Actions\Chat\Staff\Slack\GetSlackMessageFiles::make()->handle(collect([$publicTwin]))[$publicTwin->id][0])->toMatchArray(['state' => 'pending', 'url' => null]);

    \App\Models\Chat\SlackFile::create(['group_id' => $this->group->id, 'slack_id' => "FPUB$s", 'state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED, 'disk' => \App\Actions\Chat\Staff\Slack\CopySlackMessageFile::LARGE_DISK, 'path' => "slack-files/FPUB$s/x.png"]);
    $reuses = SlackMessage::create(['slack_channel_id' => $this->privateChannel->id, 'ts' => ($this->ts)(4), 'slack_user_id' => "UVIEW$s", 'text' => 'public file here', 'sent_at' => now(), 'data' => ['files' => [['id' => "FPUB$s", 'name' => 'x.png', 'mode' => 'hosted']]]]);
    actingAs($this->viewer)->get(($this->fileUrl)($reuses, "FPUB$s"))->assertNotFound();
    expect(\App\Actions\Chat\Staff\Slack\GetSlackMessageFiles::make()->handle(collect([$reuses]), true)[$reuses->id][0])->toMatchArray(['state' => 'pending', 'note' => 'File not available yet', 'url' => null]);
});

test('Slack private files: a DM file of an author without the opt-in is never copied and the bot token is never used for it; deleteStoredCopy removes the bytes, twice safely, and slack:copy-message-files --private copies existing private files once, INI-441', function () {
    slackPrivateFilesSetup($this);
    $s = $this->s;

    $beeFile = ['id' => "FPBEE$s", 'name' => 'bee.png', 'mimetype' => 'image/png', 'size' => 9, 'mode' => 'hosted', 'url_private_download' => "https://files.slack.com/files-pri/T1-FPBEE$s/download/bee.png"];
    ($this->webhook)(($this->dm)("UBEE$s", 5, ['files' => [$beeFile]]), "UVIEW$s")->assertOk();
    $beeWithData = SlackMessage::create(['slack_channel_id' => $this->dmChannel->id, 'ts' => ($this->ts)(6), 'slack_user_id' => "UBEE$s", 'text' => 'bee words', 'sent_at' => now(), 'data' => ['files' => [$beeFile]]]);
    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFile::run($beeWithData, "FPBEE$s"))->toBeNull()
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->handle($this->dmChannel, pause: 0, private: true))->toBe(['queued' => 0, 'recorded' => 0])
        ->and(\App\Models\Chat\SlackFile::where('slack_id', "FPBEE$s")->exists())->toBeFalse();
    \Illuminate\Support\Facades\Http::assertNotSent(fn ($request) => str_contains($request->url(), "T1-FPBEE$s"));
    \Illuminate\Support\Facades\Http::assertNotSent(fn ($request) => (str_contains($request->url(), 'files.info') || str_contains($request->url(), "T1-FPDM$s")) && $request->hasHeader('Authorization', 'Bearer xoxb-bot'));

    $copy = \App\Models\Chat\SlackFile::where('slack_id', "FPCH$s")->sole();
    $path = $copy->path;
    $copy->deleteStoredCopy();
    $copy->deleteStoredCopy();
    expect(\Illuminate\Support\Facades\Storage::disk('local')->exists($path))->toBeFalse()
        ->and($copy->fresh()->only(['state', 'disk', 'path', 'media_id']))->toBe(['state' => \App\Enums\CRM\Livechat\SlackFileStateEnum::PENDING, 'disk' => null, 'path' => null, 'media_id' => null]);
    actingAs($this->viewer)->get(($this->fileUrl)($this->channelMessage, "FPCH$s"))->assertNotFound();

    \Illuminate\Support\Facades\Queue::fake();
    \App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->handle($this->privateChannel, limit: 1, pause: 0, private: true);
    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Chat\Staff\Slack\CopySlackMessageFile && $job->getParameters() === [$this->channelMessage->id, "FPCH$s"]);
    app()->forgetInstance('queue');
    \Illuminate\Support\Facades\Queue::clearResolvedInstances();

    $this->privateChannel->update(['is_member' => false]);
    expect(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->dryRun($this->privateChannel, private: true))->toBe(['to_copy' => ['files' => 1, 'bytes' => 13]])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->dryRun($this->privateChannel))->toBe([])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->handle($this->privateChannel, pause: 0))->toBe(['queued' => 0, 'recorded' => 0])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->handle($this->privateChannel, pause: 0, private: true))->toBe(['queued' => 1, 'recorded' => 0])
        ->and(\App\Actions\Chat\Staff\Slack\CopySlackMessageFiles::make()->handle($this->privateChannel, pause: 0, private: true))->toBe(['queued' => 0, 'recorded' => 0]);
    $again = $copy->fresh();
    expect($again->state)->toBe(\App\Enums\CRM\Livechat\SlackFileStateEnum::COPIED)
        ->and($again->path)->not->toBe($path)
        ->and(\Illuminate\Support\Facades\Storage::disk('local')->get($again->path))->toBe('channel bytes');
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), "T1-FPCH$s") && $request->hasHeader('Authorization', "Bearer xoxp-test-UVIEW$s"));
});

test('Slack second workspace: its signed events create and fill its own channels, a forged team or the other app\'s events on a channel it does not own change nothing, a clashing name reads -awa in aiku only, and a private channel there is read through the person\'s account in that workspace, INI-540', function () {
    \Illuminate\Support\Facades\Config::set('services.slack.signing_secret', 'shh');
    \Illuminate\Support\Facades\Config::set('services.slack.team_id', 'TMAIN');
    \Illuminate\Support\Facades\Config::set('services.slack.other_workspaces', [['team_id' => 'TAWA', 'bot_token' => 'xoxb-awa', 'signing_secret' => 'awa-secret', 'name_suffix' => 'awa']]);
    Event::fake([SlackChannelMessageChanged::class]);
    $webhook = function (array $payload, string $secret) {
        $body      = json_encode($payload);
        $timestamp = (string) time();

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], ['HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp, 'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", $secret), 'CONTENT_TYPE' => 'application/json'], $body);
    };
    $message = fn (string $channel, int $i) => ['type' => 'message', 'channel' => $channel, 'channel_type' => 'channel', 'user' => "UANN{$this->s}", 'text' => "AW message $i", 'ts' => ($this->ts)($i)];
    $stored  = fn (string $channel, int $i) => SlackMessage::whereHas('channel', fn ($query) => $query->where('slack_id', $channel))->where('ts', ($this->ts)($i))->exists();

    $webhook(['type' => 'url_verification', 'challenge' => 'awa-challenge'], 'awa-secret')->assertOk()->assertJsonPath('challenge', 'awa-challenge');
    $webhook(['type' => 'event_callback', 'team_id' => 'TAWA', 'event' => $message("CAWA{$this->s}", 1)], 'awa-secret')->assertOk();
    $awaChannel = SlackChannel::where('slack_id', "CAWA{$this->s}")->sole();
    expect($awaChannel->slack_team_id)->toBe('TAWA')
        ->and($stored("CAWA{$this->s}", 1))->toBeTrue();

    $webhook(['type' => 'event_callback', 'team_id' => 'TAWA', 'event' => $message("CAWA{$this->s}", 3)], 'wrong')->assertUnauthorized();
    $webhook(['type' => 'event_callback', 'team_id' => 'TMAIN', 'event' => $message("CAWA{$this->s}", 4)], 'shh')->assertOk();
    $webhook(['type' => 'event_callback', 'team_id' => 'TAWA', 'event' => $message($this->channel->slack_id, 5)], 'awa-secret')->assertOk();
    $webhook(['type' => 'event_callback', 'team_id' => 'TMAIN', 'event' => $message($this->channel->slack_id, 6)], 'shh')->assertOk();
    expect($stored("CAWA{$this->s}", 3))->toBeFalse()
        ->and($stored("CAWA{$this->s}", 4))->toBeFalse()
        ->and($stored($this->channel->slack_id, 5))->toBeFalse()
        ->and($stored($this->channel->slack_id, 6))->toBeTrue();

    SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "GMAINPAY{$this->s}", 'name' => "payroll-{$this->s}", 'is_public' => false, 'is_private' => true]);
    $awaPayroll = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CAWAPAY{$this->s}", 'name' => "payroll-{$this->s}", 'slack_team_id' => 'TAWA']);
    $awaGeneral = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CAWAGEN{$this->s}", 'name' => "live-{$this->s}", 'slack_team_id' => 'TAWA']);
    expect($awaGeneral->fresh()->name)->toBe("live-{$this->s}-awa")
        ->and($awaGeneral->fresh()->getRawOriginal('name'))->toBe("live-{$this->s}")
        ->and($awaPayroll->fresh()->name)->toBe("payroll-{$this->s}")
        ->and($awaChannel->fresh()->name)->toBeNull()
        ->and($this->channel->fresh()->name)->toBe("live-{$this->s}");

    $awaPrivate = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "GAWA{$this->s}", 'name' => "secret-{$this->s}", 'slack_team_id' => 'TAWA', 'is_public' => false, 'is_private' => true, 'is_member' => true]);
    $awaPrivate->members()->create(['slack_user_id' => "UVIEWAWA{$this->s}"]);
    expect($awaPrivate->isPrivateVisibleTo($this->viewer))->toBeFalse();
    SlackUser::create(['slack_id' => "UVIEWAWA{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'Viewer at AW', 'data' => ['team_id' => 'TAWA']]);
    expect($awaPrivate->isPrivateVisibleTo($this->viewer))->toBeTrue()
        ->and($awaPrivate->isPrivateVisibleTo($this->stranger))->toBeFalse()
        ->and(SlackChannel::linkedSlackPerson($this->viewer)->slack_id)->toBe("UVIEW{$this->s}");

    $shared = SlackUser::create(['slack_id' => "USHAREDAWA{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'Shared mailbox at AW', 'data' => ['team_id' => 'TAWA']]);
    $sharedOnly = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "GAWASHARED{$this->s}", 'name' => "shared-{$this->s}", 'slack_team_id' => 'TAWA', 'is_public' => false, 'is_private' => true, 'is_member' => true]);
    $sharedOnly->members()->create(['slack_user_id' => $shared->slack_id]);
    expect($sharedOnly->isPrivateVisibleTo($this->viewer))->toBeFalse();
});

test('Verba channel list names the Slack workspace of each channel from the second workspace, so the sidebar can dot them, HELP-5001', function () {
    \Illuminate\Support\Facades\Config::set('services.slack.team_id', 'TMAIN');
    \Illuminate\Support\Facades\Config::set('services.slack.other_workspaces', [['team_id' => 'TAWA', 'bot_token' => 'xoxb-awa', 'signing_secret' => 'awa-secret', 'name_suffix' => 'awa', 'name' => 'AW Advantage']]);
    $awa = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CAWADOT{$this->s}", 'name' => "dot-{$this->s}", 'slack_team_id' => 'TAWA', 'is_public' => true, 'is_member' => true]);

    $workspaces = collect(\App\Actions\Chat\Staff\UI\ShowStaffSlackChannels::make()->channels($this->viewer))->pluck('workspace', 'id');

    expect($workspaces->get($this->channel->id))->toBeNull()
        ->and($workspaces->get($awa->id))->toBe('AW Advantage');
});
