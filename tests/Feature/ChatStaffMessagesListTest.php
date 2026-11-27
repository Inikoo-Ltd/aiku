<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Json\SearchSlackMessages;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\StoreStaffConversation;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

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
    \Illuminate\Support\Facades\Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $this->s      = Str::upper(Str::random(6));
    $this->group  = $this->organisation->group;
    $newUser      = fn () => User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    $this->viewer = $newUser();
    $this->plain  = $newUser();
    SlackUser::create(['slack_id' => "UVIEW{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'Viewer Person']);
    SlackUser::create(['slack_id' => "UANN{$this->s}", 'real_name' => 'Ann Author']);
    $this->channel      = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLIST{$this->s}", 'name' => "list-{$this->s}", 'is_member' => true]);
    $this->ignored      = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLISTIGN{$this->s}", 'name' => "ignored-{$this->s}", 'is_ignored' => true]);
    $this->conversation = StoreStaffConversation::run($this->viewer, ['user_ids' => [$this->plain->id]]);
});

function queriesWhile(callable $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return collect($log)->pluck('query')->all();
}

function slackListFor(User $user, string $url, ?array &$queries = null): array
{
    $version = actingAs($user)->get($url)->viewData('page')['version'];
    $props   = [];
    $queries = queriesWhile(function () use ($user, $url, $version, &$props) {
        $props = actingAs($user)->get($url, [
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $version,
            'X-Inertia-Partial-Component' => 'Chat/StaffMessaging',
            'X-Inertia-Partial-Data'      => 'slack_channels,slack_live_channel',
        ])->assertOk()->json('props');
    });

    return [$props['slack_channels'] ?? 'missing', $props['slack_live_channel'] ?? null];
}

test('one list: the conversations page opens with the same queries as before for people with and without Slack; the Slack list comes after it as one deferred group, INI-447', function () {
    foreach ([$this->viewer, $this->plain] as $person) {
        foreach ([route('grp.chat.staff.index') => 9, route('grp.chat.staff.show', $this->conversation->ulid) => 14] as $url => $ceiling) {
            actingAs($person)->get($url)->assertOk();
            $page    = null;
            $queries = queriesWhile(function () use ($person, $url, &$page) {
                $page = actingAs($person)->get($url)->assertOk();
            });
            expect(count($queries))->toBeLessThanOrEqual($ceiling)
                ->and(collect($queries)->filter(fn (string $query) => str_contains(strtolower($query), 'slack')))->toBeEmpty();
            $page->assertInertia(fn (AssertableInertia $inertia) => $inertia->component('Chat/StaffMessaging')
                ->missing('slack_channels')->missing('slack_live_channel')->where('slack_channel', null));
            expect($page->viewData('page')['deferredProps']['slack'] ?? [])->toEqualCanonicalizing(['slack_private_opt_in', 'slack_channels', 'slack_private_channels', 'slack_listed_channels', 'slack_direct_channels', 'slack_live_channel']);
        }
    }

    [$channels, $live] = slackListFor($this->viewer, route('grp.chat.staff.show', $this->conversation->ulid));
    expect(collect($channels)->pluck('id'))->toContain($this->channel->id)->not->toContain($this->ignored->id)
        ->and(collect($channels)->firstWhere('id', $this->channel->id))->toHaveKeys(['unread_count', 'last_read_ts'])
        ->and($live)->toBe('grp.slack.'.$this->group->id);

    expect(slackListFor($this->plain, route('grp.chat.staff.index')))->toBe([[], null]);

    $ulids = collect(actingAs($this->viewer)->getJson(route('grp.chat.staff.conversations.index'))->assertOk()->json('data'))->pluck('ulid');
    expect($ulids)->toContain($this->conversation->ulid);
});

test('one list: opening the page again writes nothing; only a channel seen for the first time gets a read position, INI-447', function () {
    slackListFor($this->viewer, route('grp.chat.staff.index'), $first);
    expect(collect($first)->filter(fn (string $query) => str_starts_with(strtolower(ltrim($query)), 'insert')))->toHaveCount(1);

    slackListFor($this->viewer, route('grp.chat.staff.index'), $second);
    expect(collect($second)->filter(fn (string $query) => preg_match('/^\s*(insert|update|delete)/i', $query)))->toBeEmpty();
});

test('one list: inactive, agent-only, Slack guest, other-email, bot and deleted Slack people get no channels and no live channel; private and deleted channels are never listed, INI-447', function () {
    $newUser = fn (array $attributes = []) => User::factory()->create(array_merge(['group_id' => $this->group->id, 'language_id' => $this->user->language_id], $attributes));
    $link    = fn (User $user, string $key, array $attributes = []) => SlackUser::create(array_merge(['slack_id' => "U{$key}{$this->s}", 'user_id' => $user->id, 'email' => $user->email], $attributes));

    $inactive = $newUser(['status' => false]);
    $link($inactive, 'INACT');
    $agent    = $newUser();
    $link($agent, 'AGENT');
    $agentOrg = \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($this->group, \App\Models\SupplyChain\Agent::factory()->definition())->organisation_id;
    $agent->authorisedAgentsOrganisations()->attach($agentOrg, ['org_id' => $agentOrg]);
    $guest    = $newUser();
    $link($guest, 'GUEST', ['data' => ['is_restricted' => true]]);
    $borrowed = $newUser();
    $link($borrowed, 'BORROW', ['email' => 'someone.else.'.$this->s.'@example.com']);
    $bot      = $newUser();
    $link($bot, 'BOT', ['is_bot' => true]);
    $leaver   = $newUser();
    $link($leaver, 'LEFT', ['is_deleted' => true]);

    foreach ([$agent, $guest, $borrowed, $bot, $leaver] as $person) {
        $conversation = StoreStaffConversation::run($person, ['user_ids' => [$this->plain->id]]);
        foreach ([route('grp.chat.staff.index'), route('grp.chat.staff.show', $conversation->ulid)] as $url) {
            expect(slackListFor($person, $url))->toBe([[], null]);
        }
    }

    $inactiveConversation = StoreStaffConversation::run($inactive, ['user_ids' => [$this->plain->id]]);
    foreach ([route('grp.chat.staff.index'), route('grp.chat.staff.show', $inactiveConversation->ulid)] as $url) {
        actingAs($inactive)->get($url)->assertRedirect();
    }
    $props = null;
    expect(queriesWhile(function () use ($inactive, &$props) {
        $props = \App\Actions\Chat\Staff\UI\ShowStaffSlackChannels::make()->listProps($this->group, $inactive, deferred: true);
    }))->toBe([])
        ->and($props)->toBe(['slack_channels' => [], 'slack_private_channels' => [], 'slack_listed_channels' => [], 'slack_direct_channels' => [], 'slack_live_channel' => null, 'desk_starred' => [], 'slack_private_opt_in' => null]);

    $private = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLISTPRIV{$this->s}", 'name' => "private-{$this->s}", 'is_public' => false]);
    $deleted = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CLISTDEL{$this->s}", 'name' => "deleted-{$this->s}", 'deleted_in_slack_at' => now()]);
    [$channels] = slackListFor($this->viewer, route('grp.chat.staff.index'));
    expect(collect($channels)->pluck('id'))->toContain($this->channel->id)->not->toContain($private->id)->not->toContain($deleted->id)->not->toContain($this->ignored->id);
});

test('one list: a person without Slack on the Slack page or asking for the list directly gets none, INI-447', function () {
    actingAs($this->plain)->get(route('grp.chat.staff.slack.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channels', [])->where('slack_live_channel', null));
    expect(slackListFor($this->plain, route('grp.chat.staff.show', $this->conversation->ulid)))->toBe([[], null]);
    actingAs($this->plain)->get(route('grp.chat.staff.slack.show', $this->channel->id))->assertForbidden();
});

test('one list: deep links select the right view, a conversation, a Slack channel, a search hit and a mention in a thread, INI-447', function () {
    Event::fake([SlackChannelMessageChanged::class]);
    $start  = now()->subHour()->startOfMinute()->timestamp;
    $post   = fn (int $i, array $extra = []) => StoreSlackMessage::make()->handle(array_merge([
        'type'         => 'message',
        'channel'      => $this->channel->slack_id,
        'channel_type' => 'channel',
        'user'         => "UANN{$this->s}",
        'text'         => "Message $i",
        'ts'           => sprintf('%d.%06d', $start + $i * 60, $i),
    ], $extra));
    $parent = $post(1);
    $reply  = $post(2, ['thread_ts' => $parent->ts]);

    actingAs($this->viewer)->get(route('grp.chat.staff.show', $this->conversation->ulid))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected_ulid', $this->conversation->ulid)->where('slack_channel', null));

    actingAs($this->viewer)->get(route('grp.chat.staff.slack.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected_ulid', null)->where('slack_channel', null)
            ->where('slack_channels', fn ($channels) => collect($channels)->contains('id', $this->channel->id)));

    actingAs($this->viewer)->get(SearchSlackMessages::messageUrl($parent))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected_ulid', null)
            ->where('slack_channel.id', $this->channel->id)
            ->where('slack_channel.last_read_ts', fn ($ts) => is_string($ts))
            ->where('slack_around_ts', $parent->ts)
            ->where('slack_thread_ts', null));

    actingAs($this->viewer)->get(SearchSlackMessages::messageUrl($reply))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.id', $this->channel->id)
            ->where('slack_around_ts', $parent->ts)
            ->where('slack_thread_ts', $parent->ts)
            ->where('slack_reply_ts', $reply->ts));
});
