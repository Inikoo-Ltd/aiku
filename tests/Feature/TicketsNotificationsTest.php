<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Helpers\Ticket\GetRetinaTicketBadgeData;
use App\Actions\Helpers\Ticket\SyncTicketCollaborators;
use Illuminate\Support\Str;
use App\Actions\Helpers\Ticket\LinkTicketsToAppDeployment;
use App\Models\DevOps\AppDeployment;
use App\Actions\Helpers\Ticket\ReceiveSlackTicketReaction;
use App\Actions\Helpers\Ticket\RepairSlackTicketReporters;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\PostTicketSlackThreadReply;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\SyncTicketSlackAlert;
use App\Actions\Helpers\Ticket\StoreTicketFromSlack;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use Illuminate\Support\Facades\DB;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Models\SysAdmin\User;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Guest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketNotification;
use App\Events\BroadcastTicketBadgeUpdate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use App\Actions\Helpers\Ticket\NotifyTicketUsers;
use App\Actions\SysAdmin\User\SendUserPushNotification;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

/* Laravel writes datetimes as naive UTC strings, so a Postgres session on any other zone
   stores them shifted and hands them back shifted: a waiting_at written a second ago reads
   hours old, and a fixture written at 11:48 comes back at another time entirely. Pinned for
   this file only, and put back afterwards so no other suite inherits it. */
beforeEach(function () {
    DB::statement("set time zone 'UTC'");

    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->group    = $this->organisation->group;
    $this->website  = createWebsite($this->shop);
    $this->website->update(['status' => true]);
    $this->customer = createCustomer($this->shop);
    $this->webUser  = createWebUser($this->customer);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    $this->user->assignRole('help-desk-supervisor');
    $this->user->forgetWildcardPermissionIndex();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

afterEach(function () {
    DB::statement('set time zone default');
});

test('staff reporter is told of the question by email and slack as their profile prefers', function () {
    Notification::fake();
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/*' => Http::response(['ok' => true])]);

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $reporter->update(['slack_user_id' => 'U123', 'settings' => ['notifications' => ['ticket_needs_reply' => ['email', 'slack']]]]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ask me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Which order?', 'waiting_hours' => 24])->assertRedirect();

    Notification::assertSentTo($reporter, TicketNotification::class);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === 'U123');

    $reporter->update(['settings' => ['notifications' => ['ticket_needs_reply' => []]]]);
    Notification::fake();
    UpdateTicket::make()->action($ticket->fresh(), ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Still?'])->assertRedirect();
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification, $channels) => $channels === ['database']);

    $reporter->update(['settings' => ['notifications' => ['ticket_resolved' => ['email']]]]);
    Event::fake([BroadcastTicketBadgeUpdate::class]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved', 'question' => 'Fixed the voucher total'])->assertRedirect();
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'is done'));
    expect($ticket->comments()->where('body', 'Fixed the voucher total')->count())->toBe(1);
    Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $reporter->id && ($event->notification['reason'] ?? null) === 'resolved');

    app()->detectEnvironment(fn () => 'production');
    $mail = (new TicketNotification($ticket, 'Subject', ['Line'], 'View'))->toMail($reporter);
    app()->detectEnvironment(fn () => 'testing');
    expect($mail->mailer)->toBe('ses')
        ->and($mail->from)->toBe(['help@aiku.io', 'Aiku Help']);
});

test('a reporter mutes their own ticket and hears nothing more about it, but nobody else can mute it for them', function () {
    Notification::fake();

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Mute me', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    $other = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    actingAs($other);
    patch(route('grp.models.ticket.update', $ticket->id), ['reporter_muted' => true])->assertForbidden();
    expect($ticket->fresh()->reporter_muted)->toBeFalse();

    actingAs($reporter);
    patch(route('grp.models.ticket.update', $ticket->id), ['reporter_muted' => true])->assertRedirect();
    expect($ticket->fresh()->reporter_muted)->toBeTrue();

    actingAs($this->user);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertRedirect();
    Notification::assertNotSentTo($reporter, TicketNotification::class);

    actingAs($reporter);
    patch(route('grp.models.ticket.update', $ticket->id), ['reporter_muted' => false])->assertRedirect();
    expect($ticket->fresh()->reporter_muted)->toBeFalse();
});

test('browser channel queues a web push to the reporter devices and prunes expired endpoints', function () {
    Notification::fake();
    Config::set('services.webpush.public_key', 'public');
    Config::set('services.webpush.private_key', 'private');

    post(route('grp.profile.push-subscriptions.store'), ['endpoint' => 'https://push.example.com/live', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
    post(route('grp.profile.push-subscriptions.store'), ['endpoint' => 'https://push.example.com/gone', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(2);

    $this->user->update(['settings' => ['notifications' => ['ticket_resolved' => ['browser']]]]);
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Push me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $this->user->id]);

    Queue::fake();
    NotifyTicketUsers::make()->done($ticket->fresh(), null);
    SendUserPushNotification::assertPushed(1);

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->twice();
    $webPush->shouldReceive('flush')->andReturn((function () {
        yield new MessageSentReport(new GuzzleRequest('POST', 'https://push.example.com/live'), new GuzzleResponse(201), true);
        yield new MessageSentReport(new GuzzleRequest('POST', 'https://push.example.com/gone'), new GuzzleResponse(410), false);
    })());
    app()->bind(WebPush::class, fn () => $webPush);

    expect(SendUserPushNotification::run($this->user, ['title' => 'T', 'body' => 'B', 'url' => '/']))->toBe(1)
        ->and($this->user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example.com/live'])
        ->and($this->user->pushSubscriptions()->first()->last_used_at)->not->toBeNull();

    delete(route('grp.profile.push-subscriptions.delete'), ['endpoint' => 'https://push.example.com/live'])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

test('new tickets post one alert in the Slack tickets channel and edit it as status and assignee change', function () {
    Config::set('services.slack.notifications.tickets_channel', '#tickets');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'C9', 'ts' => '42.1']), 'slack.com/api/chat.update' => Http::response(['ok' => true])]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ping me']);
    expect($ticket->fresh()->data['slack_alert'])->toEqualCanonicalizing(['channel' => 'C9', 'ts' => '42.1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === '#tickets' && str_contains(json_encode($request['blocks']), 'Status:* Todo'));

    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.update') && $request['ts'] === '42.1' && $request['channel'] === 'C9' && str_contains(json_encode($request['blocks']), 'Status:* In progress') && str_contains(json_encode($request['blocks']), $this->user->username));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '42.1' && str_ends_with($request['text'], 'In progress'));

    UpdateTicket::make()->action($ticket, ['priority' => 'urgent']);
    Http::assertSentCount(3);

    Config::set('services.slack.notifications.tickets_channel', null);
    StoreTicket::make()->action($this->group, ['subject' => 'Silent']);
    Http::assertSentCount(3);
});

test('a status change or comment queues its Slack posts instead of waiting on Slack', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Queue my Slack']);

    Queue::fake();
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    StoreTicketComment::make()->action($ticket->fresh(), $this->user, ['body' => 'On it']);

    PostTicketSlackThreadReply::assertPushed(2);
    SyncTicketSlackAlert::assertPushed(1);
});

test('slack slash command raises a bug ticket for the matching aiku user', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $this->user->update(['email' => 'raul@example.com']);
    Http::fake(['slack.com/api/users.info*' => Http::response(['ok' => true, 'user' => ['profile' => ['email' => 'RAUL@example.com']]])]);
    Auth::logout();

    $params    = ['text' => "Labels print blank\nOnly on the SK printer", 'user_id' => 'U1', 'user_name' => 'raul', 'channel_name' => 'bugs'];
    $body      = http_build_query($params);
    $timestamp = (string) time();
    $headers   = [
        'X-Slack-Request-Timestamp' => $timestamp,
        'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
        'Content-Type'              => 'application/x-www-form-urlencoded',
    ];

    $response = $this->call('POST', route('webhooks.slack_ticket'), $params, [], [], $this->transformHeadersToServerVars($headers), $body);
    $response->assertOk();

    $ticket = Ticket::where('subject', 'Labels print blank')->firstOrFail();
    expect($ticket->kind)->toBe(TicketKindEnum::BUG)
        ->and($ticket->description)->toBe('Only on the SK printer')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->data['slack']['channel'])->toBe('bugs')
        ->and($response->json('text'))->toContain($ticket->reference);

    actingAs($this->user);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('ticket.is_from_slack', true)->has('ticket.reporter_avatar')
    );

    $this->call('POST', route('webhooks.slack_ticket'), $params, [], [], $this->transformHeadersToServerVars(['X-Slack-Request-Timestamp' => $timestamp, 'X-Slack-Signature' => 'v0=bad']), $body)->assertStatus(401);
});

test('deployed commits that name a ticket are recorded on it once', function () {
    $ticket     = StoreTicket::make()->action($this->group, ['subject' => 'Labels blank']);
    $deployment = AppDeployment::create(['commit_hash' => 'abc123abc123', 'semantic_version' => 'v2.360.0']);
    $commits    = [
        ['hash' => 'deadbeef0001', 'subject' => "🐛 dispatching: labels print blank, fixes {$ticket->reference}", 'name' => 'x', 'email' => 'x@x'],
        ['hash' => 'deadbeef0002', 'subject' => 'chore: unrelated', 'name' => 'x', 'email' => 'x@x'],
        ['hash' => 'deadbeef0003', 'subject' => 'HELP-999999 does not exist', 'name' => 'x', 'email' => 'x@x'],
    ];

    $linked = LinkTicketsToAppDeployment::run($deployment, $commits);
    LinkTicketsToAppDeployment::run($deployment, $commits);

    $ticket->refresh();
    expect($linked)->toBe([$ticket->reference => 1])
        ->and($ticket->data['commits'])->toHaveCount(1)
        ->and($ticket->data['commits'][0]['version'])->toBe('v2.360.0')
        ->and($ticket->comments()->count())->toBe(1)
        ->and($ticket->comments()->first()->body)->toContain('v2.360.0')
        ->and(TicketResource::make($ticket)->resolve()['commits'][0]['hash'])->toBe('deadbeef0001');
});

test('slack ticket reaction raises a ticket from the message and mirrors replies into its thread', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $this->user->update(['email' => 'raul@example.com']);
    Http::fake([
        'slack.com/api/users.info*'            => Http::response(['ok' => true, 'user' => ['name' => $this->user->username, 'real_name' => 'Somebody Else', 'profile' => ['email' => 'raul-on-slack@example.com']]]),
        'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => [[
            'user'  => 'U1',
            'text'  => "Picking shows 1 instead of 3\nFaire FPGB",
            'files' => [['id' => 'F1', 'name' => 'shot.png', 'mimetype' => 'image/png', 'size' => 100, 'url_private_download' => 'https://files.slack.com/shot.png']],
        ]]]),
        'files.slack.com/*'                    => Http::response(UploadedFile::fake()->image('shot.png', 10, 10)->getContent(), 200, ['Content-Type' => 'image/png']),
        'slack.com/api/chat.postMessage'       => Http::response(['ok' => true]),
    ]);
    Auth::logout();

    $post = function (array $payload, ?string $signature = null) {
        $body      = json_encode($payload);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => $signature ?? 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/json',
        ];

        return $this->call('POST', route('webhooks.slack_events'), [], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $post(['type' => 'url_verification', 'challenge' => 'abc'])->assertOk()->assertJson(['challenge' => 'abc']);
    $post(['type' => 'event_callback', 'event' => []], 'v0=bad')->assertStatus(401);

    $event = ['type' => 'event_callback', 'event' => ['type' => 'reaction_added', 'reaction' => 'ticket', 'user' => 'U2', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '1789138198.657369']]];
    $post($event)->assertOk();
    $post($event)->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'reaction_added', 'reaction' => 'eyes', 'item' => ['type' => 'message', 'channel' => 'C1', 'ts' => '2']]])->assertOk();

    $ticket = Ticket::where('subject', 'Picking shows 1 instead of 3')->sole();
    expect($ticket->description)->toBe('Faire FPGB')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($this->user->fresh()->slack_user_id)->toBe('U1')
        ->and($ticket->data['slack']['ts'])->toBe('1789138198.657369')
        ->and($ticket->getMedia('ticket_images')->count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'conversations.history'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'files.slack.com'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '1789138198.657369' && str_contains($request['text'], $ticket->reference));

    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Fixed, please check']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::WAITING->value]);
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'ts' => '1789138199.1', 'thread_ts' => '1789138198.657369', 'text' => 'It is FPGB-123']])->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'bot_id' => 'B1', 'ts' => '1789138199.2', 'thread_ts' => '1789138198.657369', 'text' => 'HELP-1 is now Waiting']])->assertOk();
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C1', 'user' => 'U1', 'ts' => '1789138199.3', 'text' => 'unrelated top level message']])->assertOk();
    $fromModal = StoreTicket::make()->action($this->group, ['subject' => 'Born in aiku', 'data' => ['slack_alert' => ['channel' => 'C9', 'ts' => '55.1']]]);
    $post(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => 'C9', 'user' => 'U1', 'ts' => '55.2', 'thread_ts' => '55.1', 'text' => 'reply under the alert card']])->assertOk();
    expect($fromModal->comments()->pluck('body')->all())->toBe(['reply under the alert card'])
        ->and(ReceiveSlackTicketReaction::run($this->group, 'C9', '55.1', 'U2')->id)->toBe($fromModal->id);
    StoreTicketComment::make()->action($fromModal, $this->user, ['body' => 'answer from aiku']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '55.1' && str_contains($request['text'], 'answer from aiku'));
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::ANSWERED)
        ->and($ticket->comments()->pluck('body')->all())->toBe(['Fixed, please check', 'It is FPGB-123']);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'It is FPGB-123'));
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_contains($request['text'], 'Fixed, please check'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && str_ends_with($request['text'], 'Done'));

    $bare = StoreTicketFromSlack::run($this->group, ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '7', 'subject' => 'No clue where <https://app.aiku.io/org/aw/shops/uk|here>']);
    expect($bare->data['reference_url'])->toBe('https://app.aiku.io/org/aw/shops/uk');
    $bare = StoreTicketFromSlack::run($this->group, ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '8', 'subject' => 'Nothing to go on']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '8' && str_contains($request['text'], 'screenshot'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '7' && str_contains($request['text'], 'screenshot'));

    $orphan = StoreTicket::make()->action($this->group, ['subject' => 'Unknown reporter', 'data' => ['slack' => ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '9']]]);
    expect($orphan->reporter_id)->toBeNull()
        ->and(RepairSlackTicketReporters::run())->toBe(1)
        ->and($orphan->fresh()->reporter_id)->toBe($this->user->id);
});

test('slack shortcut opens the ticket modal and only its submit creates the ticket', function () {
    Config::set('services.slack.signing_secret', 'shh');
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake([
        'slack.com/api/users.info*'      => Http::response(['ok' => true, 'user' => ['name' => $this->user->username, 'profile' => ['email' => 'nobody@example.com']]]),
        'slack.com/api/views.open'       => Http::response(['ok' => true]),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        'files.slack.com/*'              => Http::response(UploadedFile::fake()->image('modal.png', 10, 10)->getContent(), 200, ['Content-Type' => 'image/png']),
    ]);
    Auth::logout();

    $post = function (array $payload) {
        $body      = http_build_query(['payload' => json_encode($payload)]);
        $timestamp = (string) time();
        $headers   = [
            'X-Slack-Request-Timestamp' => $timestamp,
            'X-Slack-Signature'         => 'v0='.hash_hmac('sha256', "v0:$timestamp:$body", 'shh'),
            'Content-Type'              => 'application/x-www-form-urlencoded',
        ];

        return $this->call('POST', route('webhooks.slack_interactivity'), ['payload' => json_encode($payload)], [], [], $this->transformHeadersToServerVars($headers), $body);
    };

    $post(['type' => 'message_action', 'callback_id' => 'raise_ticket', 'trigger_id' => 'T1', 'user' => ['id' => 'U1'], 'channel' => ['id' => 'C1'], 'message' => ['ts' => '55.1', 'text' => "Shortcut labels blank\nSK printer only"]])->assertOk();
    Http::assertSent(function ($request) {
        $view = $request['view'] ?? null;

        return str_contains($request->url(), 'views.open')
            && $request['trigger_id'] === 'T1'
            && $view['blocks'][0]['element']['initial_value'] === 'Shortcut labels blank'
            && $view['blocks'][1]['element']['initial_value'] === 'SK printer only'
            && $view['blocks'][2]['optional'] === false
            && json_decode($view['private_metadata'], true) === ['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '55.1'];
    });
    expect(Ticket::where('subject', 'Shortcut labels blank')->exists())->toBeFalse();

    $post([
        'type' => 'view_submission',
        'user' => ['id' => 'U1'],
        'view' => [
            'callback_id'      => 'raise_ticket',
            'private_metadata' => json_encode(['user_id' => 'U1', 'channel_id' => 'C1', 'ts' => '55.1']),
            'state'            => ['values' => [
                'subject'       => ['value' => ['value' => 'Shortcut labels blank']],
                'description'   => ['value' => ['value' => 'SK printer only']],
                'reference_url' => ['value' => ['value' => 'https://app.aiku.io/org/aw/warehouses/ac']],
                'files'         => ['value' => ['files' => [['id' => 'F9', 'name' => 'modal.png', 'mimetype' => 'image/png', 'size' => 100, 'url_private_download' => 'https://files.slack.com/modal.png']]]],
            ]],
        ],
    ])->assertOk();

    $ticket = Ticket::where('subject', 'Shortcut labels blank')->sole();
    expect($ticket->description)->toBe('SK printer only')
        ->and($ticket->reporter_id)->toBe($this->user->id)
        ->and($ticket->data['reference_url'])->toBe('https://app.aiku.io/org/aw/warehouses/ac')
        ->and($ticket->data['slack']['ts'])->toBe('55.1')
        ->and($ticket->getMedia('ticket_images')->count())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && ($request['thread_ts'] ?? null) === '55.1' && str_contains($request['text'], $ticket->reference));
});

test('a mentioned user is notified on the channels they chose and the plain comment notice is not doubled', function () {
    Notification::fake();
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    Http::fake(['slack.com/*' => Http::response(['ok' => true])]);

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $reporter->update(['nickname' => 'Mentionee', 'slack_user_id' => 'U999', 'settings' => ['notifications' => ['ticket_mention' => ['slack'], 'ticket_comment' => ['email']]]]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Mention me']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Can you check this @mentionee?']);

    Notification::assertSentToTimes($reporter, TicketNotification::class, 1);
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification, $channels) => str_contains($notification->subject, 'mentioned you') && $channels === ['database']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'chat.postMessage') && $request['channel'] === 'U999');
});

test('a reply to a waiting ticket and a new ticket reach the assignee and engineers with the reasons that ring an alert', function () {
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();

    Event::fake([BroadcastTicketBadgeUpdate::class]);
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Alert me', 'reporter_type' => 'User', 'reporter_id' => $reporter->id, 'assignee_id' => $this->user->id]);
    Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $engineer->id && ($event->notification['reason'] ?? null) === 'raised');

    $ticket->update(['status' => TicketStatusEnum::WAITING]);
    Event::fake([BroadcastTicketBadgeUpdate::class]);
    StoreTicketComment::make()->action($ticket->fresh(), $reporter, ['body' => 'Order 123']);
    Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $this->user->id && ($event->notification['reason'] ?? null) === 'answered');

    Event::fake([BroadcastTicketBadgeUpdate::class]);
    StoreTicketComment::make()->action($ticket->fresh(), $reporter, ['body' => 'And 124']);
    Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $this->user->id && ($event->notification['reason'] ?? null) === 'comment');
});

test('slack ticket direct message logs a refused delivery without failing and retries when slack is rate limited', function () {
    Config::set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    $user = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $user->update(['slack_user_id' => 'U404']);

    Http::fake(['slack.com/*' => Http::sequence()->push(['ok' => false, 'error' => 'user_not_found'])->push(['ok' => false, 'error' => 'ratelimited'], 429)]);
    \Illuminate\Support\Facades\Log::spy();
    \App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage::run($user, 'Hello');
    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['error'] === 'user_not_found' && $context['permanent'] === true)->once();

    expect(fn () => \App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage::run($user, 'Hello'))->toThrow(RuntimeException::class);
});

test('assigning a ticket pushes fresh badge counts to every engineer and QA user and the previous assignee', function () {
    setPermissionsTeamId($this->group->id);
    $otherEngineer = User::factory()->create(['group_id' => $this->group->id]);
    $otherEngineer->assignRole('help-desk-clerk');
    $qaUser = User::factory()->create(['group_id' => $this->group->id]);
    $qaUser->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::QA->value);
    $previousAssignee = User::factory()->create(['group_id' => $this->group->id]);
    $previousAssignee->assignRole('help-desk-clerk');
    $outsider = User::factory()->create(['group_id' => $this->group->id]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Queue push', 'assignee_id' => $previousAssignee->id]);

    Event::fake([BroadcastTicketBadgeUpdate::class]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id]);

    foreach ([$this->user, $otherEngineer, $qaUser, $previousAssignee] as $expectedUser) {
        Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $expectedUser->id);
    }
    Event::assertNotDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $outsider->id);

    Event::fake([BroadcastTicketBadgeUpdate::class]);
    StoreTicketComment::make()->action($ticket->fresh(), $this->user, ['body' => 'on it']);
    Event::assertNotDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $otherEngineer->id);
});

test('people mentioned in an engineering note are told, customers never are', function () {
    Notification::fake();
    setPermissionsTeamId($this->group->id);
    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $engineer  = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $colleague->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Needs a second look',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'assignee_id'   => $engineer->id,
    ]);

    StoreTicketComment::make()->action($ticket, $engineer, ['body' => "@{$colleague->username} and @{$reporter->username} please check the logs", 'is_internal' => true]);

    foreach ([$colleague, $reporter] as $mentioned) {
        Notification::assertSentTo($mentioned, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'engineering note'));
    }

    $customerTicket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Parcel lost']);
    StoreTicketComment::make()->action($customerTicket, $this->user, ['body' => '@'.$this->customer->fresh()->slug.' internal only', 'is_internal' => true]);
    Notification::assertNotSentTo($this->webUser, TicketNotification::class);
});

test('the mention list suggests the people on the ticket first, including the customer who raised it', function () {
    setPermissionsTeamId($this->group->id);
    $reporter     = User::factory()->create(['group_id' => $this->group->id]);
    $engineer     = User::factory()->create(['group_id' => $this->group->id]);
    $collaborator = User::factory()->create(['group_id' => $this->group->id]);
    $lead         = User::factory()->create(['group_id' => $this->group->id]);
    $stranger     = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $collaborator->assignRole('help-desk-clerk');
    $lead->assignRole('help-desk-supervisor');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Who should look at this',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'assignee_id'   => $engineer->id,
    ]);
    SyncTicketCollaborators::make()->action($ticket, [$collaborator->id]);

    $mentionable = collect(get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->json('options.mentionable'))->keyBy('username');
    foreach ([$reporter, $engineer, $collaborator, $lead] as $involved) {
        expect($mentionable[$involved->username]['suggested'])->toBeTrue();
    }
    expect($mentionable[$stranger->username]['suggested'])->toBeFalse()
        ->and($mentionable->where('is_customer', true)->count())->toBe(0);

    $customerTicket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Parcel damaged']);
    expect(collect(get(route('grp.json.ticket.controls', $customerTicket->id))->json('options.mentionable'))->firstWhere('is_customer', true))
        ->toMatchArray(['username' => $this->customer->fresh()->slug, 'suggested' => true]);
});

test('customers hear about progress on their tickets, never about internal notes, and opening the ticket marks updates read', function () {
    Mail::fake();
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');

    $ticket    = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Order never arrived']);
    $colleague = StoreWebUser::make()->action($this->customer, ['username' => 'colleague'.uniqid(), 'email' => 'colleague'.uniqid().'@testmail.com', 'password' => 'test']);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id]);

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertRedirect()->assertSessionHasNoErrors();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'Checking with the courier'])->assertRedirect()->assertSessionHasNoErrors();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'Courier lost it, refund needed', 'is_internal' => true])->assertRedirect()->assertSessionHasNoErrors();

    $ticketUrl     = route('retina.dropshipping.tickets.show', $ticket->reference, false);
    $badges        = GetRetinaTicketBadgeData::run($this->webUser->fresh());
    $ticketUpdates = collect($badges['recent'])->where('route', $ticketUrl);
    $titles        = $ticketUpdates->pluck('title')->all();
    expect($badges['mine']['in_progress']['count'])->toBeGreaterThanOrEqual(1)
        ->and($titles)->toContain(__(':reference is now :status', ['reference' => $ticket->reference, 'status' => TicketStatusEnum::labels()['in_progress']]))
        ->and($titles)->toContain(__(':reference has a new comment', ['reference' => $ticket->reference]))
        ->and($ticketUpdates->filter(fn ($update) => str_contains($update['title'].' '.$update['body'], 'refund') || str_contains($update['title'], 'internal'))->count())->toBe(0)
        ->and($ticketUpdates->where('read', false)->count())->toBeGreaterThanOrEqual(2);

    expect(collect(GetRetinaTicketBadgeData::run($colleague->fresh())['recent'])->where('route', $ticketUrl)->pluck('title')->all())
        ->toContain(__(':reference has a new comment', ['reference' => $ticket->reference]));

    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => '@'.$this->customer->fresh()->slug.' can you confirm the address?'])->assertRedirect()->assertSessionHasNoErrors();
    foreach ([$this->webUser, $colleague] as $customerWebUser) {
        expect(collect(GetRetinaTicketBadgeData::run($customerWebUser->fresh())['recent'])->where('route', $ticketUrl)->pluck('title')->all())
            ->toContain(__(':author mentioned you on :reference', ['author' => $engineer->contact_name ?: $engineer->username, 'reference' => $ticket->reference]));
    }

    $this->actingAs($this->webUser, 'retina')->get('http://'.$this->website->domain.'/app/dropshipping/support/'.$ticket->reference)->assertOk();
    expect(collect(GetRetinaTicketBadgeData::run($this->webUser->fresh())['recent'])->where('route', $ticketUrl)->where('read', false)->count())->toBe(0);
});

test('recently updated lists ticket notifications by ticket, with unread, mentions and the reason', function () {
    Notification::fake();
    $watcher = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $watcher->assignRole('help-desk-clerk');

    $commented = StoreTicket::make()->action($this->group, ['subject' => 'Recent commented']);
    $mentioned = StoreTicket::make()->action($this->group, ['subject' => 'Recent mentioned']);
    $readOnly  = StoreTicket::make()->action($this->group, ['subject' => 'Recent already read']);
    $legacy    = StoreTicket::make()->action($this->group, ['subject' => 'Recent legacy']);

    $notify = fn (Ticket $ticket, array $data, int $minutesAgo, bool $read = false) => $watcher->notifications()->create([
        'id'         => (string) Str::uuid(),
        'type'       => App\Notifications\TicketNotification::class,
        'data'       => ['type' => 'ticket', 'ticket_id' => $ticket->id, 'body' => '', 'route' => '', ...$data],
        'read_at'    => $read ? now() : null,
        'created_at' => now()->subMinutes($minutesAgo),
    ]);
    $notify($commented, ['title' => 'x has a new comment', 'reason' => 'comment'], 1);
    $notify($mentioned, ['title' => 'Someone mentioned you on it', 'reason' => 'mention'], 5, true);
    $notify($mentioned, ['title' => 'x is now In progress', 'reason' => 'status'], 3);
    $notify($readOnly, ['title' => 'x was edited', 'reason' => 'edited'], 2, true);
    $notify($legacy, ['title' => 'Artha mentioned you on HELP-1'], 4);

    actingAs($watcher);
    $rows = fn (string $scope) => collect(get(route('grp.json.ticket.recently_updated', ['scope' => $scope]))->assertOk()->json());

    $unread = $rows('unread');
    expect($unread->pluck('reference')->all())->toBe([$commented->reference, $mentioned->reference, $legacy->reference])
        ->and($unread->firstWhere('reference', $mentioned->reference)['reason'])->toBe('status')
        ->and($unread->firstWhere('reference', $mentioned->reference)['is_mentioned'])->toBeTrue()
        ->and($unread->firstWhere('reference', $legacy->reference)['reason'])->toBe('mention')
        ->and($unread->first())->toHaveKeys(['reason_label', 'reason_icon', 'notified_at', 'has_unread']);

    expect($rows('mentions')->pluck('reference')->sort()->values()->all())->toBe(collect([$mentioned->reference, $legacy->reference])->sort()->values()->all())
        ->and($rows('all')->pluck('reference')->all())->toContain($readOnly->reference)
        ->and($rows('all')->firstWhere('reference', $readOnly->reference)['has_unread'])->toBeFalse();

    get(route('grp.json.ticket.recently_updated', ['scope' => 'nope']))->assertSessionHasErrors('scope');
    actingAs($this->user);
});

test('ticket and comment resources carry the author identity the hover card needs', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Hover card identity', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'on it']);

    $ticketPayload = TicketResource::make($ticket->refresh())->resolve();

    expect($ticketPayload['reporter_key'])->toBe('User-'.$this->user->id)
        ->and($ticketPayload['reporter_username'])->toBe($this->user->username);

    $commentPayload = \App\Http\Resources\Helpers\TicketCommentResource::make($ticket->comments()->first())->resolve();

    expect($commentPayload['author_key'])->toBe('User-'.$this->user->id)
        ->and($commentPayload['author_username'])->toBe($this->user->username);
});

test('only the person a notification belongs to can mark it read or unread', function () {
    $mine = \App\Models\Notifications\Notification::create([
        'id'              => (string) \Illuminate\Support\Str::uuid(),
        'type'            => 'TicketRaised',
        'notifiable_type' => $this->user->getMorphClass(),
        'notifiable_id'   => $this->user->id,
        'data'            => json_encode(['title' => 'Mine to read']),
    ]);

    $theirs = \App\Models\Notifications\Notification::create([
        'id'              => (string) \Illuminate\Support\Str::uuid(),
        'type'            => 'TicketRaised',
        'notifiable_type' => $this->user->getMorphClass(),
        'notifiable_id'   => User::factory()->create(['group_id' => $this->group->id])->id,
        'data'            => json_encode(['title' => 'Somebody else, and their business']),
    ]);

    // the key is a uuid, which the model now says it is
    expect($mine->id)->toBe($mine->getAttributes()['id']);

    patchJson(route('grp.models.notifications.read', $mine->id))->assertSuccessful();
    expect($mine->refresh()->read_at)->not->toBeNull();

    // reading somebody else's by id used to answer with the row itself
    patchJson(route('grp.models.notifications.read', $theirs->id))->assertForbidden();
    patchJson(route('grp.models.notifications.unread', $theirs->id))->assertForbidden();

    expect($theirs->refresh()->read_at)->toBeNull();
});

test('changing a ticket, commenting on it or changing its collaborators tells the open ticket page to reload', function () {
    Mail::fake();
    Notification::fake();
    Event::fake([\App\Events\BroadcastTicketUpdated::class]);

    $engineer     = User::factory()->create(['group_id' => $this->group->id]);
    $collaborator = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $collaborator->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Live page']);
    $broadcastsFor = fn () => Event::dispatched(\App\Events\BroadcastTicketUpdated::class, fn ($event) => $event->ticketId === $ticket->id)->count();

    expect($broadcastsFor())->toBe(0);

    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);
    $afterUpdate = $broadcastsFor();
    expect($afterUpdate)->toBeGreaterThan(0);

    StoreTicketComment::make()->action($ticket, $engineer, ['body' => 'Looking into it'], false);
    $afterComment = $broadcastsFor();
    expect($afterComment)->toBeGreaterThan($afterUpdate);

    SyncTicketCollaborators::make()->action($ticket, [$collaborator->id]);
    expect($broadcastsFor())->toBeGreaterThan($afterComment);

    $event = new \App\Events\BroadcastTicketUpdated($ticket->id, $ticket->group_id);
    expect(collect($event->broadcastOn())->pluck('name')->all())->toBe(['private-grp.ticket.'.$ticket->id, 'private-grp.'.$ticket->group_id.'.general'])
        ->and($event->broadcastAs())->toBe('ticket-updated')
        ->and($event->broadcastWith())->toBe(['id' => $ticket->id]);
});
