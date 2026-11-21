<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelBookmark;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Chat\SlackMessageVersion;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
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

    $this->s       = Str::upper(Str::random(6));
    $group         = $this->organisation->group;
    $newUser       = fn () => User::factory()->create(['group_id' => $group->id, 'language_id' => $this->user->language_id]);
    $this->viewer  = $newUser();
    $this->stranger = $newUser();
    SlackUser::create(['slack_id' => "UVIEW{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'Viewer Person']);
    SlackUser::create(['slack_id' => "UANN{$this->s}", 'real_name' => 'Ann Author']);
    SlackUser::create(['slack_id' => "UBOB{$this->s}", 'real_name' => 'Bob Builder']);

    $this->channel = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CTHR{$this->s}", 'name' => "threads-{$this->s}", 'is_member' => true]);
    $this->hidden  = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "CTHRIGN{$this->s}", 'name' => "ignored-{$this->s}", 'is_ignored' => true]);

    $start       = now()->subDays(2)->startOfMinute();
    $this->ts    = fn (int $i) => sprintf('%d.%06d', $start->timestamp + $i * 60, $i);
    $this->store = fn (int $i, string $text, array $attributes = [], ?SlackChannel $channel = null) => SlackMessage::create(array_merge([
        'slack_channel_id' => ($channel ?? $this->channel)->id,
        'ts'               => ($this->ts)($i),
        'slack_user_id'    => "UANN{$this->s}",
        'text'             => $text,
        'sent_at'          => $start->copy()->addMinutes($i),
        'data'             => [],
    ], $attributes));
    $this->text = fn (array $row) => trim(FormatSlackMessages::plainText($row['nodes']));
});

function slackStreamPage(User $viewer, array $parameters): array
{
    $page = null;
    actingAs($viewer)->get(route('grp.chat.staff.slack.show', $parameters))->assertOk()
        ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
            $inertia->loadDeferredProps(function (AssertableInertia $reload) use (&$page) {
                $page = $reload->toArray()['props']['slack_messages'];
            });
        });

    return $page;
}

test('Slack threads: reply count and repliers counted from our rows, the thread in order and paged, a broadcast reply in the stream, a deleted parent kept readable, access refused, INI-434', function () {
    $ts    = $this->ts;
    $store = $this->store;

    $store(1, 'Question for everyone', ['thread_ts' => $ts(1), 'data' => ['reply_count' => 99]]);
    $store(2, 'First answer', ['thread_ts' => $ts(1), 'slack_user_id' => "UBOB{$this->s}"]);
    $store(3, 'Second answer', ['thread_ts' => $ts(1), 'slack_user_id' => "UVIEW{$this->s}"]);
    $store(4, 'Shared with the channel', ['thread_ts' => $ts(1), 'subtype' => 'thread_broadcast']);
    $store(5, 'Deleted reply', ['thread_ts' => $ts(1)])->delete();
    $store(10, 'Gone question', ['thread_ts' => $ts(10)])->delete();
    $store(11, 'Answer to a gone question', ['thread_ts' => $ts(10)]);
    $store(12, 'Deleted without replies')->delete();

    $page   = slackStreamPage($this->viewer, ['slackChannel' => $this->channel->id]);
    $byText = collect($page['data'])->keyBy(fn (array $row) => ($this->text)($row));

    expect(array_keys($byText->all()))->toBe(['', 'Shared with the channel', 'Question for everyone'])
        ->and($byText['Question for everyone']['reply_count'])->toBe(3)
        ->and($byText['Question for everyone']['latest_reply_at'])->not->toBeNull()
        ->and(collect($byText['Question for everyone']['reply_users'])->pluck('name')->all())->toBe(['Ann Author', $this->viewer->chatName(), 'Bob Builder'])
        ->and($byText['Shared with the channel']['is_broadcast'])->toBeTrue()
        ->and($byText['Shared with the channel']['thread_ts'])->toBe($ts(1))
        ->and($byText['']['is_deleted'])->toBeTrue()
        ->and($byText['']['author']['name'])->toBeNull()
        ->and($byText['']['reply_count'])->toBe(1)
        ->and(json_encode($page))->not->toContain('Gone question')->not->toContain('Deleted without replies');

    $thread = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.thread', ['slackChannel' => $this->channel->id, 'ts' => $ts(1)]))->assertOk()->json();
    expect(($this->text)($thread['parent']))->toBe('Question for everyone')
        ->and(array_map($this->text, $thread['replies']))->toBe(['First answer', 'Second answer', 'Shared with the channel'])
        ->and($thread['replies'][2]['is_broadcast'])->toBeTrue()
        ->and($thread['next_cursor'])->toBeNull();

    $gone = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.thread', ['slackChannel' => $this->channel->id, 'ts' => $ts(10)]))->assertOk()->json();
    expect($gone['parent']['is_deleted'])->toBeTrue()
        ->and($gone['parent']['nodes'])->toBe([])
        ->and(json_encode($gone))->not->toContain('Gone question')
        ->and(array_map($this->text, $gone['replies']))->toBe(['Answer to a gone question']);

    for ($i = 20; $i < 23; $i++) {
        $store($i, "Long $i", ['thread_ts' => $ts(10)]);
    }
    $first = \App\Actions\Chat\Staff\Json\GetSlackThread::make()->handle($this->channel, $ts(10), 2);
    expect(array_map($this->text, $first['replies']))->toBe(['Answer to a gone question', 'Long 20'])
        ->and($first['next_cursor'])->not->toBeNull();
    $second = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.thread', ['slackChannel' => $this->channel->id, 'ts' => $ts(10), 'cursor' => $first['next_cursor']]))->assertOk()->json();
    expect($second['parent'])->toBeNull()
        ->and(array_map($this->text, $second['replies']))->toBe(['Long 21', 'Long 22']);

    actingAs($this->viewer)->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->channel->id, 'around' => $ts(1), 'thread' => $ts(1), 'reply' => $ts(3)]))->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('slack_thread_ts', $ts(1))->where('slack_around_ts', $ts(1))->where('slack_reply_ts', $ts(3)));
    actingAs($this->viewer)->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->channel->id, 'thread' => '<script>', 'reply' => 'javascript:x']))->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('slack_thread_ts', null)->where('slack_reply_ts', null));

    actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.thread', ['slackChannel' => $this->channel->id, 'ts' => $ts(2)]))->assertNotFound();
    actingAs($this->stranger)->getJson(route('grp.chat.staff.slack.thread', ['slackChannel' => $this->channel->id, 'ts' => $ts(1)]))->assertForbidden();
    ($this->store)(1, 'Hidden thread', ['thread_ts' => $ts(1)], $this->hidden);
    actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.thread', ['slackChannel' => $this->hidden->id, 'ts' => $ts(1)]))->assertForbidden();
});

test('Slack pins, bookmarks and reactions: the pinned list, jump to a message returns the page around it, unsafe bookmark links are not linked, reactions in first-added order with the viewer\'s own marked, INI-438', function () {
    $ts    = $this->ts;
    $store = $this->store;
    for ($i = 1; $i <= 40; $i++) {
        $store($i, "Message $i");
    }
    SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(5))->update(['pinned_at' => now()->subHour(), 'pinned_by_slack_user_id' => "UBOB{$this->s}"]);
    SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(30))->update(['pinned_at' => now(), 'pinned_by_slack_user_id' => "UBOB{$this->s}"]);
    $store(41, 'Pinned reply', ['thread_ts' => $ts(40), 'pinned_at' => now()->subDay()]);
    $store(42, 'Pinned but deleted', ['pinned_at' => now()])->delete();

    $pins = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.pins', $this->channel->id))->assertOk()->json();
    expect(array_map($this->text, $pins))->toBe(['Message 30', 'Message 5', 'Pinned reply'])
        ->and($pins[2]['thread_ts'])->toBe($ts(40));

    $around = GetSlackChannelMessages::make()->handle($this->channel, 20, $ts(5));
    $texts  = array_map($this->text, $around->items());
    expect($texts)->toContain('Message 5')
        ->and($texts[0])->toBe('Message 14')
        ->and(end($texts))->toBe('Message 1')
        ->and($around->previousCursor())->not->toBeNull();

    $page = slackStreamPage($this->viewer, ['slackChannel' => $this->channel->id, 'around' => $ts(5)]);
    expect(array_map($this->text, $page['data']))->toContain('Message 5')->not->toContain('Message 40');

    SlackChannelBookmark::create(['slack_channel_id' => $this->channel->id, 'slack_id' => "Bk1{$this->s}", 'title' => 'Handbook', 'link' => 'https://example.com/handbook', 'emoji' => ':tada:', 'position' => '1']);
    SlackChannelBookmark::create(['slack_channel_id' => $this->channel->id, 'slack_id' => "Bk2{$this->s}", 'title' => 'Bad', 'link' => 'javascript:alert(1)', 'position' => '2']);
    SlackChannelBookmark::create(['slack_channel_id' => $this->channel->id, 'slack_id' => "Bk3{$this->s}", 'title' => 'Removed', 'link' => 'https://example.com/old', 'position' => '0', 'removed_in_slack_at' => now()]);

    actingAs($this->viewer)->get(route('grp.chat.staff.slack.show', $this->channel->id))->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('slack_channel.pinned_count', 3)
            ->where('slack_channel.bookmarks', [
                ['id' => SlackChannelBookmark::where('slack_id', "Bk1{$this->s}")->value('id'), 'title' => 'Handbook', 'url' => 'https://example.com/handbook', 'emoji' => ['unicode' => '🎉']],
                ['id' => SlackChannelBookmark::where('slack_id', "Bk2{$this->s}")->value('id'), 'title' => 'Bad', 'url' => null, 'emoji' => null],
            ]));

    $reacted = SlackMessage::where('slack_channel_id', $this->channel->id)->where('ts', $ts(40))->sole();
    SlackMessageReaction::create(['slack_message_id' => $reacted->id, 'slack_user_id' => "UBOB{$this->s}", 'name' => 'tada']);
    SlackMessageReaction::create(['slack_message_id' => $reacted->id, 'slack_user_id' => "UANN{$this->s}", 'name' => '+1']);
    SlackMessageReaction::create(['slack_message_id' => $reacted->id, 'slack_user_id' => "UVIEW{$this->s}", 'name' => '+1']);
    $newest = slackStreamPage($this->viewer, ['slackChannel' => $this->channel->id])['data'][0];
    expect(collect($newest['reactions'])->map(fn ($r) => [$r['name'], $r['count'], $r['people']])->all())->toBe([
        ['tada', 1, ['Bob Builder']],
        ['+1', 2, ['Ann Author', $this->viewer->chatName()]],
    ])->and($newest['my_reactions'])->toBe(['+1']);

    actingAs($this->stranger)->getJson(route('grp.chat.staff.slack.pins', $this->channel->id))->assertForbidden();
    actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.pins', $this->hidden->id))->assertForbidden();
});

test('Slack message versions: earlier wordings newest first for a visible channel, 404 for a channel the person cannot see, empty for an unedited message, INI-449', function () {
    $ts      = $this->ts;
    $edited  = ($this->store)(1, 'third wording', ['edited_at' => now()]);
    ($this->store)(2, 'never edited');
    SlackMessageVersion::create(['slack_message_id' => $edited->id, 'text' => 'first wording', 'data' => [], 'replaced_at' => now()->subHours(2)]);
    SlackMessageVersion::create(['slack_message_id' => $edited->id, 'text' => 'second wording', 'data' => [], 'replaced_at' => now()->subHour()]);

    $url = fn (SlackChannel $channel, string $at) => route('grp.chat.staff.slack.versions', ['slackChannel' => $channel->id, 'ts' => $at]);

    $versions = actingAs($this->viewer)->getJson($url($this->channel, $ts(1)))->assertOk()->json('versions');
    expect(array_map($this->text, $versions))->toBe(['second wording', 'first wording']);

    actingAs($this->viewer)->getJson($url($this->channel, $ts(2)))->assertOk()->assertJson(['versions' => []]);
    actingAs($this->viewer)->getJson($url($this->channel, $ts(9)))->assertNotFound();
    actingAs($this->stranger)->getJson($url($this->channel, $ts(1)))->assertNotFound();

    $hiddenMessage = ($this->store)(3, 'hidden', ['edited_at' => now()], $this->hidden);
    SlackMessageVersion::create(['slack_message_id' => $hiddenMessage->id, 'text' => 'old hidden', 'data' => [], 'replaced_at' => now()]);
    actingAs($this->viewer)->getJson($url($this->hidden, $ts(3)))->assertNotFound();

    $row = collect(slackStreamPage($this->viewer, ['slackChannel' => $this->channel->id])['data'])->firstWhere('ts', $ts(1));
    expect($row['versions_url'])->toBe($url($this->channel, $ts(1)));
});
