<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Support\Str;
use App\Actions\Helpers\Ticket\CloseTicketsAfterDeployment;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Helpers\Ticket\UpdateTicketDeployComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Helpers\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketNotification;
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
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

afterEach(function () {
    DB::statement('set time zone default');
});

test('board puts in-progress tickets held by QA under their own toggle, leaving deploying and closed tickets in place', function () {
    $tickets = collect(['working' => [TicketStatusEnum::IN_PROGRESS, null], 'requested' => [TicketStatusEnum::IN_PROGRESS, TicketQaStatusEnum::REQUESTED], 'checking' => [TicketStatusEnum::IN_PROGRESS, TicketQaStatusEnum::CHECKING], 'failed' => [TicketStatusEnum::IN_PROGRESS, TicketQaStatusEnum::FAILED], 'passed' => [TicketStatusEnum::IN_PROGRESS, TicketQaStatusEnum::PASSED], 'deploying' => [TicketStatusEnum::PENDING_DEPLOY, TicketQaStatusEnum::REQUESTED], 'done' => [TicketStatusEnum::RESOLVED, TicketQaStatusEnum::CHECKING]])
        ->map(function (array $state, string $name) {
            $ticket = StoreTicket::make()->action($this->group, ['subject' => 'QA board '.$name]);
            UpdateTicket::make()->action($ticket, ['status' => $state[0]->value]);
            $ticket->update(['qa_status' => $state[1]]);

            return $ticket->reference;
        });

    get(route('grp.tickets.board'))->assertInertia(function (AssertableInertia $page) use ($tickets) {
        $columns = collect($page->toArray()['props']['columns']);
        $boardStatuses = $columns->flatMap(fn (array $column) => $column['tickets'])->pluck('board_status', 'reference');
        $inProgress = $columns->firstWhere('key', 'in_progress');

        expect($tickets->map(fn (string $reference) => $boardStatuses[$reference])->all())->toBe([
            'working' => 'in_progress', 'requested' => 'qa', 'checking' => 'qa', 'failed' => 'qa', 'passed' => 'in_progress', 'deploying' => 'pending_deploy', 'done' => 'resolved',
        ])
            ->and(collect($inProgress['statuses'])->pluck('status')->all())->toBe(['in_progress', 'qa', 'pending_deploy'])
            ->and(collect($inProgress['tickets'])->pluck('reference'))->toContain($tickets['failed'])
            ->and(collect($columns->firstWhere('key', 'closed')['tickets'])->pluck('reference'))->toContain($tickets['done']);
    });
});

test('an engineer asks QA to check, QA answers with a verdict and the engineer still decides when it is done', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Totals wrong', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);

    actingAs($qa);
    get(route('grp.tickets.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false)->where('can_qa', true)->where('qa_queue', fn ($queue) => collect($queue)->doesntContain('reference', $ticket->reference)));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertForbidden();
    get(route('grp.tickets.create'))->assertOk();

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::REQUESTED)
        ->and($ticket->qa_requested_at)->not->toBeNull()
        ->and($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS);

    actingAs($qa);
    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('qa_queue', fn ($queue) => collect($queue)->contains('reference', $ticket->reference)));
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_qa', true)->where('ticket.qa_status', 'requested'));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'Still wrong with a voucher'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::FAILED)
        ->and($ticket->qa_user_id)->toBe($qa->id)
        ->and($ticket->qa_checked_at)->not->toBeNull()
        ->and($ticket->comments()->latest('id')->value('body'))->toBe('QA failed: Still wrong with a voucher');

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $reporter->id])->assertSessionHasErrors('qa_user_id');
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $qa->id, 'qa_note' => 'Try it with a voucher', 'images' => [UploadedFile::fake()->image('voucher.png')]])->assertRedirect();
    $requestComment = $ticket->comments()->latest('id')->first();
    expect($ticket->refresh()->qa_user_ids)->toBe([$qa->id])
        ->and($requestComment->body)->toBe('QA check requested: Try it with a voucher')
        ->and($requestComment->getMedia('ticket_images'))->toHaveCount(1);
    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertRedirect();
    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::PASSED)
        ->and($ticket->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'QA passed')->exists())->toBeTrue();
});

test('QA gives a verdict without being asked, can only skip a ticket nobody asked about, and needs a new request to change a verdict', function () {
    Notification::fake();
    $engineer     = User::factory()->create(['group_id' => $this->group->id]);
    $collaborator = User::factory()->create(['group_id' => $this->group->id]);
    $stranger     = User::factory()->create(['group_id' => $this->group->id]);
    $qa           = User::factory()->create(['group_id' => $this->group->id]);
    $mentioned    = User::factory()->create(['group_id' => $this->group->id, 'username' => 'qamention'.strtolower(Str::random(6)), 'status' => true]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $collaborator->assignRole('help-desk-clerk');
    $stranger->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Copy tweak']);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);
    $ticket->collaborators()->attach($collaborator->id);
    $update = route('grp.models.ticket.update', $ticket->id);

    actingAs($engineer);
    patch($update, ['qa_status' => 'skipped', 'qa_note' => 'Nothing to test'])->assertForbidden();

    actingAs($qa);
    patch($update, ['qa_status' => 'skipped'])->assertSessionHasErrors('qa_note');
    patch($update, ['qa_status' => 'skipped', 'qa_note' => 'Copy change only'])->assertSessionHasNoErrors();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::SKIPPED)
        ->and($ticket->qa_user_id)->toBe($qa->id)
        ->and($ticket->comments()->latest('id')->value('body'))->toBe('QA skipped: Copy change only');

    patch($update, ['qa_status' => 'passed'])->assertSessionHasErrors('qa_status');

    actingAs($stranger);
    patch($update, ['qa_status' => 'requested'])->assertForbidden();
    actingAs($collaborator);
    patch($update, ['qa_status' => 'requested'])->assertSessionHasNoErrors();

    actingAs($qa);
    patch($update, ['qa_status' => 'skipped', 'qa_note' => 'Not needed'])->assertSessionHasErrors('qa_status');
    post($update, [
        '_method'   => 'patch',
        'qa_status' => 'failed',
        'qa_note'   => "Still wrong, @{$mentioned->username} have a look",
        'images'    => [UploadedFile::fake()->image('still-wrong.png')],
    ])->assertSessionHasNoErrors();

    $verdict = $ticket->comments()->latest('id')->first();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::FAILED)
        ->and($verdict->getMedia('ticket_images'))->toHaveCount(1);
    Notification::assertSentTo($mentioned, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'mentioned you'));

    actingAs($engineer);
    patch($update, ['qa_status' => 'requested'])->assertSessionHasNoErrors();
    actingAs($qa);
    patch($update, ['qa_status' => 'skipped', 'qa_note' => 'Not needed'])->assertSessionHasErrors('qa_status');
    patch($update, ['qa_status' => 'passed'])->assertSessionHasNoErrors();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::PASSED);
});

test('a done note carries its files and notifies the people it mentions, but not the reporter a second time', function () {
    Notification::fake();
    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $mentioned = User::factory()->create(['group_id' => $this->group->id, 'username' => 'donemention'.strtolower(Str::random(6)), 'status' => true]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Totals off', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);

    actingAs($this->user);
    post(route('grp.models.ticket.update', $ticket->id), [
        '_method'        => 'patch',
        'status'         => 'resolved',
        'status_comment' => "Fixed the rounding, @{$mentioned->username} FYI",
        'images'         => [UploadedFile::fake()->image('after.png')],
    ])->assertSessionHasNoErrors();

    $note = $ticket->comments()->where('body', 'like', 'Fixed the rounding%')->sole();
    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($note->getMedia('ticket_images'))->toHaveCount(1);
    Notification::assertSentTo($mentioned, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'mentioned you'));
    Notification::assertSentTo($reporter, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'is done'));
    Notification::assertNotSentTo($reporter, TicketNotification::class, fn ($notification) => str_contains($notification->subject, 'new comment'));
});

test('the held deploy comment keeps its files out of the thread until the deployment posts it', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ship with a screenshot']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    actingAs($this->user);
    post(route('grp.models.ticket.update', $ticket->id), [
        '_method'  => 'patch',
        'status'   => 'pending_deploy',
        'question' => 'Live after the deploy',
        'images'   => [UploadedFile::fake()->image('fix.png')],
    ])->assertSessionHasNoErrors();

    $held = $ticket->deployComment()->sole();
    expect($held->getMedia('ticket_images'))->toHaveCount(1)
        ->and($ticket->comments()->count())->toBe(0)
        ->and($ticket->attachmentGalleryFor($this->user))->toBe([]);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('ticket.deploy_comment.body', 'Live after the deploy')->has('ticket.deploy_comment.files', 1));

    UpdateTicketDeployComment::make()->action($ticket->fresh(), [
        'body'         => 'Live after the deploy',
        'remove_media' => [$held->getFirstMedia('ticket_images')->ulid],
        'images'       => [UploadedFile::fake()->image('better.png')],
    ]);
    expect($held->fresh()->getMedia('ticket_images')->pluck('name')->all())->toBe(['better.png']);

    CloseTicketsAfterDeployment::run();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->deployComment()->exists())->toBeFalse()
        ->and($ticket->comments()->whereKey($held->id)->exists())->toBeTrue()
        ->and($ticket->attachmentGalleryFor($this->user))->toHaveCount(1);
});

test('QA has its own ticket list, filtered first by QA assignee, and the ticket list no longer offers that filter', function () {
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $mine   = StoreTicket::make()->action($this->group, ['subject' => 'Checked by me']);
    $anyone = StoreTicket::make()->action($this->group, ['subject' => 'Anyone in QA']);
    Ticket::whereKey($mine->id)->update(['qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => $qa->id, 'status' => TicketStatusEnum::RESOLVED]);
    Ticket::whereKey($anyone->id)->update(['qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => null, 'status' => TicketStatusEnum::RESOLVED]);

    $elementGroupKeys = fn (AssertableInertia $page) => array_keys($page->toArray()['props']['queryBuilderProps']['default']['elementGroups']);
    $references       = fn (AssertableInertia $page) => collect($page->toArray()['props']['data']['data'])->pluck('reference')->all();
    $subSections      = fn (User $user) => collect(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($user)['tickets']['topMenu']['subSections'])->pluck('label')->all();

    expect($subSections($qa))->toContain('Ticket List', 'QA List')
        ->and($subSections($engineer))->toContain('Ticket List')->not->toContain('QA List');

    actingAs($engineer);
    get(route('grp.tickets.qa_list'))->assertForbidden();
    get(route('grp.tickets.list'))->assertInertia(fn (AssertableInertia $page) => expect($elementGroupKeys($page))->toContain('mine')->not->toContain('qa_checker'));

    actingAs($qa);
    get(route('grp.tickets.qa_list'))->assertInertia(function (AssertableInertia $page) use ($elementGroupKeys) {
        $page->component('Tickets/Tickets')->where('title', 'QA List');
        expect($elementGroupKeys($page)[0])->toBe('qa_checker')
            ->and($elementGroupKeys($page))->not->toContain('mine');
    });
    get(route('grp.tickets.qa_list', ['elements' => ['qa_checker' => 'mine'], 'perPage' => 1000]))
        ->assertInertia(function (AssertableInertia $page) use ($references, $mine, $anyone, $qa) {
            expect($references($page))->toContain($mine->reference)->not->toContain($anyone->reference)
                ->and(collect($page->toArray()['props']['data']['data'])->firstWhere('reference', $mine->reference)['qa_user'])->toBe($qa->contact_name ?: $qa->username);
        });
    get(route('grp.tickets.qa_list', ['elements' => ['qa_checker' => 'anyone'], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => expect($references($page))->toContain($anyone->reference)->not->toContain($mine->reference));
    $untouched = StoreTicket::make()->action($this->group, ['subject' => 'Nobody looked yet']);
    $passed    = StoreTicket::make()->action($this->group, ['subject' => 'Already passed']);
    Ticket::whereKey($untouched->id)->update(['status' => TicketStatusEnum::RESOLVED]);
    Ticket::whereKey($passed->id)->update(['qa_status' => TicketQaStatusEnum::PASSED, 'qa_user_id' => $qa->id, 'status' => TicketStatusEnum::RESOLVED]);

    get(route('grp.tickets.qa_list', ['perPage' => 1000]))->assertInertia(function (AssertableInertia $page) use ($references, $untouched, $mine, $passed) {
        $page->where('listTip', fn ($tip) => str_contains($tip, 'no QA verdict'));
        expect($references($page))->toContain($untouched->reference, $mine->reference)->not->toContain($passed->reference)
            ->and(array_keys($page->toArray()['props']['queryBuilderProps']['default']['elementGroups']['qa_status']['elements']))->toBe(['none', 'passed', 'failed', 'skipped']);
    });
    get(route('grp.tickets.qa_list', ['elements' => ['qa_status' => 'passed'], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => expect($references($page))->toContain($passed->reference, $mine->reference, $anyone->reference)->not->toContain($untouched->reference));
    get(route('grp.tickets.qa_list', ['perPage' => 1000]))->assertInertia(function (AssertableInertia $page) {
        $isRequested = collect($page->toArray()['props']['data']['data'])->map(fn ($row) => $row['qa_status'] === 'requested')->values();
        $firstNotRequested = $isRequested->search(false);
        expect($firstNotRequested)->not->toBeFalse()
            ->and($isRequested->slice($firstNotRequested)->contains(true))->toBeFalse();
    });
    get(route('grp.tickets.qa_list', ['elements' => ['qa_status' => ''], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => expect($references($page))->toContain($passed->reference, $untouched->reference));

    get(route('grp.tickets.list', ['sort' => 'qa_status', 'perPage' => 1000]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('listTip', null)->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->firstWhere('key', 'qa_status')['sortable'] === true));
});

test('the QA list shows at a glance how far QA is behind on the tickets done', function () {
    $qa      = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    actingAs($qa);

    $summary = fn () => get(route('grp.tickets.qa_list'))->viewData('page')['props']['listSummary'];
    $before  = $summary();

    $references = [];
    foreach ([
        'not_checked'  => [TicketStatusEnum::RESOLVED, null],
        'passed'       => [TicketStatusEnum::RESOLVED, TicketQaStatusEnum::PASSED],
        'failed'       => [TicketStatusEnum::PENDING_DEPLOY, TicketQaStatusEnum::FAILED],
        'skipped'      => [TicketStatusEnum::RESOLVED, TicketQaStatusEnum::SKIPPED],
        'requested'    => [TicketStatusEnum::RESOLVED, TicketQaStatusEnum::REQUESTED],
        'checking'     => [TicketStatusEnum::PENDING_DEPLOY, TicketQaStatusEnum::CHECKING],
        'not_done_yet' => [TicketStatusEnum::IN_PROGRESS, TicketQaStatusEnum::CHECKING],
    ] as $key => [$status, $qaStatus]) {
        $ticket = StoreTicket::make()->action($this->group, ['subject' => 'QA summary '.$key]);
        Ticket::whereKey($ticket->id)->update(['status' => $status, 'qa_status' => $qaStatus, 'qa_user_id' => $key === 'passed' ? $otherQa->id : null]);
        $references[$key] = $ticket->reference;
    }

    $listedFor = fn (string $qaState) => collect(get(route('grp.tickets.qa_list', [
        'filter'   => ['qa_state' => $qaState],
        'elements' => ['qa_status' => '', 'qa_checker' => ''],
        'perPage'  => 1000,
    ]))->viewData('page')['props']['data']['data'])->pluck('reference')->intersect($references)->sort()->values()->all();

    expect($listedFor('passed'))->toBe([$references['passed']])
        ->and($listedFor('in_qa'))->toBe(collect([$references['requested'], $references['checking']])->sort()->values()->all())
        ->and($listedFor('not_checked'))->toBe([$references['not_checked']]);

    $after = $summary();

    expect(collect($after)->map(fn (int $count, string $key) => $count - $before[$key])->all())->toBe([
        'done'        => 6,
        'passed'      => 1,
        'failed'      => 1,
        'skipped'     => 1,
        'in_qa'       => 2,
        'not_checked' => 1,
    ]);

    get(route('grp.tickets.list'))->assertInertia(fn (AssertableInertia $page) => $page->where('listSummary', null));
});

test('the next page of the ticket and QA lists keeps the filters that were cleared', function () {
    $qa = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    actingAs($qa);

    foreach (range(1, 11) as $number) {
        $ticket = StoreTicket::make()->action($this->group, ['subject' => 'QA passed pagination '.$number]);
        Ticket::whereKey($ticket->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::PASSED]);
    }

    $firstPage = get(route('grp.tickets.qa_list', [
        'filter'   => ['qa_state' => 'passed'],
        'elements' => ['qa_status' => '', 'qa_checker' => ''],
        'perPage'  => 10,
    ]))->assertOk()->viewData('page')['props']['data'];

    $firstPageIds = collect($firstPage['data'])->pluck('id');

    $nextPageUrl = urldecode($firstPage['links']['next']);

    expect($nextPageUrl)->toContain('elements[qa_status]=', 'elements[qa_checker]=', 'filter[qa_state]=passed', 'page=2');

    $secondPage = get($firstPage['links']['next'])->assertOk()->viewData('page')['props']['data'];

    expect($secondPage['data'])->not->toBeEmpty()
        ->and(collect($secondPage['data'])->pluck('qa_status')->unique()->all())->toBe([TicketQaStatusEnum::PASSED->value])
        ->and(collect($secondPage['data'])->pluck('id')->intersect($firstPageIds)->all())->toBe([])
        ->and($secondPage['meta']['total'])->toBe($firstPage['meta']['total']);

    $ticketList = get(route('grp.tickets.list', ['elements' => ['mine' => '', 'status' => ''], 'perPage' => 10]))
        ->assertOk()->viewData('page')['props']['data'];

    expect(urldecode($ticketList['links']['next']))->toContain('elements[mine]=', 'elements[status]=', 'page=2');
});

test('a page past the last one of the ticket and QA lists goes back to the first page', function () {
    $qa = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    actingAs($qa);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'QA skipped out of range page']);
    Ticket::whereKey($ticket->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::SKIPPED]);

    foreach (['grp.tickets.list', 'grp.tickets.qa_list'] as $routeName) {
        $query = ['elements' => ['mine' => '', 'status' => '', 'qa_status' => 'skipped'], 'perPage' => 1000];

        $redirect = get(route($routeName, [...$query, 'page' => 7]))->assertRedirect();

        expect(urldecode($redirect->headers->get('Location')))
            ->toContain(route($routeName), 'elements[mine]=', 'elements[status]=', 'elements[qa_status]=skipped', 'page=1')
            ->not->toContain('page=7');

        get($redirect->headers->get('Location'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['data']['data'])->pluck('reference')->all())->toContain($ticket->reference));

        get(route($routeName, [...$query, 'filter' => ['global' => 'nothing-matches-this-'.uniqid()], 'page' => 1]))->assertOk();
    }
});

test('the dashboard shows QA details to QA, and the urgent check lands on requested tickets only', function () {
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $requested = StoreTicket::make()->action($this->group, ['subject' => 'Urgent check']);
    $passed    = StoreTicket::make()->action($this->group, ['subject' => 'Checked fine']);
    $hidden    = StoreTicket::make()->action($this->group, ['subject' => 'Not for QA eyes']);
    $stillOpen = StoreTicket::make()->action($this->group, ['subject' => 'Not finished yet']);
    $deploying = StoreTicket::make()->action($this->group, ['subject' => 'Waiting on a deploy']);
    Ticket::whereKey($deploying->id)->update(['status' => TicketStatusEnum::PENDING_DEPLOY]);
    Ticket::whereKey($requested->id)->update(['qa_status' => TicketQaStatusEnum::REQUESTED, 'status' => TicketStatusEnum::RESOLVED]);
    Ticket::whereKey($passed->id)->update(['qa_status' => TicketQaStatusEnum::PASSED, 'status' => TicketStatusEnum::RESOLVED]);
    Ticket::whereKey($hidden->id)->update(['qa_status' => TicketQaStatusEnum::REQUESTED, 'status' => TicketStatusEnum::RESOLVED, 'is_confidential' => true]);
    Ticket::whereKey($stillOpen->id)->update(['status' => TicketStatusEnum::IN_PROGRESS]);

    actingAs($engineer);
    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page->missing('qa_stats'));

    actingAs($qa);
    $visible = fn (): Builder => Ticket::where('group_id', $this->group->id)->visibleTo($qa);
    $done    = fn (): Builder => $visible()->whereIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::PENDING_DEPLOY]);

    expect($visible()->whereKey($hidden->id)->exists())->toBeFalse();

    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('qa_stats.requested', $visible()->where('qa_status', TicketQaStatusEnum::REQUESTED)->count())
        ->where('qa_stats.passed', $done()->where('qa_status', TicketQaStatusEnum::PASSED)->count())
        ->where('qa_stats.not_checked', $done()->whereNull('qa_status')->count()));

    expect($done()->whereKey($stillOpen->id)->exists())->toBeFalse();

    get(route('grp.tickets.qa_list', ['elements' => ['qa_status' => ''], 'filter' => ['qa_requested' => 1], 'perPage' => 1000]))
        ->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['data']['data'])->pluck('qa_status')->unique()->values()->all())->toBe(['requested']));

    $mine      = StoreTicket::make()->action($this->group, ['subject' => 'Asked of me']);
    $unclaimed = StoreTicket::make()->action($this->group, ['subject' => 'Asked of anyone']);
    $theirs    = StoreTicket::make()->action($this->group, ['subject' => 'Asked of another checker']);
    $otherQa   = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa->assignRole('qa');
    Ticket::whereKey($mine->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => $qa->id]);
    Ticket::whereKey($unclaimed->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => null]);
    Ticket::whereKey($theirs->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => $otherQa->id]);

    get(route('grp.tickets.qa_list', ['perPage' => 1000]))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($mine, $unclaimed, $theirs) {
            $ids = collect($page->toArray()['props']['data']['data'])->pluck('id');

            expect($ids)->toContain($mine->id)
                ->and($ids)->toContain($unclaimed->id)
                ->and($ids)->not->toContain($theirs->id);
        });

    actingAs($otherQa);
    get(route('grp.tickets.qa_list', ['perPage' => 1000]))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($mine, $theirs) {
            $ids = collect($page->toArray()['props']['data']['data'])->pluck('id');

            expect($ids)->toContain($theirs->id)
                ->and($ids)->not->toContain($mine->id);
        });

    actingAs($qa);
    get(route('grp.tickets.qa_list', ['perPage' => 1000]))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($stillOpen, $deploying) {
            $rows = collect($page->toArray()['props']['data']['data']);

            $verdicts = collect(TicketQaStatusEnum::cases())->filter->isVerdict()->map->value->all();

            expect($rows->where('qa_status', '!=', TicketQaStatusEnum::CHECKING->value)->pluck('status')->unique()->diff(['resolved', 'pending_deploy'])->all())->toBe([])
                ->and($rows->pluck('qa_status')->intersect($verdicts)->all())->toBe([])
                ->and($rows->pluck('id'))->toContain($deploying->id)
                ->and($rows->pluck('id'))->not->toContain($stillOpen->id);
        });
});

test('the QA queue can be narrowed to checks for anyone or for me', function () {
    Notification::fake();
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa  = User::factory()->create(['group_id' => $this->group->id]);
    $outsider = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    $otherQa->assignRole('qa');

    $forAnyone = StoreTicket::make()->action($this->group, ['subject' => 'Check for anyone']);
    $forMe     = StoreTicket::make()->action($this->group, ['subject' => 'Check for me']);
    $forOther  = StoreTicket::make()->action($this->group, ['subject' => 'Check for someone else']);
    $forBoth   = StoreTicket::make()->action($this->group, ['subject' => 'Check for both of us']);
    $inProgress = ['status' => TicketStatusEnum::IN_PROGRESS->value, 'qa_status' => TicketQaStatusEnum::REQUESTED->value];
    UpdateTicket::make()->action($forAnyone, $inProgress);
    UpdateTicket::make()->action($forMe, [...$inProgress, 'qa_user_id' => $qa->id]);
    UpdateTicket::make()->action($forOther, [...$inProgress, 'qa_user_id' => $otherQa->id]);
    UpdateTicket::make()->action($forBoth, [...$inProgress, 'qa_user_ids' => [$qa->id, $otherQa->id]]);

    actingAs($qa);
    $references = fn (string $checker) => collect(get(route('grp.json.ticket.qa_queue', ['checker' => $checker]))->assertOk()->json())->pluck('reference')->all();

    expect($references('all'))->toContain($forAnyone->reference, $forMe->reference, $forOther->reference, $forBoth->reference)
        ->and($references('anyone'))->toContain($forAnyone->reference)->not->toContain($forMe->reference, $forOther->reference, $forBoth->reference)
        ->and($references('me'))->toContain($forMe->reference, $forBoth->reference)->not->toContain($forAnyone->reference, $forOther->reference);

    $mine = collect(get(route('grp.json.ticket.qa_queue', ['checker' => 'me']))->json())->firstWhere('reference', $forMe->reference);
    expect($mine['qa_user_ids'])->toBe([$qa->id]);
    $both = collect(get(route('grp.json.ticket.qa_queue', ['checker' => 'me']))->json())->firstWhere('reference', $forBoth->reference);
    expect($both['qa_user'])->toContain(', ');

    expect($forBoth->refresh()->canBeClaimedForQaBy($qa))->toBeTrue()
        ->and($forBoth->canBeClaimedForQaBy($otherQa))->toBeTrue()
        ->and($forOther->refresh()->canBeClaimedForQaBy($qa))->toBeFalse();
    patch(route('grp.models.ticket.update', $forBoth->id), ['qa_status' => 'checking'])->assertSessionHasNoErrors();
    expect($forBoth->refresh()->qa_user_id)->toBe($qa->id)
        ->and($forBoth->isQaHeldByAnotherThan($otherQa))->toBeTrue();

    get(route('grp.json.ticket.qa_queue', ['checker' => 'nobody']))->assertSessionHasErrors('checker');

    actingAs($outsider);
    get(route('grp.json.ticket.qa_queue'))->assertForbidden();
    actingAs($this->user);
});

test('a failed QA verdict reopens a ticket that was already done, and every verdict marks its own comment', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Totals wrong again', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $qa->id])->assertRedirect();
    expect($ticket->refresh()->comments()->latest('id')->value('has_qa_verdict'))->toBeNull();

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertRedirect();
    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->not->toBeNull();

    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'The voucher total is still wrong'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->started_at)->not->toBeNull()
        ->and($ticket->assignee_id)->toBe($engineer->id)
        ->and($ticket->comments()->latest('id')->value('has_qa_verdict'))->toBe(TicketQaStatusEnum::FAILED);

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $qa->id])->assertRedirect();
    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->comments()->latest('id')->value('has_qa_verdict'))->toBe(TicketQaStatusEnum::PASSED);
});

test('a failed QA verdict leaves a ticket that is still being worked on exactly where it is', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Still in flight', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::WAITING->value]);

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'requested', 'qa_user_id' => $qa->id])->assertRedirect();

    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'Not there yet'])->assertRedirect();

    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::WAITING);
});

test('QA can fail a done ticket without reopening it by unticking Reopen ticket back', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Fail but keep done']);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::RESOLVED->value]);

    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'Minor, follow up separately', 'reopen' => false])->assertRedirect();

    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->qa_status)->toBe(TicketQaStatusEnum::FAILED);
});

test('any checker can pick up a failed or passed QA check again, whoever gave the verdict, but not a skipped one', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa  = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');
    $otherQa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Check me again']);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::RESOLVED->value]);

    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'Still broken'])->assertRedirect();

    actingAs($otherQa);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', true));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'checking'])->assertRedirect();

    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::CHECKING)
        ->and($ticket->qa_user_id)->toBe($otherQa->id);

    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::PASSED);

    actingAs($qa);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', true));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'checking'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::CHECKING)
        ->and($ticket->qa_user_id)->toBe($qa->id);

    $skipped = StoreTicket::make()->action($this->group, ['subject' => 'Nothing to test']);
    Ticket::whereKey($skipped->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::SKIPPED]);

    get(route('grp.tickets.show', $skipped->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', false));
    patch(route('grp.models.ticket.update', $skipped->id), ['qa_status' => 'checking'])->assertSessionHasErrors('qa_status');
});

test('a checker claims a ticket, it leaves every other checker\'s QA list, and only they can give its verdict', function () {
    Mail::fake();
    Notification::fake();
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa  = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');
    $otherQa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Claim me']);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $engineer->id, 'status' => TicketStatusEnum::RESOLVED->value]);

    $qaListIds = fn (array $query = []) => collect(get(route('grp.tickets.qa_list', ['perPage' => 1000, ...$query]))->assertOk()->inertiaProps()['data']['data'])->pluck('id');

    actingAs($engineer);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'checking'])->assertForbidden();

    actingAs($qa);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', true));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'checking'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->qa_status)->toBe(TicketQaStatusEnum::CHECKING)
        ->and($ticket->qa_user_id)->toBe($qa->id)
        ->and($ticket->qa_checked_at)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->latest('id')->value('has_qa_verdict'))->toBeNull()
        ->and($qaListIds())->toContain($ticket->id);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', false)->where('qa_held_by_another', false));

    actingAs($otherQa);
    expect($qaListIds())->not->toContain($ticket->id)
        ->and($qaListIds(['elements' => ['qa_checker' => 'everyone']]))->toContain($ticket->id);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', false)->where('qa_held_by_another', true));
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'checking'])->assertSessionHasErrors('qa_status');
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertSessionHasErrors('qa_status');
    expect($ticket->refresh()->qa_user_id)->toBe($qa->id);

    actingAs($qa);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'passed'])->assertRedirect();
    expect($ticket->refresh()->qa_status)->toBe(TicketQaStatusEnum::PASSED)
        ->and($ticket->qa_user_id)->toBe($qa->id)
        ->and($ticket->qa_checked_at)->not->toBeNull();

    $askedOfOther = StoreTicket::make()->action($this->group, ['subject' => 'Asked of the other checker']);
    Ticket::whereKey($askedOfOther->id)->update(['status' => TicketStatusEnum::RESOLVED, 'qa_status' => TicketQaStatusEnum::REQUESTED, 'qa_user_id' => $otherQa->id]);

    get(route('grp.tickets.show', $askedOfOther->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', false)->where('qa_held_by_another', true));
    patch(route('grp.models.ticket.update', $askedOfOther->id), ['qa_status' => 'checking'])->assertSessionHasErrors('qa_status');
    patch(route('grp.models.ticket.update', $askedOfOther->id), ['qa_status' => 'failed', 'qa_note' => 'Not mine to judge'])->assertSessionHasErrors('qa_status');
    expect($askedOfOther->refresh()->qa_status)->toBe(TicketQaStatusEnum::REQUESTED)
        ->and($askedOfOther->qa_user_id)->toBe($otherQa->id);

    actingAs($otherQa);
    get(route('grp.tickets.show', $askedOfOther->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_claim_qa', true)->where('qa_held_by_another', false));
});

test('the QA list filters by ticket status, keeps tickets being QA checked whatever their status, and the dashboard lists who is checking what', function () {
    $qa      = User::factory()->create(['group_id' => $this->group->id]);
    $otherQa = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $qa->assignRole('qa');
    $otherQa->assignRole('qa');

    $checkingOpen    = StoreTicket::make()->action($this->group, ['subject' => 'Checked while still in progress']);
    $checkingByOther = StoreTicket::make()->action($this->group, ['subject' => 'Another checker has it']);
    $openUnchecked   = StoreTicket::make()->action($this->group, ['subject' => 'Still in progress, not in QA']);
    $done            = StoreTicket::make()->action($this->group, ['subject' => 'Done, waiting for QA']);
    Ticket::whereKey($checkingOpen->id)->update(['status' => TicketStatusEnum::IN_PROGRESS, 'qa_status' => TicketQaStatusEnum::CHECKING, 'qa_user_id' => $qa->id]);
    Ticket::whereKey($checkingByOther->id)->update(['status' => TicketStatusEnum::ASSIGNED, 'qa_status' => TicketQaStatusEnum::CHECKING, 'qa_user_id' => $otherQa->id]);
    Ticket::whereKey($openUnchecked->id)->update(['status' => TicketStatusEnum::IN_PROGRESS]);
    Ticket::whereKey($done->id)->update(['status' => TicketStatusEnum::RESOLVED]);

    actingAs($qa);
    $qaListIds = fn (array $query = []) => collect(get(route('grp.tickets.qa_list', ['perPage' => 1000, ...$query]))->assertOk()->inertiaProps()['data']['data'])->pluck('id');

    get(route('grp.tickets.qa_list'))->assertInertia(function (AssertableInertia $page) {
        $elementGroups = $page->toArray()['props']['queryBuilderProps']['default']['elementGroups'];
        expect(array_slice(array_keys($elementGroups), 0, 3))->toBe(['qa_checker', 'status', 'qa_status'])
            ->and(array_keys($elementGroups['status']['elements']))->toContain('resolved', 'pending_deploy', 'in_progress', 'assigned');
    });
    expect($qaListIds())->toContain($checkingOpen->id, $done->id)->not->toContain($openUnchecked->id, $checkingByOther->id)
        ->and($qaListIds(['elements' => ['qa_checker' => 'everyone']]))->toContain($checkingByOther->id)
        ->and($qaListIds(['elements' => ['status' => 'in_progress', 'qa_status' => '']]))->toContain($checkingOpen->id)->not->toContain($openUnchecked->id, $done->id)
        ->and($qaListIds(['elements' => ['status' => 'resolved']]))->toContain($done->id)->not->toContain($checkingOpen->id);

    get(route('grp.tickets.list'))->assertInertia(function (AssertableInertia $page) {
        $keys = array_keys($page->toArray()['props']['queryBuilderProps']['default']['elementGroups']);
        expect($keys[array_search('status', $keys) + 1])->toBe('qa_status');
    });

    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => expect(collect($page->toArray()['props']['qa_checking'])->pluck('id')->all())
        ->toContain($checkingOpen->id, $checkingByOther->id)->not->toContain($done->id));
});
