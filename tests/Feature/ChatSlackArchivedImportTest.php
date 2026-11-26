<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\ImportSlackExportArchivedChannels;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackPrivateMessage;
use App\Actions\Chat\Staff\Slack\SyncSlackChannelMembers;
use App\Actions\Chat\Staff\Slack\SyncSlackPrivateChannels;
use App\Actions\Chat\Staff\Slack\SyncSlackPublicChannels;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelMember;
use App\Models\SysAdmin\User;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Web\Website;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web                = Website::has('shop')->first();
    $this->organisation = $web ? $web->organisation : createShop()[0];
    $group              = $this->organisation->group;
    $this->s            = Str::upper(Str::random(6));

    $channel = fn (string $key, array $attributes = []) => SlackChannel::create(array_merge([
        'group_id'    => $group->id,
        'slack_id'    => "C{$key}{$this->s}",
        'name'        => strtolower("{$key}-{$this->s}"),
        'is_archived' => true,
        'is_member'   => false,
    ], $attributes));
    $this->archived = $channel('ARC');
    $this->ignored  = $channel('IGN', ['is_ignored' => true]);
    $this->private  = $channel('PRV', ['is_public' => false]);
    $this->member   = $channel('MEM', ['is_member' => true]);
    $this->live     = $channel('LIV', ['is_archived' => false, 'is_member' => true]);
    $this->tokens   = $channel('TOK');
    $this->escape   = $channel('ESC');

    $this->path = storage_path('framework/testing/slack-export-'.$this->s);
    $day        = function (SlackChannel $channel, string $date, array $messages) {
        File::ensureDirectoryExists($this->path.'/'.$channel->name);
        File::put($this->path.'/'.$channel->name.'/'.$date.'.json', json_encode($messages));
    };
    $token = 'xoxe-1-FAKE-export-token';
    $day($this->archived, '2019-05-15', [
        ['type' => 'message', 'user' => 'U1', 'text' => 'first ever', 'ts' => '1557900000.000100', 'thread_ts' => '1557900000.000100', 'reply_count' => 1,
            'reactions' => [['name' => '+1', 'users' => ['U2'], 'count' => 1]]],
        ['type' => 'message', 'user' => 'U2', 'text' => 'reply in thread', 'ts' => '1557900100.000100', 'thread_ts' => '1557900000.000100', 'parent_user_id' => 'U1'],
    ]);
    $day($this->archived, '2019-05-16', [
        ['type' => 'message', 'user' => 'U1', 'text' => 'a picture', 'ts' => '1557990000.000100', 'files' => [[
            'id'                   => 'FARC'.$this->s,
            'name'                 => 'image.png',
            'mode'                 => 'hosted',
            'url_private'          => "https://files.slack.com/files-pri/T1-F1/image.png?token=$token",
            'url_private_download' => "https://files.slack.com/files-pri/T1-F1/download/image.png?token=$token",
            'thumb_360'            => "https://files.slack.com/files-tmb/T1-F1-a/image_360.png?t=$token",
        ]]],
    ]);
    $day($this->tokens, '2019-06-01', [
        ['type' => 'message', 'user' => 'U1', 'ts' => '1559350000.000100', 'text' => 'link https://files.slack.com/files-pri/T1-F2/a.png?foo=1%3Ft%3Dxoxe-1-ENCODED-token'],
        ['type' => 'message', 'user' => 'U1', 'ts' => '1559350100.000100', 'text' => 'the token xoxe-1-MIDDLE-token was pasted here'],
        ['type' => 'message', 'user' => 'U1', 'ts' => '1559350200.000100', 'files' => [['id' => 'FTOK'.$this->s, 'mode' => 'hosted',
            'url_private' => 'https://files.slack.com/files-pri/T1-F3/b.png?t=xoxp-2-OTHER-token&w=360', 'thumb_64' => 'https://files.slack.com/files-tmb/T1-F3/b_64.png?token=anything']]],
        ['type' => 'message', 'user' => 'U1', 'ts' => '1559350300.000100', 'blocks' => ['xoxb-3-IN-A-KEY' => 'kept as a key, so withheld']],
    ]);
    File::ensureDirectoryExists(dirname($this->path).'/outside-'.$this->s);
    File::put(dirname($this->path).'/outside-'.$this->s.'/2019-01-01.json', json_encode([['type' => 'message', 'user' => 'U1', 'text' => 'outside', 'ts' => '1546300800.000100']]));
    foreach (['dms.json', 'groups.json', 'mpims.json'] as $privateList) {
        File::put($this->path.'/'.$privateList, json_encode([['id' => 'G'.$this->s, 'name' => 'secret-'.$this->s, 'is_archived' => true]]));
    }
    File::ensureDirectoryExists($this->path.'/secret-'.$this->s);
    File::put($this->path.'/secret-'.$this->s.'/2019-01-01.json', json_encode([['type' => 'message', 'user' => 'U1', 'text' => 'private', 'ts' => '1546300800.000200']]));
    foreach ([$this->ignored, $this->private, $this->member, $this->live] as $other) {
        $day($other, '2020-01-01', [['type' => 'message', 'user' => 'U1', 'text' => 'not for aiku', 'ts' => '1577836800.000100']]);
    }
    File::put($this->path.'/channels.json', json_encode([
        ...array_map(fn (SlackChannel $channel) => [
            'id' => $channel->slack_id, 'name' => $channel->name, 'is_archived' => $channel->is_archived, 'created' => 1557900000,
        ], [$this->archived, $this->ignored, $this->private, $this->member, $this->live, $this->tokens]),
        ['id' => $this->escape->slack_id, 'name' => '../outside-'.$this->s, 'is_archived' => true],
    ]));

    $this->import = fn (bool $dryRun = false) => collect(ImportSlackExportArchivedChannels::run($this->path, [], $dryRun))->keyBy('channel');
});

afterEach(function () {
    File::deleteDirectory($this->path);
    File::deleteDirectory(dirname($this->path).'/outside-'.$this->s);
});

test('a dry run says what each archived channel holds and stores nothing', function () {
    $rows = ($this->import)(true);

    expect($rows[$this->archived->name])->toMatchArray(['status' => 'to import', 'in_export' => 3, 'files' => 1, 'stored' => 0])
        ->and($rows[$this->ignored->name]['status'])->toStartWith('skipped')
        ->and($rows[$this->private->name]['status'])->toStartWith('skipped')
        ->and($rows[$this->member->name]['status'])->toStartWith('skipped')
        ->and($rows->has($this->live->name))->toBeFalse()
        ->and(SlackMessage::whereIn('slack_channel_id', [$this->archived->id, $this->ignored->id, $this->private->id, $this->member->id, $this->live->id])->count())->toBe(0)
        ->and($this->archived->refresh()->history_synced_at)->toBeNull();
});

test('the history of an archived channel is stored like a backfill, without the export token and without sending anything', function () {
    Queue::fake();
    Event::fake([SlackChannelMessageChanged::class]);
    Notification::fake();

    $rows = ($this->import)();

    $messages = $this->archived->messages()->orderBy('ts')->get();
    expect($rows[$this->archived->name])->toMatchArray(['status' => 'imported', 'in_export' => 3, 'stored' => 3])
        ->and($messages->pluck('text')->all())->toBe(['first ever', 'reply in thread', 'a picture'])
        ->and($messages[1]->thread_ts)->toBe('1557900000.000100')
        ->and(json_encode(SlackMessage::where('slack_channel_id', $this->archived->id)->pluck('data')))->not->toContain('xoxe-')
        ->and($messages[2]->data['files'][0]['url_private_download'])->toBe('https://files.slack.com/files-pri/T1-F1/download/image.png')
        ->and($messages[2]->data['files'][0]['thumb_360'])->toBe('https://files.slack.com/files-tmb/T1-F1-a/image_360.png')
        ->and(SlackMessageReaction::where('slack_message_id', $messages[0]->id)->pluck('name')->all())->toBe(['+1'])
        ->and($this->archived->refresh()->history_synced_at)->not->toBeNull()
        ->and(SlackMessage::whereIn('slack_channel_id', [$this->ignored->id, $this->private->id, $this->member->id, $this->live->id])->count())->toBe(0);
    Queue::assertNothingPushed();
    Event::assertNotDispatched(SlackChannelMessageChanged::class);
    Notification::assertNothingSent();
});

test('a stopped import picks up again without doubling messages, and a finished channel is not read again', function () {
    SlackMessage::create(['slack_channel_id' => $this->archived->id, 'ts' => '1557900000.000100', 'text' => 'first ever', 'sent_at' => now()]);

    ($this->import)();
    $again = ($this->import)();

    expect($this->archived->messages()->count())->toBe(3)
        ->and($again[$this->archived->name])->toMatchArray(['status' => 'done before', 'in_export' => 0, 'stored' => 3]);
});

test('the command can be limited to channels and reports a missing export', function () {
    $this->artisan('slack:import-archived-channels', ['path' => $this->path, '--channel' => [$this->member->slack_id]])->assertSuccessful();
    expect($this->archived->messages()->count())->toBe(0);

    $this->artisan('slack:import-archived-channels', ['path' => $this->path, '--channel' => [$this->archived->name]])->assertSuccessful();
    expect($this->archived->messages()->count())->toBe(3);

    expect(fn () => ImportSlackExportArchivedChannels::run($this->path.'-missing'))->toThrow(RuntimeException::class, 'No channels.json');
});

test('the nightly history sync never reads an archived channel the aiku app is not in', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Sleep::fake();
    Http::fake([
        'slack.com/api/conversations.list*'    => Http::response(['ok' => true, 'channels' => [
            ['id' => $this->archived->slack_id, 'name' => $this->archived->name, 'is_archived' => true, 'is_member' => false],
        ]]),
        'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => []]),
    ]);

    SyncSlackPublicChannels::run($this->organisation->group, history: true, catchUpOnly: true);
    SyncSlackPublicChannels::run($this->organisation->group, history: true);

    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.history') || str_contains($request->url(), 'conversations.join'));
    expect($this->archived->messages()->count())->toBe(0);
});

test('no Slack token reaches the database, plain, URL-encoded or of another kind, and a message still holding one is withheld', function () {
    $rows = ($this->import)();

    $stored = SlackMessage::where('slack_channel_id', $this->tokens->id)->orderBy('ts')->get();
    expect($rows[$this->tokens->name])->toMatchArray(['status' => 'imported', 'in_export' => 4, 'withheld' => 1, 'stored' => 3])
        ->and(json_encode($stored->pluck('data')))->not->toMatch('/xox[a-z]-/i')
        ->and($stored->pluck('text')->implode(' '))->not->toMatch('/xox[a-z]-/i')
        ->and($stored[1]->text)->toBe('the token  was pasted here')
        ->and($stored[2]->data['files'][0]['url_private'])->toBe('https://files.slack.com/files-pri/T1-F3/b.png?w=360')
        ->and($stored[2]->data['files'][0]['thumb_64'])->toBe('https://files.slack.com/files-tmb/T1-F3/b_64.png');
});

test('a channel name that leaves the export folder, and the private lists and folders of the export, are never read', function () {
    $before = SlackMessage::count();

    $rows = ($this->import)();

    expect($rows['../outside-'.$this->s]['status'])->toBe('skipped: unsafe folder name')
        ->and($this->escape->messages()->count())->toBe(0)
        ->and(SlackMessage::whereIn('text', ['outside', 'private'])->where('ts', 'like', '15463008%')->count())->toBe(0)
        ->and(SlackMessage::count() - $before)->toBe(3 + 3);
});

test('private channels the aiku bot was added to come whole, with members and history, and stay hidden from every public path, INI-431', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Event::fake([SlackChannelMessageChanged::class]);
    $group   = $this->organisation->group;
    $slackId = "GBOT{$this->s}";
    $listed  = true;
    Http::fake([
        'slack.com/api/conversations.list*'    => function () use (&$listed, $slackId) {
            return Http::response(['ok' => true, 'channels' => $listed ? [['id' => $slackId, 'name' => 'managers', 'is_private' => true, 'is_archived' => false]] : []]);
        },
        'slack.com/api/conversations.members*' => Http::response(['ok' => true, 'members' => ['UA1', 'UB1']]),
        'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [
            ['user' => 'UA1', 'text' => 'budget talk', 'ts' => '1789400000.000100', 'reply_count' => 1],
        ]]),
        'slack.com/api/conversations.replies*' => Http::response(['ok' => true, 'messages' => [
            ['user' => 'UA1', 'text' => 'budget talk', 'ts' => '1789400000.000100', 'thread_ts' => '1789400000.000100'],
            ['user' => 'UB1', 'text' => 'agreed', 'ts' => '1789400100.000100', 'thread_ts' => '1789400000.000100'],
        ]]),
    ]);

    $totals = SyncSlackPrivateChannels::run($group, true);

    $channel = SlackChannel::where('slack_id', $slackId)->firstOrFail();
    expect($totals)->toMatchArray(['channels' => 1, 'members' => 2, 'messages' => 2, 'added' => 2, 'failed' => []])
        ->and($channel->is_private)->toBeTrue()
        ->and($channel->is_public)->toBeFalse()
        ->and($channel->members()->pluck('slack_user_id')->sort()->values()->all())->toBe(['UA1', 'UB1'])
        ->and($channel->messages()->orderBy('ts')->pluck('text')->all())->toBe(['budget talk', 'agreed'])
        ->and(SlackChannel::kept()->whereKey($channel->id)->exists())->toBeFalse()
        ->and(SlackChannel::keptPrivate()->whereKey($channel->id)->exists())->toBeTrue()
        ->and(User::where('group_id', $group->id)->get()->contains(fn (User $user) => $channel->isVisibleTo($user)))->toBeFalse();
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'conversations.list') && $request['types'] === 'private_channel');
    Event::assertNotDispatched(SlackChannelMessageChanged::class);

    SyncSlackChannelMembers::run();
    expect($channel->members()->count())->toBe(2);

    $listed = false;
    SyncSlackPrivateChannels::run($group, true);
    expect($channel->refresh()->is_member)->toBeFalse()
        ->and($channel->messages()->count())->toBe(2);
});

test('live messages of a private channel go only through the private path, once, with edits and deletions, and never DMs, INI-431', function () {
    Event::fake([SlackChannelMessageChanged::class]);
    Queue::fake();
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $group   = $this->organisation->group;
    $channel = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "GLIVE{$this->s}", 'name' => 'leads', 'is_public' => false, 'is_private' => true, 'is_member' => true]);
    $event   = ['type' => 'message', 'channel' => $channel->slack_id, 'channel_type' => 'group', 'user' => 'UA1', 'text' => 'plan', 'ts' => '1789500000.000100'];

    expect(StoreSlackMessage::run($event))->toBeNull()
        ->and(StoreSlackPrivateMessage::run($event)?->text)->toBe('plan')
        ->and(StoreSlackPrivateMessage::run($event)?->text)->toBe('plan')
        ->and($channel->messages()->count())->toBe(1);

    StoreSlackPrivateMessage::run(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $channel->slack_id, 'channel_type' => 'group',
        'message' => ['user' => 'UA1', 'text' => 'new plan', 'ts' => '1789500000.000100', 'edited' => ['ts' => '1789500050.000100']]]);
    expect($channel->messages()->value('text'))->toBe('new plan');

    StoreSlackPrivateMessage::run(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => $channel->slack_id, 'channel_type' => 'group', 'deleted_ts' => '1789500000.000100']);
    expect($channel->messages()->count())->toBe(0)
        ->and(SlackMessage::withTrashed()->where('slack_channel_id', $channel->id)->count())->toBe(1);

    expect(StoreSlackPrivateMessage::run([...$event, 'channel_type' => 'im', 'ts' => '1789500200.000100']))->toBeNull()
        ->and(StoreSlackPrivateMessage::run([...$event, 'channel_type' => 'mpim', 'ts' => '1789500300.000100']))->toBeNull()
        ->and(StoreSlackPrivateMessage::run([...$event, 'channel' => "GNOTBROUGHT{$this->s}"]))->toBeNull()
        ->and(StoreSlackPrivateMessage::run([...$event, 'channel' => $this->live->slack_id]))->toBeNull();
    $channel->update(['is_member' => false]);
    expect(StoreSlackPrivateMessage::run([...$event, 'ts' => '1789500400.000100']))->toBeNull();
    Event::assertNotDispatched(SlackChannelMessageChanged::class);

    $channel->update(['is_member' => true]);
    UpdateSlackChannelMember::run(['type' => 'member_joined_channel', 'user' => 'UC1', 'channel' => $channel->slack_id, 'channel_type' => 'G']);
    expect($channel->members()->where('slack_user_id', 'UC1')->exists())->toBeTrue();
    UpdateSlackChannelMember::run(['type' => 'member_left_channel', 'user' => 'UC1', 'channel' => $channel->slack_id, 'channel_type' => 'G']);
    expect($channel->members()->where('slack_user_id', 'UC1')->exists())->toBeFalse();

    UpdateSlackChannelFromEvent::run(['type' => 'member_joined_channel', 'user' => 'UAIKUBOT', 'channel' => "GNEW{$this->s}", 'channel_type' => 'G'], 'UAIKUBOT');
    SyncSlackPrivateChannels::assertPushed(fn ($action, $arguments) => $arguments[2] === "GNEW{$this->s}" && $arguments[1] === true);
    UpdateSlackChannelFromEvent::run(['type' => 'member_left_channel', 'user' => 'UAIKUBOT', 'channel' => $channel->slack_id, 'channel_type' => 'G'], 'UAIKUBOT');
    expect($channel->refresh()->is_member)->toBeFalse();
});

test('a private channel is read by its members only, on every path, and never by a non-member, a group admin or an engineer, INI-435/INI-564', function () {
    $group = $this->organisation->group;
    app()->instance('group', $group);
    setPermissionsTeamId($group->id);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    $languageId = \App\Models\Helpers\Language::value('id');
    $person     = function (string $key, ?string $role = null) use ($group, $languageId): User {
        $user = User::factory()->create(['group_id' => $group->id, 'language_id' => $languageId]);
        if ($role) {
            $user->assignRole($role);
        }
        \App\Models\Chat\SlackUser::create(['slack_id' => "U{$key}{$this->s}", 'user_id' => $user->id, 'email' => $user->email, 'real_name' => $key]);

        return $user->refresh();
    };
    $member   = $person('MEMB');
    $outsider = $person('OUTS');
    $admin    = $person('ADMN', 'group-admin');
    $engineer = $person('ENGR', 'help-desk-clerk');

    $channel = SlackChannel::create(['group_id' => $group->id, 'slack_id' => "GSEE{$this->s}", 'name' => "secret-{$this->s}", 'is_public' => false, 'is_private' => true, 'is_member' => true, 'is_archived' => false]);
    $channel->members()->create(['slack_user_id' => "UMEMB{$this->s}"]);
    $message = SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => '1789600000.000100', 'thread_ts' => '1789600000.000100', 'slack_user_id' => "UMEMB{$this->s}", 'text' => "hush {$this->s}", 'sent_at' => now()->subHour(), 'edited_at' => now()->subMinutes(30), 'pinned_at' => now(),
        'data' => ['files' => [['id' => "FSEE{$this->s}", 'name' => 'plan.pdf', 'mode' => 'hosted']]]]);
    SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => '1789600100.000100', 'thread_ts' => '1789600000.000100', 'slack_user_id' => "UMEMB{$this->s}", 'text' => "reply {$this->s}", 'sent_at' => now()->subMinutes(50)]);
    $publicTwin = SlackMessage::create(['slack_channel_id' => $this->live->id, 'ts' => '1789600200.000100', 'slack_user_id' => "UMEMB{$this->s}", 'text' => "hush {$this->s} out loud", 'sent_at' => now()->subMinutes(40)]);
    \App\Actions\Chat\Staff\Slack\RefreshSlackMessageSearch::make()->handle(collect([$message, $publicTwin]));
    \App\Models\Chat\StaffMessageStory::storeFor($message, ['category' => 'other', 'importance' => 5, 'headline' => "story {$this->s}", 'blurb' => 'b'], 'quick');
    \App\Models\Chat\SlackMessageVersion::create(['slack_message_id' => $message->id, 'text' => "older {$this->s}", 'data' => [], 'replaced_at' => now()->subMinutes(30)]);

    $secret = fn ($response) => str_contains($response->getContent(), "secret-{$this->s}") || preg_match('/hush '.$this->s.'(?! out loud)/', $response->getContent()) === 1;
    $search = fn (User $user) => actingAs($user)->getJson(route('grp.chat.staff.slack.search', ['q' => "hush {$this->s}"]))->assertOk();
    expect(\App\Models\Helpers\Ticket::canBeManagedBy($engineer))->toBeTrue()
        ->and(config('mcp_sql_tiers.never'))->toContain('slack_*')
        ->and((fn () => $this->slackChannelIds($this->group ?? \App\Models\SysAdmin\Group::find($channel->group_id)))->call(\App\Actions\Chat\Staff\UI\ShowStaffChatReports::make()))->not->toContain($channel->id);
    $lists  = function (User $user, bool $withUnread = false) {
        $props = null;
        actingAs($user)->get(route('grp.chat.staff.groups.index'))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props) {
                $props = $page->toArray()['props'];
                $page->loadDeferredProps(function (AssertableInertia $reload) use (&$props) {
                    $props = [...$props, ...$reload->toArray()['props']];
                });
            });

        if ($withUnread) {
            return $props['slack_private_channels'];
        }

        return [collect($props['slack_channels'])->pluck('id')->all(), collect($props['slack_private_channels'])->pluck('id')->all()];
    };
    $json = fn (string $name, array $query = []) => route("grp.chat.staff.slack.$name", ['slackChannel' => $channel->id, ...$query]);

    foreach ([$outsider, $admin, $engineer] as $refused) {
        expect($channel->isReadableBy($refused))->toBeFalse()
            ->and($channel->isPrivateVisibleTo($refused))->toBeFalse()
            ->and($channel->isVisibleTo($refused))->toBeFalse()
            ->and($this->live->isVisibleTo($refused))->toBeTrue();
        [$public, $private] = $lists($refused);
        expect($public)->not->toContain($channel->id)->and($private)->not->toContain($channel->id);
        actingAs($refused)->get(route('grp.chat.staff.groups.slack.show', $channel->id))->assertForbidden();
        actingAs($refused)->get(route('grp.chat.staff.slack.show', $channel->id))->assertForbidden();
        foreach ([$json('thread', ['ts' => $message->ts]), $json('versions', ['ts' => $message->ts]), $json('pins'), $json('files-links')] as $url) {
            expect(actingAs($refused)->getJson($url)->status())->toBeIn([403, 404]);
        }
        actingAs($refused)->postJson($json('read'))->assertForbidden();
        actingAs($refused)->postJson(route('grp.chat.staff.slack.read-all'))->assertSuccessful();
        expect(\Illuminate\Support\Facades\DB::table('slack_channel_reads')->where('user_id', $refused->id)->where('slack_channel_id', $channel->id)->exists())->toBeFalse()
            ->and($search($refused)->getContent())->toContain('out loud')
            ->and($secret($search($refused)))->toBeFalse()
            ->and(\App\Models\Chat\StaffMessageStory::query()->visibleTo($refused)->where('slack_message_id', $message->id)->exists())->toBeFalse();
        actingAs($refused)->get(route('grp.chat.staff.slack.files.show', ['slackMessage' => $message->id, 'slackFile' => "FSEE{$this->s}"]))->assertNotFound();
    }

    [$public, $private] = $lists($member);
    expect($private)->toBe([$channel->id])->and($public)->not->toContain($channel->id)
        ->and($channel->isVisibleTo($member))->toBeFalse()
        ->and($search($member)->getContent())->toContain('out loud')
        ->and($secret($search($member)))->toBeFalse()
        ->and(\App\Models\Chat\StaffMessageStory::query()->visibleTo($member)->where('slack_message_id', $message->id)->exists())->toBeFalse();

    actingAs($member)->get(route('grp.chat.staff.slack.show', [$channel->id, 'thread' => $message->ts]))
        ->assertRedirect(route('grp.chat.staff.groups.slack.show', ['thread' => $message->ts, 'slackChannel' => $channel->id]));
    $rows = null;
    actingAs($member)->get(route('grp.chat.staff.groups.slack.show', $channel->id))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$rows) {
            $page->where('slack_channel.is_private', true)->where('slack_channel.write', null);
            $page->loadDeferredProps(function (AssertableInertia $reload) use (&$rows) {
                $rows = $reload->toArray()['props']['slack_messages']['data'];
            });
        });
    $row = collect($rows)->firstWhere('ts', $message->ts);
    expect($row['files'][0])->toMatchArray(['name' => 'plan.pdf', 'state' => 'pending', 'note' => 'File not available yet', 'url' => null, 'external_url' => null])
        ->and(actingAs($member)->getJson($json('thread', ['ts' => $message->ts]))->assertOk()->getContent())->toContain("reply {$this->s}")
        ->and(actingAs($member)->getJson($json('versions', ['ts' => $message->ts]))->assertOk()->getContent())->toContain("older {$this->s}")
        ->and(actingAs($member)->getJson($json('pins'))->assertOk()->json())->toHaveCount(1);
    actingAs($member)->getJson($json('files-links'))->assertOk();
    actingAs($member)->postJson($json('read'))->assertSuccessful();
    expect(\Illuminate\Support\Facades\DB::table('slack_channel_reads')->where('user_id', $member->id)->where('slack_channel_id', $channel->id)->exists())->toBeTrue();

    Event::fake([SlackChannelMessageChanged::class]);
    Notification::fake();
    StoreSlackPrivateMessage::run(['type' => 'message', 'channel' => $channel->slack_id, 'channel_type' => 'group', 'user' => "UADMN{$this->s}",
        'text' => "<@UOUTS{$this->s}> <@UMEMB{$this->s}> ping", 'ts' => sprintf('%d.000100', now()->timestamp)]);
    Event::assertNotDispatched(SlackChannelMessageChanged::class);
    Notification::assertNothingSent();
    $unread = collect($lists($member, withUnread: true))->firstWhere('id', $channel->id);
    expect($unread['unread_count'])->toBe(1);
    actingAs($member)->get(route('grp.chat.staff.slack.files.show', ['slackMessage' => $message->id, 'slackFile' => "FSEE{$this->s}"]))->assertNotFound();
    actingAs($member)->postJson(route('grp.chat.staff.slack.messages.store', $channel->id), ['text' => 'hi'])->assertForbidden();
    actingAs($member)->postJson(route('grp.chat.staff.slack.reactions.toggle', $channel->id), ['ts' => $message->ts, 'name' => 'tada'])->assertForbidden();

    $channel->members()->delete();
    expect($channel->isReadableBy($member))->toBeFalse();
    actingAs($member)->get(route('grp.chat.staff.groups.slack.show', $channel->id))->assertForbidden();
    $member->update(['status' => false]);
    $channel->members()->create(['slack_user_id' => "UMEMB{$this->s}"]);
    expect($channel->isReadableBy($member->refresh()))->toBeFalse();
});
