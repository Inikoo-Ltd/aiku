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
    expect($totals)->toBe(['channels' => 1, 'filled' => 2])
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

function slackDirectStream(SlackChannel $channel, User $viewer): array
{
    $messages = \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::streamQuery($channel)->with(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::relations())->orderByDesc('ts')->get(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::COLUMNS);

    return \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::make()->rows($channel, $messages, $viewer);
}
