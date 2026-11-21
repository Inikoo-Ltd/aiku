<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Events\BroadcastPersonalNotification;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Notifications\SlackMentionNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Event::fake([BroadcastPersonalNotification::class]);
    $this->group = createGroup();
    $this->s     = Str::upper(Str::random(6));

    $this->person = function (string $key, ?array $channels = ['browser']) {
        $user = User::factory()->create([
            'group_id'    => $this->group->id,
            'language_id' => User::where('group_id', $this->group->id)->value('language_id') ?? 1,
            'nickname'    => ucfirst(strtolower($key)).$this->s,
            'settings'    => $channels === null ? [] : ['notifications' => ['slack_mention' => $channels]],
        ]);
        SlackUser::create(['slack_id' => "U$key{$this->s}", 'user_id' => $user->id, 'email' => $user->email, 'real_name' => ucfirst(strtolower($key)).' Slack']);

        return $user;
    };
    $this->ann  = ($this->person)('ANN');
    $this->bob  = ($this->person)('BOB');
    $this->cat  = ($this->person)('CAT');
    $this->ann->refresh();

    $channel        = fn (string $key, array $attributes = []) => SlackChannel::create(array_merge(['group_id' => $this->group->id, 'slack_id' => "C$key{$this->s}", 'name' => strtolower($key)."-{$this->s}", 'is_member' => true], $attributes));
    $this->general  = $channel('GENERAL');
    $this->ignored  = $channel('IGNORED', ['is_ignored' => true]);
    $this->private  = $channel('PRIVATE', ['is_public' => false]);

    $this->ts    = fn (int $n) => sprintf('%d.%06d', now()->timestamp, $n);
    $this->event = fn (SlackChannel $channel, string $ts, string $text, array $extra = []) => StoreSlackMessage::make()->handle(array_merge([
        'type'         => 'message',
        'channel'      => $channel->slack_id,
        'channel_type' => 'channel',
        'user'         => "UANN{$this->s}",
        'text'         => $text,
        'ts'           => $ts,
    ], $extra));
    $this->edit = fn (SlackChannel $channel, string $ts, string $text, int $seconds) => StoreSlackMessage::make()->handle([
        'type'         => 'message',
        'subtype'      => 'message_changed',
        'channel'      => $channel->slack_id,
        'channel_type' => 'channel',
        'message'      => ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => $text, 'ts' => $ts, 'edited' => ['user' => "UANN{$this->s}", 'ts' => sprintf('%d.000000', now()->timestamp + $seconds)]],
    ]);
    $this->mentions = fn (User $user) => $user->notifications()->where('type', SlackMentionNotification::class)->get();
});

test('a live Slack mention rings the bell of the linked aiku user with the author, a plain excerpt and a link to the message, INI-445', function () {
    $message = ($this->event)($this->general, ($this->ts)(1), "Hi <@UBOB{$this->s}>, the *blue* pallets are in <#{$this->general->slack_id}>");

    $notifications = ($this->mentions)($this->bob);
    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]->data['title'])->toBe("Ann{$this->s} mentioned you in #general-{$this->s}")
        ->and($notifications[0]->data['body'])->toBe("Hi @Bob Slack, the blue pallets are in #general-{$this->s}")
        ->and($notifications[0]->data['slack_message_id'])->toBe($message->id)
        ->and($notifications[0]->data['route'])->toBe(route('grp.chat.staff.slack.show', ['slackChannel' => $this->general->id, 'around' => $message->ts]))
        ->and(($this->mentions)($this->cat))->toHaveCount(0);
    Event::assertDispatched(BroadcastPersonalNotification::class, fn ($event) => $event->userId === $this->bob->id);
});

test('a mention in a thread reply links to the reply inside its thread, INI-445', function () {
    $parent = ($this->event)($this->general, ($this->ts)(1), 'Question');
    $reply  = ($this->event)($this->general, ($this->ts)(2), "<@UBOB{$this->s}|bob> can you check?", ['thread_ts' => $parent->ts]);

    expect(($this->mentions)($this->bob)->first()->data['route'])
        ->toBe(route('grp.chat.staff.slack.show', ['slackChannel' => $this->general->id, 'around' => $parent->ts, 'thread' => $parent->ts, 'reply' => $reply->ts]));
});

test('Slack mentions are off until the person picks a channel on their profile, INI-445', function () {
    $quiet = ($this->person)('DAN', null);
    $none  = ($this->person)('EVE', []);
    ($this->event)($this->general, ($this->ts)(1), "<@UDAN{$this->s}> <@UEVE{$this->s}> <@UBOB{$this->s}>");

    expect(($this->mentions)($quiet))->toHaveCount(0)
        ->and(($this->mentions)($none))->toHaveCount(0)
        ->and(($this->mentions)($this->bob))->toHaveCount(1);
});

test('mentioning yourself, a channel-wide or group mention, or a join notice notifies no one, INI-445', function () {
    ($this->event)($this->general, ($this->ts)(1), "Note to self <@UANN{$this->s}> and <!here> <!channel> <!subteam^S123|@team>");
    ($this->event)($this->general, ($this->ts)(2), "<@UBOB{$this->s}> has joined the channel", ['user' => "UBOB{$this->s}", 'subtype' => 'channel_join']);
    ($this->event)($this->general, ($this->ts)(3), "<@UCAT{$this->s}> was added", ['subtype' => 'channel_join']);

    expect(($this->mentions)($this->ann))->toHaveCount(0)
        ->and(($this->mentions)($this->bob))->toHaveCount(0)
        ->and(($this->mentions)($this->cat))->toHaveCount(0);
});

test('a person who may not see the channel, or an ignored or private channel, gets no Slack mention notification, INI-445', function () {
    $botLinked = ($this->person)('FAY');
    SlackUser::where('slack_id', "UFAY{$this->s}")->update(['is_bot' => true]);
    $inactive = ($this->person)('GUS');
    $inactive->update(['status' => false]);

    ($this->event)($this->general, ($this->ts)(1), "<@UFAY{$this->s}> <@UGUS{$this->s}>");
    ($this->event)($this->ignored, ($this->ts)(2), "<@UBOB{$this->s}>");
    ($this->event)($this->private, ($this->ts)(3), "<@UBOB{$this->s}>");
    ($this->event)($this->private, ($this->ts)(4), "<@UBOB{$this->s}>", ['channel_type' => 'group']);

    expect(($this->mentions)($botLinked))->toHaveCount(0)
        ->and(($this->mentions)($inactive))->toHaveCount(0)
        ->and(($this->mentions)($this->bob))->toHaveCount(0);
});

test('the history sync and nightly catch-up never notify, and a later edit of such a message notifies only a newly added person, INI-445', function () {
    $ts = ($this->ts)(1);
    StoreSlackMessage::make()->store($this->general, ['type' => 'message', 'user' => "UANN{$this->s}", 'text' => "<@UBOB{$this->s}> old one", 'ts' => $ts]);
    expect(($this->mentions)($this->bob))->toHaveCount(0);

    ($this->edit)($this->general, $ts, "<@UBOB{$this->s}> old one, fixed typo", 10);
    expect(($this->mentions)($this->bob))->toHaveCount(0);

    ($this->edit)($this->general, $ts, "<@UBOB{$this->s}> <@UCAT{$this->s}> old one, fixed typo", 20);
    expect(($this->mentions)($this->bob))->toHaveCount(0)
        ->and(($this->mentions)($this->cat))->toHaveCount(1);
});

test('one notification per person and message: a repeated event, an edit keeping the mention, or removing and adding it back notify once, INI-445', function () {
    $ts = ($this->ts)(1);
    ($this->event)($this->general, $ts, "<@UBOB{$this->s}> please look");
    ($this->event)($this->general, $ts, "<@UBOB{$this->s}> please look");
    ($this->edit)($this->general, $ts, "<@UBOB{$this->s}> please look now", 10);
    ($this->edit)($this->general, $ts, 'please look now', 20);
    ($this->edit)($this->general, $ts, "<@UBOB{$this->s}> please look now!", 30);
    expect(($this->mentions)($this->bob))->toHaveCount(1);

    ($this->edit)($this->general, $ts, "<@UBOB{$this->s}> <@UCAT{$this->s}> please look now!", 40);
    expect(($this->mentions)($this->bob))->toHaveCount(1)
        ->and(($this->mentions)($this->cat))->toHaveCount(1);
});

test('a mention reaches only the aiku user linked to that Slack person, never someone who typed its id on their profile or a link with another email, INI-445', function () {
    $impostor = ($this->person)('HAL');
    $impostor->update(['slack_user_id' => "UBOB{$this->s}"]);
    $otherEmail = ($this->person)('IVY');
    SlackUser::where('slack_id', "UIVY{$this->s}")->update(['email' => 'someone-else-'.$this->s.'@example.com']);

    ($this->event)($this->general, ($this->ts)(1), "<@UBOB{$this->s}> <@UIVY{$this->s}>");

    expect(($this->mentions)($this->bob))->toHaveCount(1)
        ->and(($this->mentions)($impostor))->toHaveCount(0)
        ->and(($this->mentions)($otherEmail))->toHaveCount(0);
});

test('one recipient that fails does not stop the others from being told, INI-445', function () {
    Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, function ($event) {
        if ($event->notifiable->id === $this->bob->id) {
            throw new RuntimeException('duplicate key');
        }
    });

    ($this->event)($this->general, ($this->ts)(1), "<@UBOB{$this->s}> <@UCAT{$this->s}> and <@UANN{$this->s}>");

    expect(($this->mentions)($this->bob))->toHaveCount(0)
        ->and(($this->mentions)($this->cat))->toHaveCount(1);
});

test('the Slack direct message escapes what was typed in Slack, so it cannot become a link or a mention, INI-445', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $dan = ($this->person)('DAN', ['slack']);
    $dan->update(['slack_user_id' => "UDAN{$this->s}"]);

    ($this->event)($this->general, ($this->ts)(1), "<@UDAN{$this->s}> see &lt;https://evil.example|your payslip&gt; &amp; &lt;!channel&gt;");

    $texts = collect(\Illuminate\Support\Facades\Queue::pushed(\Lorisleiva\Actions\Decorators\JobDecorator::class))
        ->filter(fn ($job) => $job->getAction() instanceof \App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage)
        ->map(fn ($job) => $job->getParameters()[1]);
    expect(($this->mentions)($dan)->first()->data['body'])->toContain('<https://evil.example|your payslip>')
        ->and($texts)->toHaveCount(1)
        ->and($texts->first())->toContain('&lt;https://evil.example|your payslip&gt; &amp; &lt;!channel&gt;')
        ->and($texts->first())->not->toContain('<https://evil')
        ->and($texts->first())->toEndWith('|Open in aiku>');
});
