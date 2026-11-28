<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\Slack\CopySlackMessageFile;
use App\Actions\Chat\Staff\Slack\Write\SendSlackMessage;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Chat\Staff\Slack\Write\UploadSlackLargeFile;
use App\Events\BroadcastPersonalNotification;
use App\Events\SlackChannelMessageChanged;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\VerbaChannelSettingsTool;
use App\Mcp\Tools\VerbaPreferencesTool;
use App\Mcp\Tools\VerbaSendTool;
use App\Mcp\Tools\VerbaTool;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use App\Notifications\SlackMentionNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Event::fake([SlackChannelMessageChanged::class, BroadcastPersonalNotification::class]);
    $web = Website::has('shop')->first();
    if (!$web) {
        list($this->organisation, $this->user) = createShop();
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
    }
    $this->group = $this->organisation->group;
    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $this->s       = Str::upper(Str::random(6));
    $newUser       = fn (array $attributes = []) => User::factory()->create(array_merge(['group_id' => $this->group->id, 'language_id' => $this->user->language_id], $attributes));
    $this->writer  = $newUser();
    $this->writer->assignRole('help-desk-clerk');
    $this->reader  = $newUser();
    $this->bob     = $newUser(['settings' => ['notifications' => ['slack_mention' => ['browser']]]]);
    SlackUser::create(['slack_id' => "UWRITE{$this->s}", 'user_id' => $this->writer->id, 'email' => $this->writer->email, 'real_name' => 'Writer Person']);
    SlackUser::create(['slack_id' => "UREAD{$this->s}", 'user_id' => $this->reader->id, 'email' => $this->reader->email, 'real_name' => 'Reader Person']);
    SlackUser::create(['slack_id' => "UBOB{$this->s}", 'user_id' => $this->bob->id, 'email' => $this->bob->email, 'real_name' => 'Bob Person']);
    $this->token = 'xoxp-test-'.$this->s;
    SlackConnection::create(['user_id' => $this->writer->id, 'slack_user_id' => "UWRITE{$this->s}", 'team_id' => 'T1', 'token' => $this->token, 'scopes' => implode(',', SlackWriteAccess::USER_SCOPES), 'connected_at' => now()]);

    $channel        = fn (string $key, array $attributes = []) => SlackChannel::create(array_merge(['group_id' => $this->group->id, 'slack_id' => "C$key{$this->s}", 'name' => strtolower($key)."-{$this->s}", 'is_member' => true], $attributes));
    $this->general  = $channel('GENERAL');
    $this->archived = $channel('ARCHIVED', ['is_archived' => true]);
    $this->ignored  = $channel('IGNORED', ['is_ignored' => true]);
    $this->private  = $channel('PRIVATE', ['is_public' => false]);
    $this->gone     = $channel('GONE', ['deleted_in_slack_at' => now()]);

    $this->nextTs      = 0;
    $this->slackAnswer = null;
    Http::preventStrayRequests();
    Http::fake(['slack.com/api/chat.postMessage' => function (Request $request) {
        if ($this->slackAnswer instanceof Closure) {
            return ($this->slackAnswer)($request);
        }
        if ($this->slackAnswer) {
            return Http::response($this->slackAnswer);
        }
        $ts      = sprintf('%d.%06d', now()->timestamp, ++$this->nextTs);
        $message = array_filter(['type' => 'message', 'user' => "UWRITE{$this->s}", 'text' => $request['text'], 'ts' => $ts, 'thread_ts' => $request['thread_ts'] ?? null]);

        return Http::response(['ok' => true, 'channel' => $request['channel'], 'ts' => $ts, 'message' => $message]);
    }]);
    $this->slack = function (array|Closure|null $answer = null) {
        $this->slackAnswer = $answer;
    };
    $this->send = fn (SlackChannel $channel, string $text, ?string $threadTs = null, ?string $key = null, ?User $as = null) => actingAs($as ?? $this->writer)
        ->postJson(route('grp.chat.staff.slack.messages.store', ['slackChannel' => $channel->id]), array_filter(['text' => $text, 'thread_ts' => $threadTs, 'idempotency_key' => $key ?? Str::uuid()->toString()]));
    $this->posts    = fn () => Http::recorded(fn (Request $request) => str_contains($request->url(), 'chat.postMessage'));
    $this->messages = fn (SlackChannel $channel) => SlackMessage::withTrashed()->where('slack_channel_id', $channel->id)->orderBy('ts')->get();
    $this->kinds    = fn () => Event::dispatched(SlackChannelMessageChanged::class)->map(fn (array $args) => $args[0]->kind.':'.$args[0]->message->ts)->values()->all();
});

test('a message is posted with the person own token, stored at once as that person from Slack\'s real answer and announced once; the echo event changes nothing and rings no one again, INI-454', function () {
    ($this->slack)(function (Request $request) {
        $ts = sprintf('%d.%06d', now()->timestamp, 700001);

        return Http::response([
            'ok'      => true,
            'channel' => $request['channel'],
            'ts'      => $ts,
            'message' => [
                'user'        => "UWRITE{$this->s}",
                'type'        => 'message',
                'ts'          => $ts,
                'bot_id'      => 'B0AIKU',
                'app_id'      => 'A0AIKU',
                'text'        => "Hi <@UBOB{$this->s}>, the *blue* pallets are in &amp; ready",
                'team'        => 'T1',
                'bot_profile' => ['id' => 'B0AIKU', 'app_id' => 'A0AIKU', 'name' => 'aiku', 'deleted' => false, 'team_id' => 'T1'],
                'blocks'      => [['type' => 'rich_text', 'block_id' => 'x', 'elements' => []]],
            ],
        ]);
    });

    $response = ($this->send)($this->general, 'Hi Bob, the *blue* pallets are in & ready')->assertOk();

    $posts = ($this->posts)();
    expect($posts)->toHaveCount(1);
    [$request] = $posts[0];
    expect($request->header('Authorization')[0])->toBe('Bearer '.$this->token)
        ->and($request['channel'])->toBe($this->general->slack_id)
        ->and($request['text'])->toBe('Hi Bob, the *blue* pallets are in &amp; ready')
        ->and($request->data())->not->toHaveKeys(['link_names', 'parse', 'thread_ts']);

    $stored = ($this->messages)($this->general);
    expect($stored)->toHaveCount(1)
        ->and($stored[0]->user_id)->toBe($this->writer->id)
        ->and($stored[0]->slack_user_id)->toBe("UWRITE{$this->s}")
        ->and($stored[0]->subtype)->toBeNull()
        ->and($response->json('ts'))->toBe($stored[0]->ts)
        ->and($response->json('message.ts'))->toBe($stored[0]->ts)
        ->and(($this->kinds)())->toBe(['new:'.$stored[0]->ts])
        ->and($this->bob->notifications()->where('type', SlackMentionNotification::class)->count())->toBe(1);

    StoreSlackMessage::make()->handle([
        'type'         => 'message',
        'channel'      => $this->general->slack_id,
        'channel_type' => 'channel',
        'user'         => "UWRITE{$this->s}",
        'text'         => $stored[0]->text,
        'ts'           => $stored[0]->ts,
        'bot_id'       => 'B0AIKU',
        'app_id'       => 'A0AIKU',
    ]);

    expect(($this->messages)($this->general))->toHaveCount(1)
        ->and(DB::table('slack_message_versions')->where('slack_message_id', $stored[0]->id)->count())->toBe(0)
        ->and(($this->kinds)())->toBe(['new:'.$stored[0]->ts, 'changed:'.$stored[0]->ts])
        ->and($this->bob->notifications()->where('type', SlackMentionNotification::class)->count())->toBe(1);
});

test('when the echo event is stored first, the send neither adds a row nor announces it as new a second time, INI-454', function () {
    $ts = sprintf('%d.%06d', now()->timestamp, 900001);
    ($this->slack)(['ok' => true, 'channel' => $this->general->slack_id, 'ts' => $ts, 'message' => ['type' => 'message', 'user' => "UWRITE{$this->s}", 'text' => 'Echo first', 'ts' => $ts]]);
    StoreSlackMessage::make()->handle(['type' => 'message', 'channel' => $this->general->slack_id, 'channel_type' => 'channel', 'user' => "UWRITE{$this->s}", 'text' => 'Echo first', 'ts' => $ts]);

    ($this->send)($this->general, 'Echo first')->assertOk()->assertJsonPath('message.ts', $ts);

    expect(($this->messages)($this->general))->toHaveCount(1)
        ->and(($this->kinds)())->toBe(['new:'.$ts]);
});

test('a thread reply goes to the root of the thread, also when answering a reply, and is stored under it, INI-456', function () {
    ($this->slack)();
    $rootTs = ($this->send)($this->general, 'Root')->json('ts');
    $replyTs = ($this->send)($this->general, 'First reply', $rootTs)->assertOk()->json('ts');
    $response = ($this->send)($this->general, 'Answer to the reply', $replyTs)->assertOk();

    $posts = ($this->posts)();
    expect($posts[1][0]['thread_ts'])->toBe($rootTs)
        ->and($posts[2][0]['thread_ts'])->toBe($rootTs)
        ->and(SlackMessage::where('slack_channel_id', $this->general->id)->where('ts', $replyTs)->value('thread_ts'))->toBe($rootTs)
        ->and($response->json('parent.ts'))->toBe($rootTs)
        ->and($response->json('parent.reply_count'))->toBe(2);
});

test('a thread reply sent also to the channel asks Slack to broadcast it and shows in the channel stream; without the tick or outside a thread nothing is broadcast, INI-537', function () {
    ($this->slack)();
    $rootTs = ($this->send)($this->general, 'Root')->json('ts');

    $broadcast = actingAs($this->writer)->postJson(route('grp.chat.staff.slack.messages.store', ['slackChannel' => $this->general->id]), ['text' => 'For everyone', 'thread_ts' => $rootTs, 'also_to_channel' => true, 'idempotency_key' => Str::uuid()->toString()])->assertOk();
    ($this->send)($this->general, 'Only in the thread', $rootTs)->assertOk();
    actingAs($this->writer)->postJson(route('grp.chat.staff.slack.messages.store', ['slackChannel' => $this->general->id]), ['text' => 'Not a reply', 'also_to_channel' => true, 'idempotency_key' => Str::uuid()->toString()])->assertOk();

    $posts = ($this->posts)();
    expect($posts[1][0]['reply_broadcast'])->toBe('true')
        ->and($posts[2][0]->data())->not->toHaveKey('reply_broadcast')
        ->and($posts[3][0]->data())->not->toHaveKeys(['reply_broadcast', 'thread_ts'])
        ->and(SlackMessage::where('slack_channel_id', $this->general->id)->where('ts', $broadcast->json('ts'))->value('subtype'))->toBe('thread_broadcast')
        ->and($broadcast->json('message.is_broadcast'))->toBeTrue()
        ->and(SlackMessage::where('slack_channel_id', $this->general->id)->where('text', 'Only in the thread')->value('subtype'))->toBeNull();
});

test('a double submit or a retry with the same key posts once and answers with the same message, INI-454', function () {
    ($this->slack)();

    $first  = ($this->send)($this->general, 'Only once', key: 'key-1')->assertOk();
    $second = ($this->send)($this->general, 'Only once', key: 'key-1')->assertOk();

    expect(($this->posts)())->toHaveCount(1)
        ->and($second->json('ts'))->toBe($first->json('ts'))
        ->and(($this->messages)($this->general))->toHaveCount(1);
});

test('everything typed between < and > is sent as plain text, so no channel, user group, mention or disguised link reaches Slack; a bare web address stays a link, INI-454', function () {
    ($this->slack)();

    ($this->send)($this->general, "Look <!channel> <!channel > <!here|here> <!EVERYONE> <!group> <!subteam^S1|@team> <@UBOB{$this->s}> <#C1|general> <https://evil.test|aiku.io> <https://aiku.io/a?b=1&c=2> a<b>c")->assertOk();

    expect(($this->posts)()[0][0]['text'])->toBe("Look &lt;!channel&gt; &lt;!channel &gt; &lt;!here|here&gt; &lt;!EVERYONE&gt; &lt;!group&gt; &lt;!subteam^S1|@team&gt; &lt;@UBOB{$this->s}&gt; &lt;#C1|general&gt; &lt;https://evil.test|aiku.io&gt; <https://aiku.io/a?b=1&c=2> a&lt;b&gt;c")
        ->and($this->bob->notifications()->where('type', SlackMentionNotification::class)->count())->toBe(0);
});

test('when Slack does not answer, the key stays claimed so a retry posts nothing new; any other failure frees it and answers with a sentence, INI-454', function () {
    ($this->slack)(fn () => throw new ConnectionException('timed out'));
    ($this->send)($this->general, 'Hello', key: 'lost-1')->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'did not answer'));

    ($this->slack)();
    ($this->send)($this->general, 'Hello', key: 'lost-1')->assertUnprocessable()->assertJsonPath('message', 'This message is already being sent.');
    expect(($this->posts)())->toHaveCount(0)
        ->and(SlackMessage::where('slack_channel_id', $this->general->id)->count())->toBe(0);

    ($this->slack)(fn () => throw new RuntimeException('could not decrypt'));
    ($this->send)($this->general, 'Hello', key: 'broken-1')->assertUnprocessable()->assertJsonPath('message', 'The message was not sent. Try again.');

    ($this->slack)();
    ($this->send)($this->general, 'Hello', key: 'broken-1')->assertOk();
    expect(SlackMessage::where('slack_channel_id', $this->general->id)->count())->toBe(1);
});

test('when the webhook stores the same message between our check and our insert, the send reads that row and does not announce it as new, INI-454', function () {
    ($this->slack)();
    $store = StoreSlackMessage::partialMock();
    $store->shouldReceive('store')->once()->andReturnUsing(function (SlackChannel $channel, array $message) {
        SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => $message['ts'], 'slack_user_id' => "UWRITE{$this->s}", 'text' => $message['text'], 'sent_at' => now(), 'data' => $message]);
        throw new UniqueConstraintViolationException('pgsql', 'insert into slack_messages', [], new Exception('duplicate key value'));
    });
    $store->shouldReceive('store')->passthru();

    $response = ($this->send)($this->general, 'Raced')->assertOk();

    expect(($this->messages)($this->general))->toHaveCount(1)
        ->and($response->json('message.ts'))->toBe(($this->messages)($this->general)[0]->ts)
        ->and(($this->kinds)())->toBe([]);
});

test('refused before Slack is called: a channel that is hidden, archived, not public, ignored or gone, a person who may not write or is not connected, an empty or too long text and an unknown thread, INI-454 INI-456', function () {
    ($this->slack)();
    $foreign = SlackMessage::create(['slack_channel_id' => $this->archived->id, 'ts' => '1700000000.000001', 'text' => 'Elsewhere', 'sent_at' => now()]);

    foreach ([$this->archived, $this->ignored, $this->private, $this->gone] as $channel) {
        ($this->send)($channel, 'Hello')->assertForbidden();
    }
    ($this->send)($this->general, 'Hello', as: $this->reader)->assertUnprocessable()->assertJsonPath('message', 'Connect your Slack account to write here');
    ($this->send)($this->general, '   ')->assertUnprocessable();
    ($this->send)($this->general, str_repeat('a', SendSlackMessage::MAX_LENGTH + 1))->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'too long'));
    ($this->send)($this->general, str_repeat('&', 1500))->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'too long'));
    ($this->send)($this->general, 'Hello', $foreign->ts)->assertUnprocessable()->assertJsonPath('message', 'This thread is no longer in Slack.');
    ($this->send)($this->general, 'Hello', '1700000000.999999')->assertUnprocessable();

    SlackConnection::where('user_id', $this->writer->id)->update(['needs_reconnect' => true]);
    ($this->send)($this->general, 'Hello')->assertUnprocessable()->assertJsonPath('message', 'Reconnect your Slack account to write here');

    expect(($this->posts)())->toHaveCount(0)
        ->and(SlackMessage::where('slack_channel_id', $this->general->id)->count())->toBe(0);
});

test('a Slack error stores nothing, answers with a short sentence and lets the same key retry, INI-454', function () {
    ($this->slack)(['ok' => false, 'error' => 'not_in_channel']);
    ($this->send)($this->general, 'Hello', key: 'retry-1')->assertUnprocessable()->assertJsonPath('message', 'You are not a member of this channel in Slack. Join it in Slack, then send again.');

    ($this->slack)(['ok' => false, 'error' => 'token_revoked']);
    ($this->send)($this->general, 'Hello', key: 'retry-1')->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'needs renewing'));

    expect(($this->posts)())->toHaveCount(2)
        ->and(SlackMessage::where('slack_channel_id', $this->general->id)->count())->toBe(0)
        ->and(SlackConnection::where('user_id', $this->writer->id)->value('needs_reconnect'))->toBeTrue()
        ->and(Event::dispatched(SlackChannelMessageChanged::class))->toHaveCount(0);
});

test('the channel screen tells the composer who may write, and why not yet, INI-454', function () {
    $state = function (User $user): array {
        $write = null;
        actingAs($user)->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->general->id]))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$write) {
                $write = $page->toArray()['props']['slack_channel']['write'];
            });

        return $write;
    };

    expect($state($this->writer))->toMatchArray(['allowed' => true, 'reason' => null])
        ->and($state($this->reader))->toMatchArray(['allowed' => true, 'reason' => 'Connect your Slack account to write here']);

    SlackConnection::where('user_id', $this->writer->id)->delete();
    expect($state($this->writer)['reason'])->toBe('Connect your Slack account to write here');
});

test('a mention chosen in the box becomes Slack\'s own code for a live person, a channel the writer may see, here and channel; typed codes, leavers, bots, hidden channels and unknown ids stay plain text, INI-461', function () {
    ($this->slack)();
    SlackUser::create(['slack_id' => "ULEFT{$this->s}", 'real_name' => 'Left Person', 'is_deleted' => true]);
    SlackUser::create(['slack_id' => "UBOT{$this->s}", 'real_name' => 'A Bot', 'is_bot' => true]);
    $this->general->members()->create(['slack_user_id' => "UWRITE{$this->s}"]);
    $chosen = fn (string $token) => "\u{E000}{$token}\u{E000}";

    ($this->send)($this->general, $chosen("@UBOB{$this->s}")." look at ".$chosen("#CGENERAL{$this->s}")." and ".$chosen("#CPRIVATE{$this->s}").', '.$chosen("@ULEFT{$this->s}").' '.$chosen("@UBOT{$this->s}").' '.$chosen('@UNKNOWN1').' '.$chosen('!here').' <@UREAD'.$this->s.'> '.$chosen("@UBOB{$this->s}"))->assertOk();

    expect(($this->posts)()[0][0]['text'])->toBe("<@UBOB{$this->s}> look at <#CGENERAL{$this->s}> and #CPRIVATE{$this->s}, @ULEFT{$this->s} @UBOT{$this->s} @UNKNOWN1 <!here> &lt;@UREAD{$this->s}&gt; <@UBOB{$this->s}>")
        ->and(($this->messages)($this->general)->first()->text)->toContain("<@UBOB{$this->s}>")
        ->and($this->bob->notifications()->where('type', SlackMentionNotification::class)->count())->toBe(1);
});

test('here or channel in a channel of more than six members is sent only once the writer confirmed it, INI-461', function () {
    ($this->slack)();
    foreach (range(1, SendSlackMessage::EVERYONE_CONFIRM_ABOVE + 1) as $member) {
        $this->general->members()->create(['slack_user_id' => "UMEMBER{$member}{$this->s}"]);
    }

    ($this->send)($this->general, "Hello \u{E000}!channel\u{E000}")->assertStatus(422)->assertJsonValidationErrors('notify_everyone');
    ($this->send)($this->general, "Hi \u{E000}!here\u{E000}")->assertStatus(422);
    expect(($this->posts)())->toHaveCount(0)
        ->and(SendSlackMessage::needsEveryoneConfirmation(0))->toBeTrue()
        ->and(SendSlackMessage::needsEveryoneConfirmation(SendSlackMessage::EVERYONE_CONFIRM_ABOVE))->toBeFalse()
        ->and(SendSlackMessage::needsEveryoneConfirmation(SendSlackMessage::EVERYONE_CONFIRM_ABOVE + 1))->toBeTrue();

    actingAs($this->writer)->postJson(route('grp.chat.staff.slack.messages.store', ['slackChannel' => $this->general->id]), ['text' => "Hello \u{E000}!channel\u{E000}", 'notify_everyone' => true, 'idempotency_key' => Str::uuid()->toString()])->assertOk();
    expect(($this->posts)()[0][0]['text'])->toBe('Hello <!channel>');
});

test('a message sent from aiku is marked so, and Slack\'s echo event keeps the mark; a message written in Slack is not marked, INI-454 INI-473', function () {
    ($this->slack)();
    $ts = ($this->send)($this->general, 'From aiku')->assertOk()->json('ts');

    StoreSlackMessage::make()->store($this->general, ['type' => 'message', 'user' => "UWRITE{$this->s}", 'text' => 'From aiku', 'ts' => $ts, 'bot_id' => 'B1']);
    StoreSlackMessage::make()->store($this->general, ['type' => 'message', 'user' => "UBOB{$this->s}", 'text' => 'From Slack', 'ts' => '1700000000.000100']);

    expect(($this->messages)($this->general)->pluck('sent_from_aiku', 'text')->all())->toBe(['From Slack' => false, 'From aiku' => true]);
});

test('the composer gets the people and channels it may mention, here and channel first, then members of this channel, without leavers, bots or hidden channels, INI-461', function () {
    SlackUser::create(['slack_id' => "ULEFT{$this->s}", 'real_name' => 'Left Person', 'is_deleted' => true]);
    $this->general->members()->create(['slack_user_id' => "UREAD{$this->s}"]);

    $write = null;
    actingAs($this->writer)->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->general->id]))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$write) {
            $write = $page->toArray()['props']['slack_channel']['write'];
        });
    $people = collect($write['people'])->pluck('id');

    expect($people->take(3)->all())->toBe(['!here', '!channel', "UREAD{$this->s}"])
        ->and($people)->toContain("UBOB{$this->s}")->not->toContain("ULEFT{$this->s}")
        ->and(collect($write['channels'])->pluck('id'))->toContain("CGENERAL{$this->s}")->not->toContain("CPRIVATE{$this->s}")
        ->and($write['member_count'])->toBe(1);
});

test('what the box sends comes back from the renderer with the same names it showed as chips, INI-461', function () {
    ($this->slack)();
    $chosen = fn (string $token) => "\u{E000}{$token}\u{E000}";

    $this->general->members()->create(['slack_user_id' => "UWRITE{$this->s}"]);
    ($this->send)($this->general, $chosen("@UBOB{$this->s}").' please check '.$chosen("#CGENERAL{$this->s}").' '.$chosen('!here'))->assertOk();

    $stored = ($this->messages)($this->general)->first();
    expect(trim(FormatSlackMessages::plainText(FormatSlackMessages::make()->handle([$stored])[$stored->id])))->toBe("@Bob Person please check #{$this->general->name} @here");
});

test('a person who has not connected Slack posts through the Desk bot under their own name and photo, stays the author through the echo, can edit it, cannot react, and is offered to connect, INI-548', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test-'.$this->s);
    SlackUser::where('slack_id', "UREAD{$this->s}")->update(['avatar_url' => 'https://avatars.example/reader.png']);
    ($this->slack)(function (Request $request) {
        $ts = sprintf('%d.%06d', now()->timestamp, 800001);

        return Http::response(['ok' => true, 'channel' => $request['channel'], 'ts' => $ts, 'message' => [
            'type' => 'message', 'subtype' => 'bot_message', 'bot_id' => 'B0AIKU', 'username' => $request['username'], 'text' => $request['text'], 'ts' => $ts,
        ]]);
    });

    $response = ($this->send)($this->general, 'Hello from Desk', as: $this->reader)->assertOk()->assertJsonPath('stand_in', true);

    [$request] = ($this->posts)()[0];
    expect($request->header('Authorization')[0])->toBe('Bearer xoxb-test-'.$this->s)
        ->and($request['username'])->toBe('Reader Person')
        ->and($request['icon_url'])->toBe('https://avatars.example/reader.png');

    $ts      = $response->json('ts');
    $stored  = fn () => SlackMessage::where('slack_channel_id', $this->general->id)->where('ts', $ts)->sole();
    $isOwned = fn (SlackMessage $message) => $message->slack_user_id === "UREAD{$this->s}" && $message->user_id === $this->reader->id && $message->subtype === null && $message->sent_from_aiku;
    expect($isOwned($stored()))->toBeTrue();

    StoreSlackMessage::make()->store($this->general, ['type' => 'message', 'subtype' => 'bot_message', 'bot_id' => 'B0AIKU', 'text' => 'Hello from Desk', 'ts' => $ts]);
    expect($isOwned($stored()))->toBeTrue();

    Http::fake(['slack.com/api/chat.update' => Http::response(['ok' => true, 'ts' => $ts, 'text' => 'Hello again from Desk'])]);
    actingAs($this->reader)->patchJson(route('grp.chat.staff.slack.messages.update', ['slackChannel' => $this->general->id]), ['ts' => $ts, 'text' => 'Hello again from Desk'])->assertOk();
    expect($stored()->text)->toBe('Hello again from Desk')
        ->and($isOwned($stored()))->toBeTrue();

    actingAs($this->reader)->postJson(route('grp.chat.staff.slack.reactions.toggle', ['slackChannel' => $this->general->id]), ['ts' => $ts, 'name' => 'eyes', 'add' => true])
        ->assertUnprocessable()->assertJsonPath('message', 'Connect your Slack account to write here');

    actingAs($this->reader)->get(route('grp.chat.staff.slack.show', ['slackChannel' => $this->general->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.write.stand_in', true)->where('slack_channel.write.allowed', true));
});

test('what the formatting bar writes reaches Slack as its own markup: bold, italic, strike, code, code block, quote, lists and bare links; typed < > & are escaped everywhere else, INI-463', function () {
    ($this->slack)();

    ($this->send)($this->general, "*bold* _it_ ~gone~ `code` a > b & c\n```\nx <b> & y\n```\n> quote\n> more\n• one\n• two\n1. first\nhttps://aiku.io/a?b=1&c=2 and <https://aiku.io/x>\n&gt; typed entity")->assertOk();

    expect(($this->posts)()[0][0]['text'])->toBe("*bold* _it_ ~gone~ `code` a &gt; b &amp; c\n```\nx &lt;b&gt; &amp; y\n```\n> quote\n> more\n• one\n• two\n1. first\nhttps://aiku.io/a?b=1&amp;c=2 and <https://aiku.io/x>\n&amp;gt; typed entity");
});

test('the markup the box sends is rendered by the renderer as formatting: bold, italic, strike, code, code block, quote, bullet and numbered lists and a link, INI-463', function () {
    ($this->slack)(function (Request $request) {
        $ts = sprintf('%d.%06d', now()->timestamp, 900001);

        return Http::response(['ok' => true, 'channel' => $request['channel'], 'ts' => $ts, 'message' => ['type' => 'message', 'user' => "UWRITE{$this->s}", 'text' => preg_replace(['/^>/m', '/(?<!<)(https?:\/\/\S+)/'], ['&gt;', '<$1>'], $request['text']), 'ts' => $ts]]);
    });

    ($this->send)($this->general, "*bold* _it_ ~gone~ `code`\n```\nblock\n```\n> quote\n• one\n• two\n\n1. first\n2. second\n\nhttps://aiku.io/x")->assertOk();

    $stored = ($this->messages)($this->general)->first();
    $types  = [];
    array_walk_recursive(FormatSlackMessages::make()->handle([$stored])[$stored->id], function ($value, $key) use (&$types) {
        if ($key === 'type') {
            $types[] = $value;
        }
    });

    expect($types)->toContain('bold', 'italic', 'strike', 'code', 'pre', 'quote', 'list', 'link');
});

test('editing a message: its mentions come back as chips and are sent as real mentions for live people only, typed codes and typed entities stay plain, INI-461', function () {
    ($this->slack)();
    $chosen = fn (string $token) => "\u{E000}{$token}\u{E000}";
    $ts     = ($this->send)($this->general, $chosen("@UBOB{$this->s}").' hello')->assertOk()->json('ts');
    Http::fake(['slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'channel' => $request['channel'], 'ts' => $request['ts'], 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => "UWRITE{$this->s}"]])]);

    actingAs($this->writer)->patchJson(route('grp.chat.staff.slack.messages.update', ['slackChannel' => $this->general->id]), [
        'ts'   => $ts,
        'format' => 'readable',
        'text' => $chosen("@UBOB{$this->s}").' and '.$chosen("@UREAD{$this->s}").' '.$chosen('@UNKNOWN1')." typed <@UWRITE{$this->s}> <!channel> & more > less",
    ])->assertOk();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'chat.update')
        && $request['text'] === "<@UBOB{$this->s}> and <@UREAD{$this->s}> @UNKNOWN1 typed &lt;@UWRITE{$this->s}&gt; &lt;!channel&gt; &amp; more &gt; less");
});

test('an edit may keep a here or channel the message already had without asking again, but cannot add one in a large channel without confirmation, INI-461', function () {
    ($this->slack)();
    foreach (range(1, SendSlackMessage::EVERYONE_CONFIRM_ABOVE + 1) as $member) {
        $this->general->members()->create(['slack_user_id' => "UMEMBER{$member}{$this->s}"]);
    }
    $ts = ($this->send)($this->general, 'Hello')->assertOk()->json('ts');
    Http::fake(['slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'ts' => $request['ts'], 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => "UWRITE{$this->s}"]])]);
    $edit = fn (string $text) => actingAs($this->writer)->patchJson(route('grp.chat.staff.slack.messages.update', ['slackChannel' => $this->general->id]), ['ts' => $ts, 'text' => $text]);

    $edit("Hello \u{E000}!here\u{E000}")->assertStatus(422)->assertJsonValidationErrors('notify_everyone');
    $edit("Hello \u{E000}!here\u{E000}")->assertStatus(422);
    actingAs($this->writer)->patchJson(route('grp.chat.staff.slack.messages.update', ['slackChannel' => $this->general->id]), ['ts' => $ts, 'text' => "Hello \u{E000}!here\u{E000}", 'notify_everyone' => true])->assertOk();
    $edit("Hello again \u{E000}!here\u{E000}")->assertOk();
    $edit("Hello again \u{E000}!here\u{E000} \u{E000}!channel\u{E000}")->assertStatus(422)->assertJsonValidationErrors('notify_everyone');
});

test('an edit from a box that still sends Slack\'s own entities keeps them, while the readable box escapes every &, INI-461', function () {
    ($this->slack)();
    $ts = ($this->send)($this->general, 'Hello')->assertOk()->json('ts');
    Http::fake(['slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'ts' => $request['ts'], 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => "UWRITE{$this->s}"]])]);
    $edit = fn (array $extra) => actingAs($this->writer)->patchJson(route('grp.chat.staff.slack.messages.update', ['slackChannel' => $this->general->id]), ['ts' => $ts, 'text' => 'a &amp; b &lt;x&gt;', ...$extra])->assertOk();

    $edit([]);
    $edit(['format' => 'readable']);

    $sent = Http::recorded(fn (Request $request) => str_contains($request->url(), 'chat.update'))->map(fn (array $pair) => $pair[0]['text'])->all();
    expect($sent)->toBe(['a &amp; b &lt;x&gt;', 'a &amp;amp; b &amp;lt;x&amp;gt;']);
});


describe('files sent from aiku, INI-465', function () {
    beforeEach(function () {
        Storage::fake(config('media-library.disk_name'));
        $this->fileCalls   = [];
        $this->bytesStatus   = 200;
        $this->completeThrows = false;
        Http::fake([
            'slack.com/api/files.getUploadURLExternal' => function (Request $request) {
                $this->fileCalls[] = 'getUploadURLExternal';
                $id = 'F'.Str::upper(Str::random(8));

                return Http::response(['ok' => true, 'file_id' => $id, 'upload_url' => "https://files.slack.com/upload/v1/$id"]);
            },
            'files.slack.com/upload/*' => function () {
                $this->fileCalls[] = 'bytes';

                return Http::response('OK - 4', $this->bytesStatus);
            },
            'slack.com/api/files.completeUploadExternal' => function () {
                $this->fileCalls[] = 'completeUploadExternal';
                if ($this->completeThrows) {
                    throw new ConnectionException('timeout');
                }

                return Http::response(['ok' => true, 'files' => []]);
            },
        ]);
        $this->upload = fn (UploadedFile $file, ?User $as = null, ?SlackChannel $channel = null) => actingAs($as ?? $this->writer)
            ->post(route('grp.chat.staff.slack.files.upload', ['slackChannel' => ($channel ?? $this->general)->id]), ['file' => $file], ['Accept' => 'application/json']);
        $this->completeFiles = fn (array $ids, array $extra = []) => actingAs($this->writer)
            ->postJson(route('grp.chat.staff.slack.files.send', ['slackChannel' => $this->general->id]), ['file_ids' => $ids, 'idempotency_key' => Str::uuid()->toString(), ...$extra]);
    });

    test('a file goes through Slack\'s three calls in order with the person token, our copy is kept at once and the file_share event downloads nothing', function () {
        $response = ($this->upload)(UploadedFile::fake()->image('pallet.png', 20, 20))->assertOk();
        $fileId   = $response->json('file_id');
        ($this->completeFiles)([$fileId], ['text' => 'See <!channel> & this'])->assertOk()->assertJsonPath('file_ids.0', $fileId);

        expect($this->fileCalls)->toBe(['getUploadURLExternal', 'bytes', 'completeUploadExternal']);
        $calls = Http::recorded(fn (Request $request) => str_contains($request->url(), 'slack.com/api/files.'))->values();
        expect($calls[0][0]->header('Authorization')[0])->toBe('Bearer '.$this->token)
            ->and($calls[0][0]['filename'])->toBe('pallet.png')
            ->and($calls[1][0]->header('Authorization')[0])->toBe('Bearer '.$this->token)
            ->and($calls[1][0]['channel_id'])->toBe($this->general->slack_id)
            ->and($calls[1][0]['initial_comment'])->toBe('See &lt;!channel&gt; &amp; this')
            ->and(json_decode($calls[1][0]['files'], true))->toBe([['id' => $fileId, 'title' => 'pallet.png']])
            ->and($calls[1][0]->data())->not->toHaveKey('thread_ts');

        $copy = SlackFile::where('slack_id', $fileId)->first();
        expect($copy->state->value)->toBe('copied')->and($copy->media_id)->not->toBeNull();

        $message = StoreSlackMessage::make()->handle([
            'type' => 'message', 'subtype' => 'file_share', 'channel' => $this->general->slack_id, 'channel_type' => 'channel',
            'user' => "UWRITE{$this->s}", 'ts' => sprintf('%d.%06d', now()->timestamp, 900001), 'text' => 'See',
            'files' => [['id' => $fileId, 'name' => 'pallet.png', 'mimetype' => 'image/png', 'size' => 100, 'url_private_download' => "https://files.slack.com/files-pri/T1-$fileId/pallet.png"]],
        ]);
        expect($message->sent_from_aiku)->toBeTrue();

        $before = count(Http::recorded());
        CopySlackMessageFile::make()->handle($message, $message->data['files'][0]);
        expect(count(Http::recorded()))->toBe($before)->and(SlackFile::where('slack_id', $fileId)->count())->toBe(1);
    });

    test('a file in a thread carries the thread root, and a reply to a reply goes to the root', function () {
        $root = SlackMessage::create(['slack_channel_id' => $this->general->id, 'ts' => '1700000000.000100', 'text' => 'root', 'sent_at' => now(), 'data' => []]);
        $reply = SlackMessage::create(['slack_channel_id' => $this->general->id, 'ts' => '1700000000.000200', 'thread_ts' => $root->ts, 'text' => 'reply', 'sent_at' => now(), 'data' => []]);
        $fileId = ($this->upload)(UploadedFile::fake()->createWithContent('notes.pdf', "%PDF-1.4\n%test\n"))->assertOk()->json('file_id');

        ($this->completeFiles)([$fileId], ['thread_ts' => $reply->ts])->assertOk();

        $calls = Http::recorded(fn (Request $request) => str_contains($request->url(), 'files.completeUploadExternal'))->values();
        expect($calls[0][0]['thread_ts'])->toBe($root->ts);
    });

    test('a file over the cap is refused before anything is sent to Slack', function () {
        ($this->upload)(UploadedFile::fake()->createWithContent('big.pdf', "%PDF-1.4\n".str_repeat('a', 25 * 1024 * 1024)))->assertUnprocessable();

        expect($this->fileCalls)->toBe([]);
    });

    test('the real content decides the type, not the file name', function () {
        $page = UploadedFile::fake()->createWithContent('photo.png', '<html><body><script>alert(1)</script></body></html>');
        ($this->upload)($page)->assertUnprocessable();

        expect($this->fileCalls)->toBe([]);
    });

    test('one failed file does not lose the others: each file is its own request and only files uploaded earlier can be completed', function () {
        $good = ($this->upload)(UploadedFile::fake()->image('a.png', 10, 10))->assertOk()->json('file_id');
        $this->bytesStatus = 500;
        ($this->upload)(UploadedFile::fake()->image('b.png', 10, 10))->assertUnprocessable();

        ($this->completeFiles)([$good])->assertOk();
        ($this->completeFiles)(['FNEVERUPLOADED'])->assertUnprocessable();
        ($this->completeFiles)([$good, 'FNEVERUPLOADED'])->assertUnprocessable();
    });

    test('a person who has not connected is asked to connect, and a read-only or archived channel refuses', function () {
        $fileId = ($this->upload)(UploadedFile::fake()->image('a.png', 10, 10))->assertOk()->json('file_id');
        SlackConnection::where('user_id', $this->writer->id)->delete();

        ($this->upload)(UploadedFile::fake()->image('b.png', 10, 10))->assertUnprocessable();
        ($this->completeFiles)([$fileId])->assertUnprocessable();

        SlackConnection::create(['user_id' => $this->writer->id, 'slack_user_id' => "UWRITE{$this->s}", 'team_id' => 'T1', 'token' => $this->token, 'scopes' => implode(',', SlackWriteAccess::USER_SCOPES), 'connected_at' => now()]);
        ($this->upload)(UploadedFile::fake()->image('c.png', 10, 10), null, $this->archived)->assertForbidden();
        ($this->upload)(UploadedFile::fake()->image('d.png', 10, 10), null, $this->private)->assertForbidden();
    });

    test('an unreachable Slack on complete keeps the claim and markers so a retry does not double-send, and a definite refusal frees them', function () {
        $fileId = ($this->upload)(UploadedFile::fake()->image('a.png', 10, 10))->assertOk()->json('file_id');
        $key    = Str::uuid()->toString();
        $this->completeThrows = true;

        ($this->completeFiles)([$fileId], ['idempotency_key' => $key])->assertUnprocessable();
        expect(Cache::has(\App\Actions\Chat\Staff\Slack\Write\SendSlackFiles::sentMarkerKey($fileId)))->toBeTrue();
        ($this->completeFiles)([$fileId], ['idempotency_key' => $key])->assertUnprocessable();
        expect(array_count_values($this->fileCalls)['completeUploadExternal'])->toBe(1);
    });

    test('a file uploaded in another channel cannot be completed here', function () {
        $fileId = ($this->upload)(UploadedFile::fake()->image('a.png', 10, 10))->assertOk()->json('file_id');
        $other  = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "COTHER{$this->s}", 'name' => "other-{$this->s}", 'is_member' => true]);

        actingAs($this->writer)->postJson(route('grp.chat.staff.slack.files.send', ['slackChannel' => $other->id]), ['file_ids' => [$fileId], 'idempotency_key' => Str::uuid()->toString()])->assertUnprocessable();
    });

    test('a file sent to a Slack DM from Verba goes with the person token, keeps no copy until Slack\'s file_share event, which stores the message once and queues the private copy with the author\'s token, INI-593', function () {
        Config::set('services.slack.team_id', 'T1');
        \Illuminate\Support\Facades\Queue::fake();
        $dm = slackDirectForSend($this, 'im', ["UWRITE{$this->s}", "UREAD{$this->s}"]);
        optInPrivately($this);

        $fileId = ($this->upload)(UploadedFile::fake()->image('payslip.png', 20, 20), null, $dm)->assertOk()->json('file_id');
        expect(SlackFile::where('slack_id', $fileId)->exists())->toBeFalse();

        actingAs($this->writer)->postJson(route('grp.chat.staff.slack.files.send', ['slackChannel' => $dm->id]), ['file_ids' => [$fileId], 'idempotency_key' => Str::uuid()->toString()])->assertOk();
        $complete = Http::recorded(fn (Request $request) => str_contains($request->url(), 'files.completeUploadExternal'))->last()[0];
        expect($complete->header('Authorization')[0])->toBe('Bearer '.$this->token)
            ->and($complete['channel_id'])->toBe($dm->slack_id);

        $ts    = sprintf('%d.000321', now()->timestamp);
        $share = ['type' => 'message', 'subtype' => 'file_share', 'channel' => $dm->slack_id, 'channel_type' => 'im', 'user' => "UWRITE{$this->s}", 'bot_id' => 'B0AIKU', 'text' => '', 'ts' => $ts, 'files' => [['id' => $fileId, 'name' => 'payslip.png', 'mimetype' => 'image/png']]];
        \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->handle($share, ['team_id' => 'T1', 'user_id' => "UWRITE{$this->s}", 'is_bot' => false]);
        \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->handle($share, ['team_id' => 'T1', 'user_id' => "UWRITE{$this->s}", 'is_bot' => false]);
        $rows = ($this->messages)($dm);
        expect($rows)->toHaveCount(1)
            ->and($rows[0]->sent_from_aiku)->toBeTrue()
            ->and(SlackFile::where('slack_id', $fileId)->where('is_private', false)->exists())->toBeFalse();
        CopySlackMessageFile::assertPushed(2);
        expect(CopySlackMessageFile::privateReadingToken($rows[0]))->toBe($this->token);

        ($this->upload)(UploadedFile::fake()->image('not-mine.png', 20, 20), $this->bob, $dm)->assertForbidden();
    });

    describe('files over 25 MB, straight to R2 then relayed to Slack by a job, INI-491', function () {
        beforeEach(function () {
            $this->r2Calls = [];
            $this->r2Size  = 120 * 1024 * 1024;
            Config::set('filesystems.disks.staff-chat-r2', [
                'driver' => 's3', 'key' => 'key', 'secret' => 'secret', 'region' => 'auto', 'endpoint' => 'https://account.r2.cloudflarestorage.com',
                'bucket' => 'aiku-staff-chat', 'use_path_style_endpoint' => true, 'throw' => true,
                'handler' => function (\Aws\CommandInterface $command) {
                    $this->r2Calls[] = $command->getName();

                    return \GuzzleHttp\Promise\Create::promiseFor(new \Aws\Result(match ($command->getName()) {
                        'CreateMultipartUpload' => ['UploadId' => 'UP1'],
                        'HeadObject'            => ['ContentLength' => $this->r2Size],
                        default                 => [],
                    }));
                },
            ]);
            Storage::forgetDisk('staff-chat-r2');
            $this->startLarge = fn (array $file, ?User $as = null, ?SlackChannel $channel = null) => actingAs($as ?? $this->writer)
                ->postJson(route('grp.chat.staff.slack.large-files.start', ['slackChannel' => ($channel ?? $this->general)->id]), $file);
        });

        test('aiku signs one short-lived R2 link per 50 MB part on a key it chooses, and refuses what it could never send before touching R2', function () {
            $started = ($this->startLarge)(['name' => '../training.mp4', 'size' => $this->r2Size, 'mime_type' => 'video/mp4'])->assertOk();

            expect($started->json('urls'))->toHaveCount(3)
                ->and($started->json('urls.2'))->toContain('account.r2.cloudflarestorage.com/aiku-staff-chat/slack-uploads/'.$started->json('upload'))
                ->toContain('partNumber=3')->toContain('uploadId=UP1')->toContain('X-Amz-Expires=7200')
                ->and(Cache::get(UploadSlackLargeFile::stateKey($started->json('upload')))['name'])->toBe('training.mp4')
                ->and($this->r2Calls)->toBe(['CreateMultipartUpload']);

            ($this->startLarge)(['name' => 'huge.mp4', 'size' => 1024 ** 3 + 1, 'mime_type' => 'video/mp4'])->assertUnprocessable();
            ($this->startLarge)(['name' => 'page.html', 'size' => $this->r2Size, 'mime_type' => 'text/html'])->assertUnprocessable();
            ($this->startLarge)(['name' => 'archive.7z', 'size' => $this->r2Size, 'mime_type' => 'application/octet-stream'])->assertOk();
            ($this->startLarge)(['name' => 'a.mp4', 'size' => $this->r2Size, 'mime_type' => 'video/mp4'], null, $this->private)->assertForbidden();
            ($this->startLarge)(['name' => 'a.mp4', 'size' => $this->r2Size, 'mime_type' => 'video/mp4'], $this->bob)->assertUnprocessable();
            Config::set('filesystems.disks.staff-chat-r2.bucket', null);
            ($this->startLarge)(['name' => 'a.mp4', 'size' => $this->r2Size, 'mime_type' => 'video/mp4'])->assertUnprocessable();
            expect($this->r2Calls)->toBe(['CreateMultipartUpload', 'CreateMultipartUpload']);
        });

        test('completing checks the real size in R2: a short file is deleted and refused, a whole one is queued for Slack; only its uploader in that channel sees its state', function () {
            \Illuminate\Support\Facades\Queue::fake();
            $upload   = ($this->startLarge)(['name' => 'training.mp4', 'size' => $this->r2Size, 'mime_type' => 'video/mp4'])->json('upload');
            $parts    = [['part' => 1, 'etag' => '"a"'], ['part' => 2, 'etag' => '"b"'], ['part' => 3, 'etag' => '"c"']];
            $complete = fn (array $parts, ?User $as = null) => actingAs($as ?? $this->writer)
                ->postJson(route('grp.chat.staff.slack.large-files.complete', ['slackChannel' => $this->general->id, 'upload' => $upload]), ['parts' => $parts]);

            $complete(array_slice($parts, 0, 2))->assertUnprocessable();
            $complete($parts, $this->reader)->assertNotFound();

            $this->r2Size = 1024;
            $complete($parts)->assertUnprocessable();
            expect($this->r2Calls)->toContain('AbortMultipartUpload')->toContain('DeleteObject');
            UploadSlackLargeFile::assertNotPushed();

            $upload       = ($this->startLarge)(['name' => 'training.mp4', 'size' => 120 * 1024 * 1024, 'mime_type' => 'video/mp4'])->json('upload');
            $this->r2Size = 120 * 1024 * 1024;
            actingAs($this->writer)->postJson(route('grp.chat.staff.slack.large-files.complete', ['slackChannel' => $this->general->id, 'upload' => $upload]), ['parts' => $parts])
                ->assertOk()->assertJsonPath('state', 'relaying');
            UploadSlackLargeFile::assertPushed(1);
            actingAs($this->writer)->getJson(route('grp.chat.staff.slack.large-files.show', ['slackChannel' => $this->private->id, 'upload' => $upload]))->assertNotFound();
            actingAs($this->writer)->getJson(route('grp.chat.staff.slack.large-files.show', ['slackChannel' => $this->general->id, 'upload' => $upload]))->assertJsonPath('state', 'relaying');
        });

        test('the job checks the real type from R2, relays the bytes to Slack as the person, keeps the R2 object as our copy and leaves the file ready for SendSlackFiles; a disguised page is deleted unsent', function () {
            Storage::fake('staff-chat-r2');
            $relay = function (string $name, string $bytes) {
                $upload = strtolower((string) Str::ulid());
                Storage::disk('staff-chat-r2')->put("slack-uploads/$upload", $bytes);
                Cache::put(UploadSlackLargeFile::stateKey($upload), [
                    'user_id' => $this->writer->id, 'channel_id' => $this->general->id, 'upload_id' => 'UP1', 'path' => "slack-uploads/$upload",
                    'name' => $name, 'size' => strlen($bytes), 'parts' => 1, 'state' => 'relaying', 'file_id' => null, 'error' => null,
                ]);
                setPermissionsTeamId(null);
                app()->forgetInstance('group');
                UploadSlackLargeFile::make()->asJob($upload);

                return [$upload, Cache::get(UploadSlackLargeFile::stateKey($upload))];
            };

            [$page, $refused] = $relay('photo.png', '<html><body><script>alert(1)</script></body></html>');
            expect($refused['state'])->toBe('failed')->and($refused['error'])->not->toBeNull()
                ->and(Storage::disk('staff-chat-r2')->exists("slack-uploads/$page"))->toBeFalse()
                ->and($this->fileCalls)->toBe([]);

            [$upload, $done] = $relay('notes.pdf', "%PDF-1.4\n".str_repeat('a', 2048));
            expect($done['state'])->toBe('done')->and($this->fileCalls)->toBe(['getUploadURLExternal', 'bytes']);
            $bytes = Http::recorded(fn (Request $request) => str_contains($request->url(), 'files.slack.com/upload'))->last()[0];
            expect($bytes->header('Content-Length')[0])->toBe('2057')->and($bytes->header('Content-Type')[0])->toBe('application/pdf');
            $copy = SlackFile::where('slack_id', $done['file_id'])->where('is_private', false)->first();
            expect($copy->state->value)->toBe('copied')->and($copy->media_id)->not->toBeNull()->and($copy->disk)->toBeNull()
                ->and(Storage::disk('staff-chat-r2')->exists("slack-uploads/$upload"))->toBeFalse();

            setPermissionsTeamId($this->group->id);
            app()->instance('group', $this->group);
            ($this->completeFiles)([$done['file_id']])->assertOk();
            expect($this->fileCalls)->toBe(['getUploadURLExternal', 'bytes', 'completeUploadExternal']);

            Config::set('media-library.max_file_size', 1024);
            [$video, $kept] = $relay('notes-long.pdf', "%PDF-1.4\n".str_repeat('b', 4096));
            $copy = SlackFile::where('slack_id', $kept['file_id'])->where('is_private', false)->first();
            expect($kept['state'])->toBe('done')->and($copy->media_id)->toBeNull()->and($copy->disk)->toBe('staff-chat-r2')->and($copy->path)->toBe("slack-files/{$kept['file_id']}/notes-long.pdf")
                ->and(Storage::disk('staff-chat-r2')->exists($copy->path))->toBeTrue()
                ->and(Storage::disk('staff-chat-r2')->exists("slack-uploads/$video"))->toBeFalse();
        });

        test('a job killed mid-relay leaves no file stuck on Sending to Slack and deletes the object', function () {
            Storage::fake('staff-chat-r2');
            $upload = strtolower((string) Str::ulid());
            Storage::disk('staff-chat-r2')->put("slack-uploads/$upload", 'bytes');
            Cache::put(UploadSlackLargeFile::stateKey($upload), ['user_id' => $this->writer->id, 'channel_id' => $this->general->id, 'upload_id' => 'UP1', 'path' => "slack-uploads/$upload", 'name' => 'a.mp4', 'size' => 5, 'parts' => 1, 'state' => 'relaying', 'file_id' => null, 'error' => null]);

            UploadSlackLargeFile::make()->jobFailed(new RuntimeException('timeout'), $upload);

            expect(Cache::get(UploadSlackLargeFile::stateKey($upload))['state'])->toBe('failed')
                ->and(Storage::disk('staff-chat-r2')->exists("slack-uploads/$upload"))->toBeFalse();
        });
    });
});

test('Slack second workspace: a message in an aw-advantage channel goes through that workspace\'s bot under the person\'s own account there, never their main token; reactions there are refused, INI-540', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-main');
    Config::set('services.slack.other_workspaces', [['team_id' => 'TAWA', 'bot_token' => 'xoxb-awa', 'signing_secret' => 'awa-secret', 'name_suffix' => 'awa']]);
    $awaChannel = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CAWA{$this->s}", 'name' => "awa-{$this->s}", 'is_member' => true, 'slack_team_id' => 'TAWA']);

    ($this->send)($awaChannel, 'Hello aw team')->assertOk();
    $post = ($this->posts)()->last()[0];
    expect($post->header('Authorization')[0])->toBe('Bearer xoxb-awa')
        ->and($post['username'])->toBe('Writer Person')
        ->and(($this->messages)($awaChannel)->last()->slack_user_id)->toBe("UWRITE{$this->s}");

    SlackUser::create(['slack_id' => "UWAWA{$this->s}", 'user_id' => $this->writer->id, 'email' => $this->writer->email, 'real_name' => 'Writer at AW', 'data' => ['team_id' => 'TAWA']]);
    ($this->send)($awaChannel, 'Hello again')->assertOk();
    expect(($this->posts)()->last()[0]['username'])->toBe('Writer at AW')
        ->and(($this->messages)($awaChannel)->last()->slack_user_id)->toBe("UWAWA{$this->s}");

    ($this->send)($this->general, 'Main as before')->assertOk();
    expect(($this->posts)()->last()[0]->header('Authorization')[0])->toBe('Bearer '.$this->token);

    $lastTs = ($this->messages)($awaChannel)->last()->ts;
    actingAs($this->writer)->postJson(route('grp.chat.staff.slack.reactions.toggle', ['slackChannel' => $awaChannel->id]), ['ts' => $lastTs, 'name' => 'eyes', 'add' => true])
        ->assertUnprocessable()->assertJsonPath('message', 'Connect your Slack account to write here');

    SlackConnection::create(['user_id' => $this->writer->id, 'slack_user_id' => "UWAWA{$this->s}", 'team_id' => 'TAWA', 'token' => 'xoxp-awa-'.$this->s, 'scopes' => implode(',', SlackWriteAccess::USER_SCOPES), 'connected_at' => now()]);
    Http::fake(['slack.com/api/reactions.add' => Http::response(['ok' => true])]);
    actingAs($this->writer)->postJson(route('grp.chat.staff.slack.reactions.toggle', ['slackChannel' => $awaChannel->id]), ['ts' => $lastTs, 'name' => 'eyes', 'add' => true])->assertOk();
    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), 'reactions.add'))->last()[0]->header('Authorization')[0])->toBe('Bearer xoxp-awa-'.$this->s);

    ($this->send)($this->general, 'Main still uses the main token')->assertOk();
    expect(($this->posts)()->last()[0]->header('Authorization')[0])->toBe('Bearer '.$this->token);
});

function slackDirectForSend(object $test, string $type, array $slackIds): SlackChannel
{
    $channel = SlackChannel::create(['group_id' => $test->group->id, 'slack_id' => strtoupper($type).'D'.Str::upper(Str::random(8)), 'direct_type' => $type, 'is_private' => true, 'is_public' => false, 'is_member' => false]);
    \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->addMembers($channel, $slackIds);

    return $channel;
}

function optInPrivately(object $test): SlackConnection
{
    $connection = SlackConnection::where('user_id', $test->writer->id)->sole();
    $connection->forceFill(['scopes' => implode(',', [...SlackWriteAccess::USER_SCOPES, ...SlackWriteAccess::PRIVATE_SCOPES])])->save();
    $connection->grantPrivateConsent(SlackWriteAccess::PRIVATE_CONSENT_VERSION, SlackWriteAccess::PRIVATE_TERMS_VERSION);

    return $connection;
}

test('an opted-in member replies, edits and deletes in a Slack DM from Verba with their own token, stored once and never broadcast; the echo adds nothing; non-members and other writes are refused, INI-593', function () {
    Event::fake([SlackChannelMessageChanged::class, BroadcastPersonalNotification::class, \App\Events\SlackDirectMessageChanged::class]);
    Config::set('services.slack.team_id', 'T1');
    $dm = slackDirectForSend($this, 'im', ["UWRITE{$this->s}", "UREAD{$this->s}"]);
    optInPrivately($this);

    actingAs($this->writer)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.write.allowed', true)->where('slack_channel.write.reason', null)->where('slack_channel.write.direct', true)->where('slack_channel.write.stand_in', false));

    ($this->send)($dm, 'Lunch at one?')->assertOk();
    $rows = ($this->messages)($dm);
    $post = ($this->posts)()->last()[0];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->only(['slack_user_id', 'user_id', 'text', 'sent_from_aiku']))->toBe(['slack_user_id' => "UWRITE{$this->s}", 'user_id' => $this->writer->id, 'text' => 'Lunch at one?', 'sent_from_aiku' => true])
        ->and($post->header('Authorization')[0])->toBe('Bearer '.$this->token)
        ->and($post['channel'])->toBe($dm->slack_id)
        ->and($post['username'] ?? null)->toBeNull()
        ->and(($this->kinds)())->toBe([]);
    $announced = fn () => Event::dispatched(\App\Events\SlackDirectMessageChanged::class)->map(fn (array $args) => $args[0]->kind)->values()->all();
    $event     = Event::dispatched(\App\Events\SlackDirectMessageChanged::class)->first()[0];
    expect($announced())->toBe(['new'])
        ->and($event->broadcastWith()['message'])->toBeNull()
        ->and($event->broadcastWith()['user_id'])->toBe($this->writer->id)
        ->and(collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->sort()->values()->all())->toBe(collect(['private-grp.slack-direct.'.$this->writer->id, 'private-grp.slack-direct.'.$this->reader->id])->sort()->values()->all());

    $ts       = $rows[0]->ts;
    $fromApp  = ['type' => 'message', 'channel' => $dm->slack_id, 'channel_type' => 'im', 'user' => "UWRITE{$this->s}", 'bot_id' => 'B0AIKU', 'app_id' => 'A0AIKU', 'text' => 'Lunch at one?', 'ts' => $ts];
    $byWriter = ['team_id' => 'T1', 'user_id' => "UWRITE{$this->s}", 'is_bot' => false];
    \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->handle($fromApp, $byWriter);
    expect(($this->messages)($dm))->toHaveCount(1)
        ->and(($this->messages)($dm)[0]->sent_from_aiku)->toBeTrue()
        ->and($announced())->toBe(['new', 'changed']);

    \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $dm->slack_id, 'channel_type' => 'im', 'message' => [...$fromApp, 'text' => 'Lunch at half one?', 'edited' => ['ts' => sprintf('%d.000005', now()->timestamp)]]], $byWriter);
    expect(($this->messages)($dm)[0]->text)->toBe('Lunch at half one?');
    \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->handle([...$fromApp, 'ts' => sprintf('%d.000077', now()->timestamp), 'subtype' => 'bot_message', 'text' => 'an app speaking'], $byWriter);
    \App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage::make()->handle([...$fromApp, 'ts' => sprintf('%d.000078', now()->timestamp), 'user' => "UNOBODY{$this->s}", 'text' => 'an unknown app user'], $byWriter);
    expect(($this->messages)($dm))->toHaveCount(1);

    Http::fake([
        'slack.com/api/chat.update' => Http::response(['ok' => true, 'channel' => $dm->slack_id, 'ts' => $ts, 'text' => 'Lunch at two?', 'message' => ['text' => 'Lunch at two?', 'edited' => ['user' => "UWRITE{$this->s}", 'ts' => sprintf('%d.000009', now()->timestamp)]]]),
        'slack.com/api/chat.delete' => Http::response(['ok' => true, 'channel' => $dm->slack_id, 'ts' => $ts]),
    ]);
    actingAs($this->writer)->patchJson(route('grp.chat.staff.slack.messages.update', ['slackChannel' => $dm->id]), ['ts' => $ts, 'text' => 'Lunch at two?', 'format' => 'readable'])->assertOk();
    $edited = SlackMessage::where('slack_channel_id', $dm->id)->where('ts', $ts)->sole();
    expect($edited->text)->toBe('Lunch at two?')
        ->and($edited->versions()->count())->toBe(2);

    Http::fake(['slack.com/api/reactions.add' => Http::response(['ok' => true])]);
    actingAs($this->writer)->postJson(route('grp.chat.staff.slack.reactions.toggle', ['slackChannel' => $dm->id]), ['ts' => $ts, 'name' => 'eyes', 'add' => true])->assertOk()
        ->assertJsonPath('message.my_reactions', ['eyes']);
    $reacted = Http::recorded(fn (Request $request) => str_contains($request->url(), 'reactions.add'))->last()[0];
    expect($reacted->header('Authorization')[0])->toBe('Bearer '.$this->token)
        ->and($reacted['channel'])->toBe($dm->slack_id)
        ->and($edited->reactions()->pluck('name', 'slack_user_id')->all())->toBe(["UWRITE{$this->s}" => 'eyes'])
        ->and(Arr::last($announced()))->toBe('changed');

    Config::set('services.slack.signing_secret', 'shh');
    $webhook = function (array $event, string $deliveredBy) {
        $body      = json_encode(['type' => 'event_callback', 'event' => $event, 'authorizations' => [['team_id' => 'T1', 'user_id' => $deliveredBy, 'is_bot' => false]]]);
        $timestamp = (string) time();

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], ['HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp, 'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'), 'CONTENT_TYPE' => 'application/json'], $body);
    };
    $readerReacts = ['type' => 'reaction_added', 'user' => "UREAD{$this->s}", 'reaction' => 'tada', 'item' => ['type' => 'message', 'channel' => $dm->slack_id, 'ts' => $ts], 'event_ts' => sprintf('%d.000010', now()->timestamp)];
    $webhook($readerReacts, "UBOB{$this->s}")->assertOk();
    expect($edited->reactions()->where('name', 'tada')->exists())->toBeFalse();
    $webhook($readerReacts, "UWRITE{$this->s}")->assertOk();
    expect($edited->reactions()->where('name', 'tada')->exists())->toBeTrue();

    actingAs($this->writer)->postJson(route('grp.chat.staff.slack.pin.toggle', ['slackChannel' => $dm->id]), ['ts' => $ts, 'pinned' => true])->assertForbidden();

    actingAs($this->writer)->deleteJson(route('grp.chat.staff.slack.messages.delete', ['slackChannel' => $dm->id]), ['ts' => $ts])->assertOk();
    expect(SlackMessage::withTrashed()->where('slack_channel_id', $dm->id)->where('ts', $ts)->sole()->trashed())->toBeTrue()
        ->and(array_slice($announced(), -2))->toBe(['changed', 'deleted']);

    \App\Actions\Chat\Staff\Slack\PurgeSlackDirectWords::run("UWRITE{$this->s}");
    $purged = SlackMessage::withTrashed()->where('slack_channel_id', $dm->id)->where('ts', $ts)->sole();
    expect($purged->text)->toBeNull()
        ->and($purged->data)->toBeNull()
        ->and($purged->versions()->count())->toBe(0);

    $sentBefore = ($this->posts)()->count();
    ($this->send)($dm, 'Not my chat', as: $this->bob)->assertForbidden();
    actingAs($this->bob)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertForbidden();
    expect(($this->posts)()->count())->toBe($sentBefore)
        ->and(($this->messages)($dm))->toHaveCount(1);
});

test('without the private opt-in, or with a Slack connection that needs renewing, a Slack DM says why under the box and nothing reaches Slack, INI-593', function () {
    Config::set('services.slack.team_id', 'T1');
    $dm = slackDirectForSend($this, 'mpim', ["UWRITE{$this->s}", "UREAD{$this->s}", "UBOB{$this->s}"]);

    actingAs($this->writer)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.write.allowed', true)->where('slack_channel.write.reason', 'Turn on private conversations in your Slack settings to reply here')->where('slack_channel.write.direct', true));
    ($this->send)($dm, 'Hello all')->assertUnprocessable()->assertJsonPath('errors.slack.0', 'Turn on private conversations in your Slack settings to reply here');

    optInPrivately($this)->forceFill(['needs_reconnect' => true])->save();
    actingAs($this->writer)->get(route('grp.chat.staff.direct.slack.show', $dm))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.write.reason', 'Reconnect your Slack account to reply here'));
    ($this->send)($dm, 'Hello all')->assertUnprocessable()->assertJsonPath('errors.slack.0', 'Reconnect your Slack account to reply here');
    SlackConnection::where('user_id', $this->writer->id)->sole()->forceFill(['needs_reconnect' => false, 'revoked_at' => now()])->save();
    ($this->send)($dm, 'Hello all')->assertUnprocessable()->assertJsonPath('errors.slack.0', 'Reconnect your Slack account to reply here');

    expect(($this->posts)())->toHaveCount(0)
        ->and(($this->messages)($dm))->toHaveCount(0);

    SlackConnection::where('user_id', $this->writer->id)->sole()->forceFill(['revoked_at' => null])->save();
    $closing = slackDirectForSend($this, 'im', ["UWRITE{$this->s}", "UREAD{$this->s}"]);
    ($this->slack)(['ok' => false, 'error' => 'is_archived']);
    ($this->send)($closing, 'Are you there?')->assertUnprocessable()->assertJsonPath('errors.slack.0', 'This conversation is closed in Slack, the other person is no longer there.');
    expect($closing->refresh()->is_archived)->toBeTrue();
    actingAs($this->writer)->get(route('grp.chat.staff.direct.slack.show', $closing))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('slack_channel.write.reason', 'This conversation is closed in Slack, the other person is no longer there.')->where('slack_channel.write.connect_url', null));
});

test('starting a chat in Verba opens the Slack DM or group DM with those people, or opens one in Slack, whenever the starter is opted in, even over an older Verba DM, which then points to it; a call, a named chat, someone without a Slack person, a starter not opted in or Slack failing get a Verba chat, INI-593', function () {
    Config::set('services.slack.team_id', 'T1');
    $dm      = slackDirectForSend($this, 'im', ["UWRITE{$this->s}", "UREAD{$this->s}"]);
    $start   = fn (array $with, bool $preferSlack = true, array $extra = []) => actingAs($this->writer)->postJson(route('grp.chat.staff.conversations.store'), ['user_ids' => array_map(fn (User $user) => $user->id, $with), 'prefer_slack' => $preferSlack, ...$extra]);
    $verbaDm = fn (User $with) => \App\Models\Chat\StaffConversation::where('dm_key', \App\Models\Chat\StaffConversation::dmKey([$this->writer->id, $with->id]))->first();
    $opened   = null;
    $openFails = false;
    Http::fake(['slack.com/api/conversations.open' => function (Request $request) use (&$opened, &$openFails) {
        $opened = $request['users'];
        if ($openFails) {
            return Http::response(['ok' => false, 'error' => 'ratelimited']);
        }

        return Http::response(str_contains($request['users'], ',') ? ['ok' => true, 'channel' => ['id' => "GMP{$this->s}"]] : ['ok' => true, 'channel' => ['id' => "DBOB{$this->s}"]]);
    }]);
    optInPrivately($this);

    $start([$this->reader], preferSlack: false)->assertSuccessful()->assertJsonMissingPath('slack_direct');
    $older = $verbaDm($this->reader);
    expect($older)->not->toBeNull();

    $start([$this->reader])->assertOk()->assertJsonPath('slack_direct.id', $dm->id)->assertJsonPath('slack_direct.url', route('grp.chat.staff.direct.slack.show', $dm->id));
    expect($opened)->toBeNull();
    $pointer = function (User $viewer) use ($older): ?string {
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $viewer);

        return (new \App\Http\Resources\Chat\StaffConversationResource($older->fresh('participants')))->toArray($request)['slack_direct_url'];
    };
    expect($pointer($this->writer))->toBe(route('grp.chat.staff.direct.slack.show', $dm->id))
        ->and($pointer($this->reader))->toBe(route('grp.chat.staff.direct.slack.show', $dm->id))
        ->and($pointer($this->bob))->toBeNull();
    $start([$this->reader], preferSlack: false)->assertSuccessful()->assertJsonMissingPath('slack_direct');
    $start([$this->reader], extra: ['name' => 'Planning'])->assertSuccessful()->assertJsonMissingPath('slack_direct');

    $response = $start([$this->bob])->assertOk();
    $bobDm    = SlackChannel::where('slack_id', "DBOB{$this->s}")->sole();
    expect($opened)->toBe("UBOB{$this->s}")
        ->and($response->json('slack_direct.id'))->toBe($bobDm->id)
        ->and($bobDm->direct_type)->toBe('im')
        ->and($bobDm->members()->pluck('slack_user_id')->sort()->values()->all())->toBe(collect(["UBOB{$this->s}", "UWRITE{$this->s}"])->sort()->values()->all());

    $group = $start([$this->reader, $this->bob])->assertOk();
    $mpim  = SlackChannel::where('slack_id', "GMP{$this->s}")->sole();
    expect(collect(explode(',', $opened))->sort()->values()->all())->toBe(collect(["UREAD{$this->s}", "UBOB{$this->s}"])->sort()->values()->all())
        ->and($group->json('slack_direct.id'))->toBe($mpim->id)
        ->and($mpim->direct_type)->toBe('mpim')
        ->and($mpim->members()->count())->toBe(3);

    $start([$this->user])->assertSuccessful()->assertJsonMissingPath('slack_direct');
    $openFails = true;
    $start([$this->reader, $this->bob])->assertSuccessful()->assertJsonMissingPath('slack_direct');

    SlackConnection::where('user_id', $this->writer->id)->sole()->endPrivateConsent('withdrawn');
    $start([$this->reader])->assertSuccessful()->assertJsonMissingPath('slack_direct');
});

describe('Verba through MCP, INI-596', function () {
    beforeEach(function () {
        $this->verbaDm = StaffConversation::create(['group_id' => $this->group->id, 'type' => 'dm', 'dm_key' => "verba-{$this->s}", 'last_message_at' => now()]);
        $this->verbaDm->addParticipants([$this->writer->id, $this->reader->id]);
        $this->others = StaffConversation::create(['group_id' => $this->group->id, 'type' => 'group', 'name' => "Others {$this->s}", 'last_message_at' => now()]);
        $this->others->addParticipants([$this->reader->id, $this->bob->id]);
        $this->others->messages()->create(['user_id' => $this->bob->id, 'body' => "secret-{$this->s}"]);
        $this->keptPrivate = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "GPRIV{$this->s}", 'name' => "hush-{$this->s}", 'is_public' => false, 'is_private' => true, 'is_member' => true]);
        $this->keptPrivate->members()->create(['slack_user_id' => "UWRITE{$this->s}"]);
        $this->bobDm = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "DBOBREAD{$this->s}", 'direct_type' => 'im', 'is_private' => true, 'is_public' => false, 'is_member' => false]);
        $this->bobDm->members()->createMany([['slack_user_id' => "UBOB{$this->s}"], ['slack_user_id' => "UREAD{$this->s}"]]);
        $this->tool = fn (string $tool, array $arguments = [], ?User $as = null) => AikuServer::actingAs($as ?? $this->writer)->tool($tool, $arguments);
        $this->confirmToken = function (array $arguments): string {
            actingAs($this->writer);

            return json_decode((string) app(VerbaSendTool::class)->handle(new Laravel\Mcp\Request($arguments))->content(), true)['confirm_token'];
        };
    });

    test('lists and reads only what the user may read in Verba, the same answer for others\' and unknown ids', function () {
        ($this->tool)(VerbaTool::class)->assertOk()
            ->assertSee([$this->verbaDm->ulid, '"slack-'.$this->general->id.'"', '"slack-'.$this->keptPrivate->id.'"'])
            ->assertDontSee([$this->others->ulid, $this->others->name, '"slack-'.$this->private->id.'"', '"slack-'.$this->ignored->id.'"', '"slack-'.$this->bobDm->id.'"']);

        foreach ([$this->others->ulid, 'slack-'.$this->private->id, 'slack-'.$this->bobDm->id, 'slack-'.$this->gone->id, 'slack-999999999', 'nonsense'] as $id) {
            ($this->tool)(VerbaTool::class, ['conversation' => $id])->assertHasErrors(['No Verba conversation or V·Desk'])->assertDontSee("secret-{$this->s}");
        }
        ($this->tool)(VerbaTool::class, ['conversation' => 'slack-'.$this->keptPrivate->id])->assertOk()->assertSee('private_v_desk');
        ($this->tool)(VerbaTool::class, ['conversation' => 'slack-'.$this->keptPrivate->id], $this->reader)->assertHasErrors(['No Verba conversation or V·Desk']);
        ($this->tool)(VerbaTool::class, ['conversation' => $this->others->ulid], $this->bob)->assertOk()->assertSee("secret-{$this->s}");
    });

    test('someone who left reads up to the moment they left and cannot write', function () {
        $this->others->addParticipants([$this->writer->id]);
        $this->travelTo(now()->addMinute());
        $this->others->removeParticipants([$this->writer->id]);
        $this->travelTo(now()->addMinute());
        $this->others->messages()->create(['user_id' => $this->bob->id, 'body' => "after-{$this->s}"]);

        ($this->tool)(VerbaTool::class, ['conversation' => $this->others->ulid])->assertOk()->assertSee("secret-{$this->s}")->assertDontSee("after-{$this->s}");
        ($this->tool)(VerbaSendTool::class, ['conversation' => $this->others->ulid, 'text' => 'hello'])->assertHasErrors(['no longer in that conversation']);
        expect($this->others->messages()->where('body', 'hello')->exists())->toBeFalse();
    });

    test('sends to Verba and Slack only after confirm, through the existing actions, once, with no real mentions and nothing in the request log', function () {
        $verba = ['conversation' => $this->verbaDm->ulid, 'text' => 'Pallet <b>ready</b>'];
        ($this->tool)(VerbaSendTool::class, $verba)->assertOk()->assertSee(['Nothing was sent', '"text":"Pallet ready"']);
        expect($this->verbaDm->messages()->count())->toBe(0);

        ($this->tool)(VerbaSendTool::class, [...$verba, 'confirm' => true, 'confirm_token' => 'made-up', 'request_text' => 'yes'])->assertHasErrors(['confirm_token is unknown']);
        $token = ($this->confirmToken)($verba);
        ($this->tool)(VerbaSendTool::class, [...$verba, 'text' => 'Pallet gone', 'confirm' => true, 'confirm_token' => $token, 'request_text' => 'yes'])->assertHasErrors(['for another message']);
        $token = ($this->confirmToken)($verba);
        ($this->tool)(VerbaSendTool::class, [...$verba, 'confirm' => true, 'confirm_token' => $token, 'request_text' => 'tell reader the pallet is ready'])->assertOk();
        ($this->tool)(VerbaSendTool::class, [...$verba, 'confirm' => true, 'confirm_token' => $token, 'request_text' => 'tell reader the pallet is ready'])->assertHasErrors(['used']);
        expect($this->verbaDm->messages()->sole()->only(['user_id', 'body']))->toBe(['user_id' => $this->writer->id, 'body' => 'Pallet ready']);
        ($this->tool)(VerbaTool::class, ['conversation' => $this->verbaDm->ulid], $this->reader)->assertOk()->assertSee('Pallet ready');

        $slack = ['conversation' => 'slack-'.$this->general->id, 'text' => "Hi \u{E000}!channel\u{E000} and \u{E000}@UBOB{$this->s}\u{E000}"];
        ($this->tool)(VerbaSendTool::class, $slack)->assertOk()->assertSee('Slack, as the user');
        expect(($this->posts)())->toHaveCount(0);

        $token = ($this->confirmToken)($slack);
        ($this->tool)(VerbaSendTool::class, [...$slack, 'confirm' => true, 'confirm_token' => $token, 'request_text' => 'post it'])->assertOk();
        ($this->tool)(VerbaSendTool::class, [...$slack, 'confirm' => true, 'confirm_token' => $token, 'request_text' => 'post it'])->assertHasErrors(['used']);
        $posts = ($this->posts)();
        expect($posts)->toHaveCount(1)
            ->and($posts[0][0]['text'])->not->toContain('<!channel>')->not->toContain('<@UBOB')
            ->and($posts[0][0]->header('Authorization')[0])->toBe('Bearer '.$this->token);
        ($this->tool)(VerbaTool::class, ['conversation' => 'slack-'.$this->general->id])->assertOk()->assertSee('!channel');

        ($this->tool)(VerbaSendTool::class, ['conversation' => 'slack-'.$this->keptPrivate->id, 'text' => 'x'])->assertHasErrors(['read only'])->assertDontSee($this->keptPrivate->name);
        ($this->tool)(VerbaSendTool::class, ['conversation' => 'slack-'.$this->bobDm->id, 'text' => 'x'])->assertHasErrors(['No Verba conversation or V·Desk']);
        expect(($this->posts)())->toHaveCount(1);

        $request = Illuminate\Http\Request::create('/mcp/aiku', 'POST', content: json_encode(['method' => 'tools/call', 'params' => ['name' => 'verba_send', 'arguments' => ['conversation' => $this->verbaDm->ulid, 'message' => "words-{$this->s}", 'request_text' => "asked-{$this->s}"]]]));
        $request->setUserResolver(fn () => $this->writer);
        (new App\Http\Middleware\LogMcpRequest())->terminate($request, response('{}'));
        expect(App\Models\SysAdmin\McpRequest::where('user_id', $this->writer->id)->latest('id')->first()->arguments)->toBe(['conversation' => $this->verbaDm->ulid]);
    });

    test('preferences are the user\'s own and star only what they can read', function () {
        ($this->tool)(VerbaPreferencesTool::class, ['pattern' => 'dots', 'colour' => 'sage', 'star' => ['slack-'.$this->general->id, $this->verbaDm->ulid], 'welcome_seen' => true])->assertOk()->assertSee('"after"');
        expect(Arr::get($this->writer->refresh()->settings, 'desk_starred'))->toBeNull();

        ($this->tool)(VerbaPreferencesTool::class, ['star' => [$this->others->ulid], 'confirm' => true, 'request_text' => 'star it'])->assertHasErrors(['this user can read']);
        ($this->tool)(VerbaPreferencesTool::class, ['star' => ['slack-'.$this->bobDm->id], 'confirm' => true, 'request_text' => 'star it'])->assertHasErrors(['this user can read']);
        ($this->tool)(VerbaPreferencesTool::class, ['pattern' => 'dots', 'colour' => 'sage', 'star' => ['slack-'.$this->general->id, $this->verbaDm->ulid], 'welcome_seen' => true, 'confirm' => true, 'request_text' => 'dots and sage'])->assertOk();

        $settings = $this->writer->refresh()->settings;
        expect(Arr::only($settings['chat_wallpaper'], ['pattern', 'colour']))->toEqual(['pattern' => 'dots', 'colour' => 'sage'])
            ->and($settings['desk_starred'])->toBe(['slack-'.$this->general->id, 'dm-'.$this->verbaDm->ulid])
            ->and($settings['verba_welcome_seen'])->toBeTrue()
            ->and(Arr::get($this->reader->refresh()->settings, 'desk_starred'))->toBeNull();

        ($this->tool)(VerbaPreferencesTool::class, ['wallpaper_conversation' => $this->others->ulid, 'pattern' => 'waves', 'confirm' => true, 'request_text' => 'x'])->assertHasErrors(['No Verba conversation or V·Desk']);
    });

    test('Verba settings are for Verba admins, public channels only, and the change is recorded as the admin', function () {
        ($this->tool)(VerbaChannelSettingsTool::class, [], $this->reader)->assertHasErrors(['Verba admins only']);

        $admin = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
        $admin->assignRole(App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(App\Enums\SysAdmin\Authorisation\RolesEnum::VERBA_ADMIN->value, $this->group));

        ($this->tool)(VerbaChannelSettingsTool::class, ['search' => $this->s], $admin)->assertOk()->assertSee($this->general->name)->assertDontSee([$this->keptPrivate->name, $this->private->name]);
        ($this->tool)(VerbaChannelSettingsTool::class, ['channel' => $this->keptPrivate->name, 'hidden' => true], $admin)->assertHasErrors(['No public V·Desk']);
        ($this->tool)(VerbaChannelSettingsTool::class, ['channel' => 'slack-'.$this->bobDm->id, 'hidden' => true], $admin)->assertHasErrors(['No public V·Desk']);

        $change = ['channel' => '#'.$this->general->name, 'hidden' => true, 'shows_to' => [$this->organisation->code]];
        ($this->tool)(VerbaChannelSettingsTool::class, $change, $admin)->assertOk()->assertSee('"before"');
        expect($this->general->refresh()->is_hidden_in_lists)->toBeFalse();

        ($this->tool)(VerbaChannelSettingsTool::class, [...$change, 'confirm' => true, 'request_text' => 'only for us, hidden'], $admin)->assertOk();
        expect($this->general->refresh()->is_hidden_in_lists)->toBeTrue()
            ->and($this->general->organisations()->pluck('organisations.id')->all())->toBe([$this->organisation->id])
            ->and(App\Models\Helpers\Audit::where('auditable_type', 'SlackChannel')->where('auditable_id', $this->general->id)->pluck('user_id')->unique()->all())->toBe([$admin->id]);
    });
});
