<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\ImportSlackExportArchivedChannels;
use App\Actions\Chat\Staff\Slack\SyncSlackPublicChannels;
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
