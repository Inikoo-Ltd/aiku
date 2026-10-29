<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Ticket\SyncTicketCollaborators;
use Illuminate\Support\Str;
use App\Actions\Helpers\Ticket\ClassifyTicket;
use App\Actions\Helpers\Ticket\RateTicket;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UI\ShowTicketsReports;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Search\SearchTickets;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use Illuminate\Support\Facades\DB;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Models\SysAdmin\User;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketNotification;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Events\BroadcastTicketBadgeUpdate;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
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
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

afterEach(function () {
    DB::statement('set time zone default');
});

test('tickets dashboard counts created, done, status and assignees', function () {
    $this->travelTo(now()->previous('Wednesday')->setTime(10, 0));
    $before = ShowTicketsReports::make()->handle($this->group, '1w');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Report me', 'assignee_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicket::make()->action($this->group, ['subject' => 'Still open', 'assignee_id' => $this->user->id]);

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    $today = collect($stats['daily'])->firstWhere('date', now()->toDateString());
    $me    = collect($stats['assignees'])->firstWhere('name', $this->user->contact_name ?: $this->user->username);

    expect($stats['created'])->toBe($before['created'] + 2)
        ->and($stats['done'])->toBe($before['done'] + 1)
        ->and($stats['open'])->toBe($before['open'] + 1)
        ->and(count($stats['daily']))->toBe(8)
        ->and(collect($stats['daily'])->last()['open'])->toBe(collect($before['daily'])->last()['open'] + 1)
        ->and($stats['bucket'])->toBe('day')
        ->and(ShowTicketsReports::make()->handle($this->group, '1y')['bucket'])->toBe('week')
        ->and($today['created'])->toBeGreaterThanOrEqual(2)
        ->and(collect($stats['by_status'])->firstWhere('status', 'resolved')['total'])->toBeGreaterThanOrEqual(1)
        ->and($me['done'])->toBeGreaterThanOrEqual(1)
        ->and($me['open'])->toBeGreaterThanOrEqual(1)
        ->and($me['median_hours'])->not->toBeNull()
        ->and($me)->toHaveKeys(['longest_wait_days', 'rating', 'assigned', 'in_progress', 'resolved', 'cancelled'])
        ->and($stats['reporters'])->toBeArray()
        ->and($stats['assignees_total']['created'])->toBe($stats['created'])
        ->and(collect($stats['by_status'])->sum('total'))->toBe($stats['created'])
        ->and($stats)->not->toHaveKey('by_time_of_day')
        ->and($stats)->not->toHaveKey('by_hour')
        ->and($stats['by_quarter_hour'])->toHaveCount(96)
        ->and(collect($stats['by_quarter_hour'])->pluck('slot')->all())->toBe(range(0, 95))
        ->and(collect($stats['by_quarter_hour'])->sum('created'))->toBeLessThanOrEqual($stats['created'])
        ->and(collect($stats['by_quarter_hour'])->sum('done'))->toBeLessThanOrEqual($stats['done'])
        ->and($stats['weekdays'])->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(6)
        ->and(ShowTicketsReports::make()->handle($this->group, '1w', timezone: 'Asia/Kuala_Lumpur')['by_quarter_hour'])->not->toBe($stats['by_quarter_hour'])
        ->and($stats['resolvers_total']['done'])->toBe($before['resolvers_total']['done']);

    $forSomeoneWithNoTickets = ShowTicketsReports::make()->handle($this->group, '1w', assignee: User::factory()->create(['group_id' => $this->group->id]));

    $onlyCustomer = ShowTicketsReports::make()->handle($this->group, '1w', types: ['customer']);
    $helpAndEngineer = ShowTicketsReports::make()->handle($this->group, '1w', types: ['help', 'engineer']);

    expect($onlyCustomer['types'])->toBe(['customer'])
        ->and($onlyCustomer['created'] + $helpAndEngineer['created'])->toBe($stats['created']);

    expect($forSomeoneWithNoTickets['created'])->toBe(0)
        ->and(collect($forSomeoneWithNoTickets['by_quarter_hour'])->sum('created'))->toBeLessThanOrEqual($stats['created'])
        ->and(collect($forSomeoneWithNoTickets['by_quarter_hour'])->sum('started'))->toBe(0)
        ->and(collect($forSomeoneWithNoTickets['by_quarter_hour'])->sum('done'))->toBe(0);

    $oldTicket = StoreTicket::make()->action($this->group, ['subject' => 'Old but resolved now', 'assignee_id' => $this->user->id]);
    $oldTicket->update(['created_at' => now()->subMonths(3)]);
    UpdateTicket::make()->action($oldTicket, ['status' => TicketStatusEnum::RESOLVED->value]);

    $after = ShowTicketsReports::make()->handle($this->group, '1w');

    expect($after['assignees_total']['done'])->toBe($stats['assignees_total']['done'])
        ->and($after['resolvers_total']['done'])->toBe($stats['resolvers_total']['done'] + 1);

    get(route('grp.tickets.reports', ['created' => 'lm']))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/TicketsReports')->where('stats.interval', 'lm')->where('stats.timezone', $this->user->timezone_name)->has('stats.daily', now()->subMonth()->daysInMonth)
    );
});

test('tickets dashboard counts created, started and resolved tickets by the quarter hour of the day in the viewer timezone', function () {
    $hourly = fn (string $timezone = 'UTC') => collect(ShowTicketsReports::make()->handle($this->group, '1w', timezone: $timezone)['by_quarter_hour'])->keyBy('slot');
    $before            = $hourly();
    $kualaLumpurBefore = $hourly('Asia/Kuala_Lumpur');
    $day               = now('UTC')->previousWeekday()->startOfDay();

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Hour by hour', 'assignee_id' => $this->user->id]);
    $ticket->update([
        'created_at'  => $day->copy()->setTime(5, 10),
        'started_at'  => $day->copy()->setTime(9, 40),
        'resolved_at' => $day->copy()->setTime(14, 5),
        'closed_at'   => $day->copy()->setTime(16, 20),
    ]);

    $after = $hourly();

    expect($after->keys()->all())->toBe(range(0, 95))
        ->and($after[20]['created'])->toBe($before[20]['created'] + 1)
        ->and($after[38]['started'])->toBe($before[38]['started'] + 1)
        ->and($after[56]['done'])->toBe($before[56]['done'] + 1)
        ->and($after[65]['closed'])->toBe($before[65]['closed'] + 1)
        ->and($after[56]['closed'])->toBe($before[56]['closed'])
        ->and($after[20]['started'])->toBe($before[20]['started'])
        ->and($after[20]['done'])->toBe($before[20]['done'])
        ->and($after->sum('created'))->toBe($before->sum('created') + 1);

    $kualaLumpur = $hourly('Asia/Kuala_Lumpur');

    expect($kualaLumpur[52]['created'])->toBe($kualaLumpurBefore[52]['created'] + 1)
        ->and($kualaLumpur[20]['created'])->toBe($kualaLumpurBefore[20]['created'])
        ->and($kualaLumpur->sum('created'))->toBe($kualaLumpurBefore->sum('created') + 1);
});

test('reporter rates a resolved ticket once and CSAT shows on the dashboard', function () {
    $ticket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Rate me']);

    expect(RateTicket::canRate($ticket, $this->webUser))->toBeFalse();

    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    $ticket->refresh();

    expect(RateTicket::canRate($ticket, $this->webUser))->toBeTrue()
        ->and(RateTicket::canRate($ticket, $this->user))->toBeFalse();

    $this->actingAs($this->webUser, 'retina')
        ->post('http://'.$this->website->domain.'/app/models/ticket/'.$ticket->id.'/rate', ['rating' => 4, 'comment' => 'Quick fix'])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->rating)->toBe(4)
        ->and($ticket->rating_comment)->toBe('Quick fix')
        ->and($ticket->rated_at)->not->toBeNull()
        ->and(RateTicket::canRate($ticket, $this->webUser))->toBeFalse();

    $this->actingAs($this->webUser, 'retina')
        ->post('http://'.$this->website->domain.'/app/models/ticket/'.$ticket->id.'/rate', ['rating' => 1])
        ->assertForbidden();

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    expect($stats['csat'])->toBeGreaterThan(0)
        ->and(count($stats['csat_by_month']))->toBe(12)
        ->and(collect($stats['csat_by_month'])->last()['total'])->toBeGreaterThanOrEqual(1);
});

test('staff reporter rates their own resolved help ticket from grp', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Rate from grp', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CANCELLED->value]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_rate', true));
    post(route('grp.models.ticket.rate', $ticket->id), ['rating' => 5])->assertRedirect();

    expect($ticket->fresh()->rating)->toBe(5);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_rate', false)->where('ticket.rating', 5));
});

test('board only shows tickets closed in the last 24 hours', function () {
    $fresh = StoreTicket::make()->action($this->group, ['subject' => 'Closed today']);
    $stale = StoreTicket::make()->action($this->group, ['subject' => 'Closed last week']);
    UpdateTicket::make()->action($fresh, ['status' => TicketStatusEnum::RESOLVED->value]);
    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::RESOLVED->value]);
    $stale->update(['closed_at' => now()->subDays(7)]);

    get(route('grp.tickets.board', ['periods' => ['closed' => '24h']]))->assertInertia(function (AssertableInertia $page) use ($fresh, $stale) {
        $columns = collect($page->toArray()['props']['columns']);
        expect($columns->firstWhere('key', 'open')['period'])->toBeNull();
        $references = $columns->firstWhere('key', 'closed')['tickets'];
        $references = collect($references)->pluck('reference');

        expect($references)->toContain($fresh->reference)
            ->and($references)->not->toContain($stale->reference);
    });
});

test('list and board show only tickets created in the chosen interval', function () {
    $fresh = StoreTicket::make()->action($this->group, ['subject' => 'Raised today']);
    $old   = StoreTicket::make()->action($this->group, ['subject' => 'Raised last year']);
    $old->update(['created_at' => now()->subYear()->subMonth()]);

    get(route('grp.tickets.list', ['created' => 'tdy']))->assertInertia(function (AssertableInertia $page) use ($fresh, $old) {
        $references = collect($page->toArray()['props']['data']['data'])->pluck('reference');
        expect($references)->toContain($fresh->reference)->and($references)->not->toContain($old->reference)
            ->and($page->toArray()['props']['createdInterval'])->toBe('tdy');
    });

    $old->update(['created_at' => now(), 'priority' => 'urgent']);
    get(route('grp.tickets.list', ['elements' => ['priority' => 'urgent']]))->assertInertia(function (AssertableInertia $page) use ($fresh, $old) {
        $references = collect($page->toArray()['props']['data']['data'])->pluck('reference');
        expect($references)->toContain($old->reference)->and($references)->not->toContain($fresh->reference);
    });
    $old->update(['created_at' => now()->subYear()->subMonth()]);

    $fresh->update(['created_at' => now()->subHours(2)]);
    get(route('grp.tickets.list', ['created' => '1h', 'perPage' => 1000]))->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['data']['data'])->pluck('reference'))->not->toContain($fresh->reference));
    get(route('grp.tickets.list', ['created' => '3h', 'perPage' => 1000]))->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['data']['data'])->pluck('reference'))->toContain($fresh->reference));

    get(route('grp.tickets.board', ['created' => '24h']))->assertInertia(function (AssertableInertia $page) use ($fresh, $old) {
        $references = collect($page->toArray()['props']['columns'])->flatMap(fn ($column) => $column['tickets'])->pluck('reference');
        expect($references)->toContain($fresh->reference)->and($references)->not->toContain($old->reference);
    });
});

test('ticket badges count my tickets and the engineer queue, and engineers hear of new tickets in-app', function () {
    Notification::fake();
    Event::fake([BroadcastTicketBadgeUpdate::class]);

    $count    = fn (User $user, string $slot, string $key) => GetTicketBadgeData::run($user)[$slot][$key]['count'];
    $baseline = GetTicketBadgeData::run($this->user)['queue'];

    $reporter = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Badge me', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    Notification::assertSentTo($this->user, TicketNotification::class, fn ($notification, $channels) => $channels === ['database'] && str_contains($notification->subject, $ticket->reference));
    Notification::assertNotSentTo($reporter, TicketNotification::class);
    Event::assertDispatched(BroadcastTicketBadgeUpdate::class, fn (BroadcastTicketBadgeUpdate $event) => $event->userId === $this->user->id && $event->notification !== null);

    expect(GetTicketBadgeData::run($reporter)['queue'])->toBeNull()
        ->and($count($reporter, 'mine', 'to_do'))->toBe(1)
        ->and($count($reporter, 'mine', 'in_progress'))->toBe(0)
        ->and($count($reporter, 'mine', 'waiting'))->toBe(0)
        ->and($count($this->user, 'queue', 'new_unassigned'))->toBe($baseline['new_unassigned']['count'] + 1)
        ->and($count($this->user, 'queue', 'overdue'))->toBe($baseline['overdue']['count'])
        ->and($count($this->user, 'queue', 'assigned_to_me'))->toBe($baseline['assigned_to_me']['count']);

    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id, 'status' => TicketStatusEnum::WAITING->value, 'question' => 'Which printer?']);
    $ticket->update(['created_at' => now()->subDays(2)]);

    expect($count($reporter, 'mine', 'waiting'))->toBe(1)
        ->and($count($this->user, 'queue', 'assigned_to_me'))->toBe($baseline['assigned_to_me']['count'])
        ->and($count($this->user, 'queue', 'waiting'))->toBe($baseline['waiting']['count'] + 1);

    StoreTicketComment::make()->action($ticket, $reporter, ['body' => 'The red one']);
    Notification::assertSentTo($this->user, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'answered '.$ticket->reference));

    expect($count($this->user, 'queue', 'replied'))->toBe($baseline['replied']['count'] + 1)
        ->and($count($this->user, 'queue', 'waiting'))->toBe($baseline['waiting']['count'])
        ->and($count($this->user, 'queue', 'overdue'))->toBe($baseline['overdue']['count'] + 1);

    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect($count($reporter, 'mine', 'to_do') + $count($reporter, 'mine', 'in_progress') + $count($reporter, 'mine', 'waiting'))->toBe(0)
        ->and($count($this->user, 'queue', 'overdue'))->toBe($baseline['overdue']['count']);
});

test('tickets reports link to filtered lists by assignee and dates', function () {
    $mine = StoreTicket::make()->action($this->group, ['subject' => 'Assigned to me', 'assignee_id' => $this->user->id]);
    UpdateTicket::make()->action($mine, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicket::make()->action($this->group, ['subject' => 'Not assigned to me']);

    $from = $mine->fresh()->created_at->toDateString();

    get(route('grp.tickets.list', ['filter' => ['assignee' => $this->user->username], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', Ticket::where('assignee_id', $this->user->id)->count()));

    get(route('grp.tickets.list', ['filter' => ['created_since' => $from], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', Ticket::where('created_at', '>=', $from)->count()));

    get(route('grp.tickets.list', ['filter' => ['resolved_since' => $from], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', Ticket::where('resolved_at', '>=', $from)->count()));

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    expect($stats)->toHaveKey('from');
    foreach ($stats['assignees'] as $row) {
        expect($row)->toHaveKeys(['username', 'short_name']);
    }
});

test('engineers raise task and qa tickets, staff cannot', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $staff    = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');

    expect(collect(TicketKindEnum::raisableBy($engineer))->pluck('value')->all())->toBe(['bug', 'feature', 'task', 'qa', 'documentation', 'data_integrity', 'support', 'aurora'])
        ->and(collect(TicketKindEnum::raisableBy($staff))->pluck('value')->all())->toBe(['bug', 'feature', 'documentation', 'data_integrity', 'support', 'aurora']);

    actingAs($staff);
    post(route('grp.models.ticket.store'), ['subject' => 'Do it for me', 'kind' => 'task'])->assertSessionHasErrors('kind');

    actingAs($engineer);
    post(route('grp.models.ticket.store'), ['subject' => 'Please test totals', 'kind' => 'qa'])->assertSessionHasNoErrors();
    post(route('grp.models.ticket.store'), ['subject' => 'Printer blank', 'kind' => 'bug'])->assertSessionHasNoErrors();

    $qaTicket = Ticket::where('subject', 'Please test totals')->first();
    expect($qaTicket->kind)->toBe(TicketKindEnum::QA)
        ->and($qaTicket->defaultWaitingHours())->toBe(14 * 24);
});

test('recent ticket updates in each popup are about that popup\'s own tickets', function () {
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');

    $reported = StoreTicket::make()->action($this->group, ['subject' => 'I reported this', 'reporter_type' => 'User', 'reporter_id' => $engineer->id]);
    $assigned = StoreTicket::make()->action($this->group, ['subject' => 'I work on this', 'assignee_id' => $engineer->id]);
    $other    = StoreTicket::make()->action($this->group, ['subject' => 'Someone else entirely']);

    $engineer->notifications()->delete();
    foreach ([$reported, $assigned, $other] as $ticket) {
        $engineer->notifications()->create([
            'id'   => (string) Str::uuid(),
            'type' => TicketNotification::class,
            'data' => ['type' => 'ticket', 'ticket_id' => $ticket->id, 'title' => $ticket->subject, 'body' => '', 'route' => ''],
        ]);
    }

    $badges = GetTicketBadgeData::run($engineer);

    expect(collect($badges['recent'])->pluck('title')->all())->toBe(['I reported this'])
        ->and(collect($badges['queue_recent'])->pluck('title')->all())->toBe(['I work on this']);
});

test('the tickets to fix popup groups the team queue, my work and my QA checks by role', function () {
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $engineerRows = GetTicketBadgeData::run($engineer)['queue'];
    $qaRows       = GetTicketBadgeData::run($qa)['queue'];

    expect(collect($engineerRows)->map(fn ($row) => $row['section'])->all())->toBe([
        'new_unassigned' => 'team', 'overdue' => 'team',
        'assigned_to_me' => 'mine', 'collaborating' => 'mine', 'waiting' => 'mine', 'replied' => 'mine', 'qa_failed' => 'mine', 'qa_passed' => 'mine',
    ])
        ->and(array_keys($qaRows))->toContain('qa_to_check')
        ->and(array_keys($qaRows))->not->toContain('assigned_to_me');

    $failed = StoreTicket::make()->action($this->group, ['subject' => 'Fix me again', 'assignee_id' => $engineer->id]);
    $failed->update(['qa_status' => TicketQaStatusEnum::FAILED]);
    $passed = StoreTicket::make()->action($this->group, ['subject' => 'Good to go', 'assignee_id' => $engineer->id]);
    $passed->update(['qa_status' => TicketQaStatusEnum::PASSED]);
    $toCheck = StoreTicket::make()->action($this->group, ['subject' => 'Check me']);
    $toCheck->update(['qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => $qa->id]);

    expect(GetTicketBadgeData::run($engineer)['queue']['qa_failed']['count'])->toBe($engineerRows['qa_failed']['count'] + 1)
        ->and(GetTicketBadgeData::run($engineer)['queue']['qa_passed']['count'])->toBe($engineerRows['qa_passed']['count'] + 1)
        ->and(GetTicketBadgeData::run($qa)['queue']['qa_to_check']['count'])->toBe($qaRows['qa_to_check']['count'] + 1);

    UpdateTicket::make()->action($passed, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect(GetTicketBadgeData::run($engineer)['queue']['qa_passed']['count'])->toBe($engineerRows['qa_passed']['count']);
});

test('ticket search ranks subject over description over comments, understands key:value tokens and jumps to a reference', function () {
    $bySubject     = StoreTicket::make()->action($this->group, ['subject' => 'Email marketing broken', 'description' => 'nothing here', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    $byDescription = StoreTicket::make()->action($this->group, ['subject' => 'Something else', 'description' => 'The email marketing tool times out']);
    $byComment     = StoreTicket::make()->action($this->group, ['subject' => 'Unrelated', 'description' => 'unrelated']);
    StoreTicketComment::make()->handle($byComment, $this->user, ['body' => 'Same as the email marketing bug'], mirrorToSlack: false, notifyUsers: false);
    $byInternal    = StoreTicket::make()->action($this->group, ['subject' => 'Quiet', 'description' => 'quiet']);
    StoreTicketComment::make()->handle($byInternal, $this->user, ['body' => 'email marketing note'], mirrorToSlack: false, notifyUsers: false)->update(['is_internal' => true]);
    $other         = StoreTicket::make()->action($this->group, ['subject' => 'Invoice PDF export', 'description' => 'nothing']);

    $search = function (string $q) {
        $hits = collect();
        get(route('grp.tickets.list', ['filter' => ['global' => $q]]))->assertInertia(function (AssertableInertia $page) use (&$hits) {
            $hits = collect($page->toArray()['props']['data']['data']);
        });

        return $hits;
    };

    $hits = $search('email marke');
    expect($hits->pluck('reference')->take(2)->all())->toBe([$bySubject->reference, $byDescription->reference])
        ->and($hits->pluck('reference')->slice(2)->sort()->values()->all())->toBe(collect([$byComment->reference, $byInternal->reference])->sort()->values()->all())
        ->and($hits->first()['search_snippet'])->toContain('<mark>Email</mark>')
        ->and($search('email -tool')->pluck('reference'))->not->toContain($byDescription->reference)
        ->and($search('"marketing tool"')->pluck('reference')->all())->toBe([$byDescription->reference])
        ->and($search('email status:open reporter:me')->pluck('reference'))->toContain($bySubject->reference)
        ->and($search('email status:resolved'))->toBeEmpty()
        ->and($search('email is:unassigned after:'.now()->toDateString())->pluck('reference')->all())->toContain($bySubject->reference, $byDescription->reference, $byComment->reference, $byInternal->reference)
        ->and($search('email before:'.now()->toDateString()))->toBeEmpty()
        ->and($search('email assignee:'.$this->user->username))->toBeEmpty();

    foreach ([(string) $other->number, 'help-'.$other->number, 'HELP'.$other->number, 'https://app.aiku.io/tickets/'.$other->reference.'?tab=comments'] as $reference) {
        expect($search($reference)->pluck('reference')->all())->toBe([$other->reference], $reference);
    }

    $other->update(['subject' => 'Renamed to email digest']);
    expect($search('digest')->pluck('reference')->all())->toBe([$other->reference])
        ->and($search('emial digest')->pluck('reference')->all())->toBe([$other->reference])
        ->and($search('email marketting')->pluck('reference')->first())->toBe($bySubject->reference);

    $staff = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $staff->removeRole('group-admin');
    actingAs($staff);
    expect($search('email')->pluck('reference'))->not->toContain($byInternal->reference)
        ->and(get(route('grp.search.index', ['q' => 'email marke', 'route_src' => 'grp.tickets.board']))->assertOk()->json('results.tickets.*.code'))->toContain($bySubject->reference)
        ->and(SearchTickets::run((string) $other->number)['results']['tickets'][0]['href'])->toBe(route('grp.tickets.show', $other->reference))
        ->and(get(route('grp.search.index', ['q' => strtolower($other->reference), 'route_src' => 'grp.dashboard.show']))->assertOk()->json('results.tickets.*.code'))->toBe([$other->reference]);
});

test('tickets sidebar link opens the board for everyone', function () {
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');

    expect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($this->user)['tickets']['route']['name'])->toBe('grp.tickets.board')
        ->and(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($clerk)['tickets']['route']['name'])->toBe('grp.tickets.board')
        ->and(\App\Actions\UI\Grp\Layout\GetOrganisationNavigation::run($clerk, $this->organisation)['tickets']['route']['name'])->toBe('grp.org.tickets.board')
        ->and(\App\Actions\UI\Grp\Layout\GetShopNavigation::run($this->shop, $clerk)['tickets']['route']['name'])->toBe('grp.org.shops.show.tickets.board');

    $tabs = fn (array $navigation) => collect($navigation['tickets']['topMenu']['subSections'])->pluck('label')->all();
    expect($tabs(\App\Actions\UI\Grp\Layout\GetOrganisationNavigation::run($clerk, $this->organisation)))->toBe($tabs(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($clerk)))
        ->and($tabs(\App\Actions\UI\Grp\Layout\GetShopNavigation::run($this->shop, $clerk)))->toBe($tabs(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($clerk)))
        ->and(array_slice($tabs(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($clerk)), 0, 2))->toBe(['Board', 'Overview']);
});

test('the assignee edits status, priority, assignee, kind and module of their ticket from the list', function () {
    setPermissionsTeamId($this->group->id);
    $assignee  = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $assignee->assignRole('help-desk-clerk');
    $colleague->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Edit from the list', 'kind' => 'bug', 'assignee_id' => $assignee->id]);

    actingAs($assignee);
    get(route('grp.tickets.list'))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Tickets')
            ->where('updateRoute', 'grp.models.ticket.update')
            ->has('options.priorities')
            ->has('options.kinds')
            ->has('options.modules')
            ->where('options.assignees', fn ($assignees) => collect($assignees)->pluck('value')->contains($colleague->id))
    );

    $update = fn (array $data) => patch(route('grp.models.ticket.update', $ticket->id), $data)->assertRedirect()->assertSessionHasNoErrors();

    $update(['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $update(['priority' => 'urgent']);
    $update(['kind' => 'feature']);
    $update(['module' => 'dispatching']);
    $update(['status' => TicketStatusEnum::WAITING->value, 'question' => 'Which order?', 'waiting_hours' => 24]);
    $update(['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $update(['assignee_id' => $colleague->id]);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->priority->value)->toBe('urgent')
        ->and($ticket->kind?->value)->toBe('feature')
        ->and($ticket->module?->value)->toBe('dispatching')
        ->and($ticket->assignee_id)->toBe($colleague->id)
        ->and($ticket->comments()->where('body', 'Which order?')->exists())->toBeTrue();
});

test('ticket list sorts by creation, remembers the Mine filter and lets lead engineers edit any row', function () {
    $olderAssignedToMe = StoreTicket::make()->action($this->group, ['subject' => 'Created first, updated later', 'assignee_id' => $this->user->id]);
    $olderAssignedToMe->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $newest = StoreTicket::make()->action($this->group, ['subject' => 'Created last']);
    UpdateTicket::make()->action($olderAssignedToMe->fresh(), ['priority' => 'urgent']);

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Tickets')
            ->where('data.data.0.reference', $newest->reference)
            ->where('can_assign', true)
            ->where('mineFilter', null)
    );

    patch(route('grp.models.profile.update'), ['tickets_list_mine' => 'assigned'])->assertSessionHasNoErrors();
    expect($this->user->fresh()->settings['tickets_list_mine'] ?? null)->toBe('assigned');

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->where('mineFilter', 'assigned')
            ->where('data.data', fn ($rows) => collect($rows)->isNotEmpty() && collect($rows)->every(fn ($row) => $row['assignee_id'] === $this->user->id))
    );

    get(route('grp.tickets.list', ['elements' => ['mine' => '']]))->assertInertia(
        fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => collect($rows)->pluck('reference')->contains($newest->reference))
    );

    patch(route('grp.models.profile.update'), ['tickets_list_mine' => ''])->assertSessionHasNoErrors();

    setPermissionsTeamId($this->group->id);
    $someoneElse = User::factory()->create(['group_id' => $this->group->id]);
    $someoneElse->assignRole('help-desk-clerk');
    UpdateTicket::make()->action($newest->fresh(), ['assignee_id' => $someoneElse->id]);

    patch(route('grp.models.ticket.update', $newest->id), ['priority' => 'high'])->assertRedirect()->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.update', $newest->id), ['assignee_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect($newest->fresh()->priority->value)->toBe('high')
        ->and($newest->fresh()->assignee_id)->toBeNull();
});

test('the quick look controls include the comments the viewer can see', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Quick look comments']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'public note']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'internal note'])->update(['is_internal' => true]);

    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()
        ->assertJsonCount(2, 'comments')
        ->assertJsonPath('comments.0.body', 'internal note')
        ->assertJsonPath('comments_newest_first', (bool) data_get($this->user->fresh()->settings, 'ticket_comments_newest_first', true));

    setPermissionsTeamId($this->group->id);
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    actingAs($clerk);
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonCount(2, 'comments');

    $ticket->comments()->where('body', 'internal note')->update(['is_lead_only' => true]);
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonCount(1, 'comments')->assertJsonPath('comments.0.body', 'public note');
});

test('the board tells lead engineers apart so only they can drag any ticket', function () {
    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true)->where('can_assign', true));

    setPermissionsTeamId($this->group->id);
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    actingAs($clerk);

    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true)->where('can_assign', false));
});

test('reports stay unfiltered by default and narrow to one assignee when picked', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    StoreTicket::make()->action($this->group, ['subject' => 'Theirs', 'assignee_id' => $engineer->id]);
    StoreTicket::make()->action($this->group, ['subject' => 'Nobody on it']);

    $everyone = ShowTicketsReports::make()->handle($this->group, 'all');
    $theirs   = ShowTicketsReports::make()->handle($this->group, 'all', null, $engineer);

    expect($everyone['assignee'])->toBeNull()
        ->and($theirs['assignee'])->toBe($engineer->username)
        ->and($theirs['created'])->toBe(Ticket::where('group_id', $this->group->id)->where('assignee_id', $engineer->id)->count())
        ->and($everyone['created'])->toBeGreaterThan($theirs['created']);

    get(route('grp.tickets.reports', ['created' => 'all']))->assertInertia(
        fn (AssertableInertia $page) => $page->where('stats.assignee', null)
            ->where('assigneeOptions', fn ($options) => collect($options)->pluck('value')->contains($engineer->username))
    );
    get(route('grp.tickets.reports', ['created' => 'all', 'assignee' => $engineer->username]))->assertInertia(
        fn (AssertableInertia $page) => $page->where('stats.assignee', $engineer->username)->where('stats.created', $theirs['created'])
    );
    get(route('grp.tickets.reports', ['created' => 'all', 'assignee' => 'not-an-engineer']))->assertInertia(
        fn (AssertableInertia $page) => $page->where('stats.assignee', null)
    );
});

test('report totals, ratings and reporters link to matching ticket lists', function () {
    setPermissionsTeamId($this->group->id);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);

    $rated    = StoreTicket::make()->action($this->group, ['subject' => 'Rated one', 'reporter_type' => 'User', 'reporter_id' => $reporter->id, 'assignee_id' => $this->user->id]);
    $unrated  = StoreTicket::make()->action($this->group, ['subject' => 'Not rated', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    $someone  = StoreTicket::make()->action($this->group, ['subject' => 'Someone else', 'assignee_id' => $this->user->id]);
    $rated->forceFill(['rating' => 4, 'rated_at' => now()])->saveQuietly();

    $references = fn (array $filter) => collect(get(route('grp.tickets.list', ['filter' => $filter]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference');

    expect($references(['reporter' => 'User-'.$reporter->id])->sort()->values()->all())->toBe(collect([$rated->reference, $unrated->reference])->sort()->values()->all())
        ->and($references(['reporter' => 'User-'.$reporter->id, 'rated' => 1])->all())->toBe([$rated->reference])
        ->and($references(['has_assignee' => 1])->all())->toContain($rated->reference, $someone->reference)
        ->and($references(['has_assignee' => 1])->all())->not->toContain($unrated->reference)
        ->and($references(['reporter' => 'WebUser-'.$reporter->id])->all())->toBe([])
        ->and($references(['rated_month' => now()->format('Y-m')])->all())->toContain($rated->reference)
        ->and($references(['rated_month' => now()->subMonth()->format('Y-m')])->all())->not->toContain($rated->reference);

    $reporterRow = collect(ShowTicketsReports::make()->handle($this->group, 'all')['reporters'])->firstWhere('key', 'User-'.$reporter->id);
    expect($reporterRow)->toHaveKey('avatar')
        ->and($reporterRow['created'])->toBe(2);
});

test('engineer report rows split assigned and collaborating tickets and link to matching lists', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');

    $own     = StoreTicket::make()->action($this->group, ['subject' => 'Own work', 'assignee_id' => $engineer->id]);
    $helping = StoreTicket::make()->action($this->group, ['subject' => 'Helping out', 'assignee_id' => $this->user->id]);
    SyncTicketCollaborators::make()->action($helping, [$engineer->id]);

    $row = collect(ShowTicketsReports::make()->handle($this->group, 'all')['assignees'])->firstWhere('username', $engineer->username);
    expect($row['open'])->toBe(1)
        ->and($row['collaborating']['open'])->toBe(1)
        ->and($row['collaborating']['done'])->toBe(0);

    $references = fn (array $filter) => collect(get(route('grp.tickets.list', ['filter' => $filter]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference')->sort()->values()->all();

    expect($references(['assignee' => $engineer->username]))->toBe([$own->reference])
        ->and($references(['collaborator' => $engineer->username]))->toBe([$helping->reference])
        ->and($references(['involved' => $engineer->username]))->toBe(collect([$own->reference, $helping->reference])->sort()->values()->all())
        ->and(ShowTicketsReports::make()->handle($this->group, 'all', null, $engineer)['created'])->toBe(2);
});

test('the ticket list can be narrowed to the tickets someone collaborates on', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');

    $helping = StoreTicket::make()->action($this->group, ['subject' => 'Helping out', 'assignee_id' => $this->user->id]);
    StoreTicket::make()->action($this->group, ['subject' => 'Not involved', 'assignee_id' => $this->user->id]);
    SyncTicketCollaborators::make()->action($helping, [$engineer->id]);

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => count($rows) >= 2)
    );
    $response = get(route('grp.tickets.list', ['elements' => ['collaborator' => $engineer->username]]))->assertOk();
    expect(collect($response->viewData('page')['props']['data']['data'])->pluck('reference')->all())->toBe([$helping->reference]);
});

test('old AD references and padded numbers are still found by search', function () {
    $customerTicket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Padded customer ticket']);
    $legacy         = StoreTicket::make()->action($this->group, ['subject' => 'Raised before the rename']);
    $legacy->forceFill(['reference' => 'AD-1697'])->saveQuietly();

    $found = fn (string $search) => collect(get(route('grp.tickets.list', ['filter' => ['global' => $search]]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference')->all();

    $number = (int) Str::afterLast($customerTicket->reference, '-');

    expect($customerTicket->reference)->toMatch('/^CUS-\d{3,}$/')
        ->and($found('AD-1697'))->toContain('AD-1697')
        ->and($found('1697'))->toContain('AD-1697')
        ->and($found($customerTicket->reference))->toContain($customerTicket->reference)
        ->and($found((string) $number))->toContain($customerTicket->reference);
});

test('collaborators who lose their role drop off and tickets held by former staff can be taken over', function () {
    setPermissionsTeamId($this->group->id);
    $assignee    = User::factory()->create(['group_id' => $this->group->id]);
    $leaver      = User::factory()->create(['group_id' => $this->group->id]);
    $engineer    = User::factory()->create(['group_id' => $this->group->id]);
    $assignee->assignRole('help-desk-clerk');
    $leaver->assignRole('help-desk-clerk');
    $engineer->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Handover', 'assignee_id' => $assignee->id]);
    SyncTicketCollaborators::make()->action($ticket, [$leaver->id, $engineer->id]);
    expect($ticket->collaborators()->pluck('users.id')->sort()->values()->all())->toBe(collect([$leaver->id, $engineer->id])->sort()->values()->all());

    $leaver->removeRole('help-desk-clerk');
    $leaver->forgetWildcardPermissionIndex();

    actingAs($assignee);
    patch(route('grp.models.ticket.collaborators.update', $ticket->id), ['collaborator_ids' => [$leaver->id, $engineer->id]])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->collaborators()->pluck('users.id')->all())->toBe([$engineer->id]);

    $assignee->removeRole('help-desk-clerk');
    $assignee->forgetWildcardPermissionIndex();

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['assignee_id' => $engineer->id])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->assignee_id)->toBe($engineer->id);
});

test('the ticket list offers ownership by role and QA filters', function () {
    Notification::fake();
    $qa    = User::factory()->create(['group_id' => $this->group->id]);
    $staff = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');

    $forAnyone = StoreTicket::make()->action($this->group, ['subject' => 'List QA for anyone']);
    $forQa     = StoreTicket::make()->action($this->group, ['subject' => 'List QA for me']);
    $failed    = StoreTicket::make()->action($this->group, ['subject' => 'List QA failed']);
    $noQa      = StoreTicket::make()->action($this->group, ['subject' => 'List without QA', 'reporter_type' => 'User', 'reporter_id' => $qa->id]);
    UpdateTicket::make()->action($forAnyone, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'qa_status' => TicketQaStatusEnum::REQUESTED->value]);
    UpdateTicket::make()->action($forQa, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'qa_status' => TicketQaStatusEnum::REQUESTED->value, 'qa_user_id' => $qa->id]);
    $failed->forceFill(['qa_status' => TicketQaStatusEnum::FAILED, 'qa_user_id' => $qa->id])->saveQuietly();
    Ticket::whereIn('id', [$forAnyone->id, $forQa->id, $failed->id])->update(['status' => TicketStatusEnum::RESOLVED]);

    $references   = fn (array $elements) => collect(get(route('grp.tickets.list', ['elements' => $elements, 'perPage' => 500]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference');
    $qaReferences = fn (array $elements) => collect(get(route('grp.tickets.qa_list', ['elements' => ['qa_status' => '', ...$elements], 'perPage' => 500]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('reference');

    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->where('queryBuilderProps.default.elementGroups.mine.label', 'Ownership')
            ->has('queryBuilderProps.default.elementGroups.mine.elements.assigned')
            ->has('queryBuilderProps.default.elementGroups.mine.elements.unassigned')
            ->has('queryBuilderProps.default.elementGroups.qa_status')
            ->missing('queryBuilderProps.default.elementGroups.qa_checker')
    );

    actingAs($qa);
    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->missing('queryBuilderProps.default.elementGroups.mine.elements.assigned')
            ->has('queryBuilderProps.default.elementGroups.mine.elements.reported')
            ->where('queryBuilderProps.default.elementGroups.mine.optional', true)
            ->where('queryBuilderProps.default.elementGroups.status.optional', false)
            ->missing('queryBuilderProps.default.elementGroups.qa_checker')
    );

    actingAs($this->user);
    $unassignedTicket = StoreTicket::make()->action($this->group, ['subject' => 'List nobody on it']);
    $assignedTicket   = StoreTicket::make()->action($this->group, ['subject' => 'List someone on it', 'assignee_id' => $this->user->id]);
    expect($references(['mine' => 'unassigned'])->all())->toContain($unassignedTicket->reference)->not->toContain($assignedTicket->reference);

    actingAs($qa);
    expect($references(['mine' => 'reported'])->all())->toContain($noQa->reference)->not->toContain($forAnyone->reference)
        ->and($qaReferences(['qa_checker' => 'mine'])->all())->toContain($forQa->reference, $failed->reference)->not->toContain($forAnyone->reference, $noQa->reference)
        ->and($qaReferences(['qa_checker' => 'anyone'])->all())->toContain($forAnyone->reference)->not->toContain($forQa->reference, $noQa->reference)
        ->and($qaReferences(['qa_checker' => 'everyone'])->all())->toContain($forAnyone->reference, $forQa->reference, $failed->reference)->not->toContain($noQa->reference)
        ->and($references(['qa_status' => 'failed'])->all())->toContain($failed->reference, $forQa->reference)->not->toContain($noQa->reference)
        ->and($qaReferences(['qa_checker' => 'mine,anyone,everyone'])->all())->toContain($forAnyone->reference)->not->toContain($noQa->reference)
        ->and($references(['qa_status' => 'requested,failed,passed'])->all())->not->toContain($noQa->reference);

    patch(route('grp.models.profile.update'), ['tickets_list_mine' => 'reported'])->assertSessionHasNoErrors();
    expect($references([])->all())->toContain($noQa->reference)->not->toContain($forAnyone->reference)
        ->and($references(['mine' => ''])->all())->toContain($noQa->reference, $forAnyone->reference);

    actingAs($staff);
    get(route('grp.tickets.list'))->assertInertia(
        fn (AssertableInertia $page) => $page->missing('queryBuilderProps.default.elementGroups.qa_checker')
            ->has('queryBuilderProps.default.elementGroups.qa_status')
    );
    expect($references(['qa_status' => 'none'])->all())->toContain($forAnyone->reference, $forQa->reference)->not->toContain($failed->reference);

    actingAs($this->user);
});

test('a ticket list refetches one changed row, and a confidential ticket is not handed to someone who cannot see it', function () {
    $outsider = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $outsider->removeRole('group-admin');

    $open         = StoreTicket::make()->action($this->group, ['subject' => 'Row to refresh', 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);
    $confidential = StoreTicket::make()->action($this->group, ['subject' => 'HR matter', 'is_confidential' => true, 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);

    actingAs($this->user);
    get(route('grp.json.ticket.row', $open->id))->assertOk()
        ->assertJsonPath('id', $open->id)
        ->assertJsonPath('reference', $open->reference)
        ->assertJsonPath('subject', 'Row to refresh');

    actingAs($outsider);
    get(route('grp.json.ticket.row', $confidential->id))->assertForbidden();
});

test('jev fills in the kind and module nobody set, replaces a set one only when sure, and the reports chart tickets by module', function () {
    config(['services.openrouter.api_key' => 'test-key']);
    Http::fake(['openrouter.ai/api/alpha/decisions' => Http::sequence()
        ->push(['answers' => [
            'kind'   => ['choice' => 'feature', 'probabilities' => ['feature' => 0.8, 'bug' => 0.2]],
            'module' => ['choice' => 'procurement', 'probabilities' => ['procurement' => 0.9]],
        ]])
        ->push(['answers' => ['module' => ['choice' => 'crm', 'probabilities' => ['crm' => 0.3, 'chat' => 0.3]]]])
        ->push(['answers' => [
            'kind'   => ['choice' => 'aurora', 'probabilities' => ['aurora' => 0.7]],
            'module' => ['choice' => 'websites', 'probabilities' => ['websites' => 0.2]],
        ]])]);

    $classified = StoreTicket::make()->action($this->group, ['subject' => 'Add supplier lead times to purchase orders']);
    $unsure     = StoreTicket::make()->action($this->group, ['subject' => 'Something odd', 'kind' => TicketKindEnum::BUG->value]);
    $setByStaff = StoreTicket::make()->action($this->group, ['subject' => 'Chat lag', 'kind' => TicketKindEnum::BUG->value, 'module' => TicketModuleEnum::CHAT->value]);

    expect($classified->refresh()->kind)->toBe(TicketKindEnum::FEATURE)
        ->and($classified->module)->toBe(TicketModuleEnum::PROCUREMENT)
        ->and($unsure->refresh()->kind)->toBe(TicketKindEnum::BUG)
        ->and($unsure->module)->toBe(TicketModuleEnum::CRM)
        ->and($setByStaff->refresh()->module)->toBe(TicketModuleEnum::CHAT);
    Http::assertSentCount(2);

    $stats = ShowTicketsReports::make()->handle($this->group, '1w');
    $rows  = collect($stats['breakdown']);

    expect($rows->where(1, 'procurement')->where(2, 'feature')->sum(5))->toBeGreaterThanOrEqual(1)
        ->and($rows->where(1, 'crm')->where(3, 'open')->sum(5))->toBeGreaterThanOrEqual(1)
        ->and(collect($stats['modules'])->firstWhere('value', 'chat')['label'])->toBe('Chat')
        ->and(collect($stats['kinds'])->sum('total'))->toBe($stats['created'])
        ->and($rows->sum(5))->toBe($stats['created']);

    ClassifyTicket::make()->handle($setByStaff, reclassify: true);

    expect($setByStaff->refresh()->kind)->toBe(TicketKindEnum::AURORA)
        ->and($setByStaff->module)->toBe(TicketModuleEnum::CHAT);
});

test('the board loads ticket people, escalations and images up front, so more tickets add no queries', function () {
    $raise = function () {
        $parent = StoreTicket::make()->action($this->group, ['subject' => 'Board load '.Str::random(6), 'assignee_id' => $this->user->id]);
        $child  = StoreTicket::make()->action($this->group, ['subject' => 'Escalated '.Str::random(6), 'assignee_id' => $this->user->id]);
        $child->update(['model_type' => 'Ticket', 'model_id' => $parent->id]);
    };

    request()->setUserResolver(fn () => $this->user);

    $countBoardQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        \App\Actions\Helpers\Ticket\UI\ShowTicketsBoard::make()->handle($this->group);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $raise();
    $countBoardQueries();
    $queries = $countBoardQueries();

    foreach (range(1, 3) as $ticket) {
        StoreTicket::make()->action($this->group, ['subject' => 'Board load '.Str::random(6), 'assignee_id' => $this->user->id]);
    }

    expect($countBoardQueries())->toBe($queries);
});
