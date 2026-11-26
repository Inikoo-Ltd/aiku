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
use App\Events\BroadcastPersonalNotification;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
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
});
