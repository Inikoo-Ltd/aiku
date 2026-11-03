<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Ticket\CancelStaleTickets;
use App\Actions\Helpers\Ticket\CloseTicketsAfterDeployment;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Helpers\Ticket\UpdateTicketDeployComment;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use Illuminate\Support\Facades\DB;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Models\SysAdmin\User;
use App\Mcp\Tools\TicketWriteTool;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use App\Models\SysAdmin\Guest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
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

test('help ticket gets a HELP reference and defaults', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Printer on fire']);

    expect($ticket->reference)->toBe('HELP-'.$ticket->number)
        ->and($ticket->type)->toBe(TicketTypeEnum::HELP)
        ->and($ticket->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::NORMAL);

    $next = StoreTicket::make()->action($this->group, ['subject' => 'Second']);
    expect($next->number)->toBe($ticket->number + 1);

    return $ticket;
});

test('a premonition ticket gets a PRE reference with its own numbering', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Claim list repeats products', 'type' => TicketTypeEnum::PREMONITION->value]);
    $next   = StoreTicket::make()->action($this->group, ['subject' => 'Balance return on a cancelled order', 'type' => TicketTypeEnum::PREMONITION->value]);

    expect($ticket->type)->toBe(TicketTypeEnum::PREMONITION)
        ->and($ticket->reference)->toBe('PRE-'.str_pad((string) $ticket->number, 2, '0', STR_PAD_LEFT))
        ->and($next->number)->toBe($ticket->number + 1)
        ->and(TicketTypeEnum::PREMONITION->icon()['icon'])->toBe('fal fa-bowling-ball');

    $user = createAdminGuest($this->group)->getUser();
    foreach (['pre-'.$ticket->number, 'PRE'.$ticket->number, (string) $ticket->number] as $search) {
        $query = Ticket::query();
        \App\Actions\Helpers\Ticket\ApplyTicketSearch::run($query, $search, $user);
        expect($query->pluck('reference')->all())->toContain($ticket->reference);
    }
});

test('customer ticket from retina gets an AD reference and the customer attached', function () {
    $ticket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'API returns 500', 'priority' => 'high']);

    expect($ticket->reference)->toStartWith('CUS-')
        ->and($ticket->type)->toBe(TicketTypeEnum::CUSTOMER)
        ->and($ticket->customer_id)->toBe($this->customer->id)
        ->and($ticket->shop_id)->toBe($this->shop->id)
        ->and($ticket->reporter)->toBeInstanceOf($this->webUser::class)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::HIGH);

    return $ticket;
});

test('status changes stamp resolved and closed dates', function (Ticket $ticket) {
    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect($ticket->resolved_at)->not->toBeNull()->and($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CANCELLED->value]);
    expect($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::OPEN->value, 'assignee_id' => $this->user->id]);
    expect($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->assignee_id)->toBe($this->user->id);
})->depends('help ticket gets a HELP reference and defaults');

test('a ticket waiting longer than the grace period is cancelled, a fresh one is left alone', function () {
    $stale = StoreTicket::make()->action($this->group, ['subject' => 'Waited forever']);
    $fresh = StoreTicket::make()->action($this->group, ['subject' => 'Just asked']);
    $answered = StoreTicket::make()->action($this->group, ['subject' => 'Old but answered']);
    Ticket::whereIn('id', [$stale->id, $fresh->id, $answered->id])->update(['status' => TicketStatusEnum::WAITING]);
    Ticket::whereIn('id', [$stale->id, $answered->id])->update(['created_at' => now()->subDays(20), 'updated_at' => now()]);
    $answered->comments()->create(['body' => 'asked 20 days ago', 'created_at' => now()->subDays(20)]);
    $answered->comments()->create(['body' => 'here is the info', 'created_at' => now()->subDays(2)]);
    $stale->comments()->create(['body' => 'tagged it', 'created_at' => now()->subDays(20)]);

    expect(CancelStaleTickets::run(14))->toBe(1)
        ->and($stale->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($stale->fresh()->closed_at)->not->toBeNull()
        ->and($stale->comments()->count())->toBe(2)
        ->and($fresh->fresh()->status)->toBe(TicketStatusEnum::WAITING)
        ->and($answered->fresh()->status)->toBe(TicketStatusEnum::WAITING);

    $stale = $stale->fresh();
    expect($stale->isCancelledForNoReply())->toBeTrue();
    StoreTicketComment::make()->action($stale, $this->webUser, ['body' => 'sorry, was on holiday']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::ANSWERED)
        ->and($stale->fresh()->closed_at)->toBeNull();

    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::RESOLVED->value]);
    StoreTicketComment::make()->action($stale, $this->webUser, ['body' => 'thanks!']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::RESOLVED);

    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::CANCELLED->value]);
    expect($stale->fresh()->isCancelledForNoReply())->toBeFalse();
    StoreTicketComment::make()->action($stale->fresh(), $this->webUser, ['body' => 'thank you for checking']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::CANCELLED);

    UpdateTicket::make()->action($stale, ['status' => TicketStatusEnum::WAITING->value]);
    StoreTicketComment::make()->action($stale, $this->user, ['body' => 'any news?']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::WAITING);

    StoreTicketComment::make()->action($stale, $this->webUser, ['body' => 'here you go']);
    expect($stale->fresh()->status)->toBe(TicketStatusEnum::ANSWERED)
        ->and($stale->fresh()->waiting_until)->toBeNull()
        ->and($stale->fresh()->waiting_at->isAfter(now()->subMinute()))->toBeTrue();

    $board = get(route('grp.tickets.board'))->assertOk()->inertiaProps();
    $waiting = collect($board['columns'])->firstWhere('key', 'waiting');
    expect($waiting['label'])->toBe('Waiting')
        ->and(collect($waiting['statuses'])->pluck('count', 'status')->all())->toBe(['waiting' => 2, 'answered' => 1]);
});

test('staff and customers comment on the same public thread', function (Ticket $ticket) {
    $staffNote = StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Looking into it']);
    $customerNote = StoreTicketComment::make()->action($ticket, $this->webUser, ['body' => 'Any news?']);

    expect($staffNote->author_type)->toBe('User')
        ->and($customerNote->author_type)->toBe('WebUser')
        ->and($ticket->comments()->count())->toBe(2);
})->depends('customer ticket from retina gets an AD reference and the customer attached');

test('grp ticket pages render', function (Ticket $ticket) {
    get(route('grp.tickets.index'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsDashboard')->where('can_manage', true)->has('queue')->has('stats.open'));
    get(route('grp.tickets.list'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/Tickets')->has('data.data', Ticket::count())->where('searchHelp', \App\Actions\Helpers\Ticket\ApplyTicketSearch::HELP));
    get(route('grp.tickets.board'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsBoard')->has('columns', 5)->where('me', $this->user->username)->has('formerAssignees')->has('assignees'));
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    get(route('grp.tickets.create'))->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/CreateTicket'));
    actingAs($this->user);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->component('Tickets/Ticket')->where('ticket.reference', $ticket->reference)->has('comments', 2)->where('pageHead.wrapped_actions.0.key', 'delete')
            ->where('options.assignees', fn ($assignees) => collect($assignees)->pluck('value')->all() === GetTicketBadgeData::engineers($this->group->id)->sortBy(fn (User $user) => strtok((string) ($user->contact_name ?: $user->username), ' '))->pluck('id')->values()->all()
                && collect($assignees)->firstWhere('value', $this->user->id)['is_me'] === true)
            ->where('options.mentionable', fn ($mentionable) => collect($mentionable)->pluck('username')->contains($this->user->username))
    );
})->depends('customer ticket from retina gets an AD reference and the customer attached');

test('grp form endpoints create, update and comment', function () {
    $countBefore = Ticket::count();
    $reporter    = User::factory()->create(['group_id' => $this->group->id]);

    actingAs($reporter);
    post(route('grp.models.ticket.store'), ['subject' => 'From the form', 'priority' => 'urgent'])->assertRedirect();
    actingAs($this->user);
    $ticket = Ticket::latest('id')->first();
    expect(Ticket::count())->toBe($countBefore + 1)
        ->and($ticket->reporter_id)->toBe($reporter->id)
        ->and($ticket->priority)->toBe(ChatPriorityEnum::URGENT);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertRedirect();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'On it'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->comments()->count())->toBe(1);
});

test('reporter reopens their own done ticket into reporter replied, nobody else can', function () {
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Reporter reopen']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value, 'assignee_id' => $this->user->id]);

    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'answered', 'status_comment' => 'Let me in'])->assertForbidden();

    actingAs($reporter);
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'A reply does not reopen'])->assertRedirect();
    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::RESOLVED);

    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::CANCELLED->value]);
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'No problem, thank you for checking'])->assertRedirect();
    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::CANCELLED);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'answered'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'open', 'status_comment' => 'Still wrong'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'answered', 'status_comment' => 'Still wrong'])->assertRedirect()->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::ANSWERED)
        ->and($ticket->assignee_id)->toBe($this->user->id)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->comments()->where('body', 'Still wrong')->value('author_id'))->toBe($reporter->id);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'answered', 'status_comment' => 'Again'])->assertForbidden();
    actingAs($this->user);
});

test('reporter cancels their own ticket but cannot change anything else', function () {
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Reporter cancel']);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    actingAs($reporter);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'cancelled', 'priority' => 'urgent'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'cancelled', 'status_comment' => 'Not needed'])->assertRedirect();
    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::CANCELLED);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'cancelled'])->assertForbidden();

    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    $other = StoreTicket::make()->action($this->group, ['subject' => 'Not mine']);
    $other->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    patch(route('grp.models.ticket.update', $other->id), ['status' => 'cancelled'])->assertForbidden();
    actingAs($this->user);
});

test('a comment is translated into the reader language and cached', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Translate me']);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Olá Graciela'], false);

    $key = 'ticket-comment-translation:'.$comment->id.':'.$comment->updated_at?->timestamp.':'.$this->user->language_id;
    Cache::put($key, ['text' => 'Hello Graciela', 'language' => 'Portuguese']);

    post(route('grp.models.ticket.comment.translate', $comment->id))
        ->assertOk()
        ->assertJson(['text' => 'Hello Graciela', 'language' => 'Portuguese']);

    $ticket->update(['description' => 'Olá, o meu email não aparece']);
    $descriptionKey = 'ticket-description-translation:'.$ticket->id.':'.$ticket->refresh()->updated_at?->timestamp.':'.$this->user->language_id;
    Cache::put($descriptionKey, ['text' => 'Hello, my email does not show up', 'language' => 'Portuguese']);

    post(route('grp.models.ticket.translate', $ticket->id))
        ->assertOk()
        ->assertJson(['text' => 'Hello, my email does not show up']);
});

test('comment author edits and deletes their own comment', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Comment edit']);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'Typo'], false);

    patch(route('grp.models.ticket.comment.update', $comment->id), ['body' => 'Fixed'])->assertRedirect();
    expect($comment->refresh()->body)->toBe('Fixed');

    $admin = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    actingAs($admin);
    patch(route('grp.models.ticket.comment.update', $comment->id), ['body' => 'Hijacked'])->assertForbidden();
    delete(route('grp.models.ticket.comment.delete', $comment->id))->assertForbidden();
    actingAs($this->user);

    delete(route('grp.models.ticket.comment.delete', $comment->id))->assertRedirect();
    expect(TicketComment::find($comment->id))->toBeNull();
});

test('a lead engineer deletes someone else\'s comment and the history notes it', function () {
    $ticket   = StoreTicket::make()->action($this->group, ['subject' => 'Wrong name comment']);
    $poster   = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['contact_name' => 'Posting Guest']))->getUser();
    $bystander = StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser();
    $comment  = StoreTicketComment::make()->action($ticket, $poster, ['body' => 'Posted under the wrong account'], false);

    actingAs($bystander);
    delete(route('grp.models.ticket.comment.delete', $comment->id))->assertForbidden();

    actingAs($this->user);
    $thread = collect(get(route('grp.tickets.show', $ticket->reference))->inertiaProps()['comments']);
    expect($thread->firstWhere('id', $comment->id))->toMatchArray(['can_delete' => true, 'can_edit' => false]);

    delete(route('grp.models.ticket.comment.delete', $comment->id))->assertRedirect();
    expect(TicketComment::find($comment->id))->toBeNull();

    $timeline = collect(get(route('grp.tickets.show', $ticket->reference))->inertiaProps()['timeline']);
    expect($timeline->firstWhere('text', 'Removed a comment by '.$poster->contact_name))->not->toBeNull();
});

test('asking the reporter posts the question and cancels the ticket when its own deadline passes', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Need info']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'waiting', 'question' => 'Which order?', 'waiting_hours' => 2])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatusEnum::WAITING)
        ->and($ticket->waiting_until->between(now()->addMinutes(119), now()->addMinutes(121)))->toBeTrue()
        ->and($ticket->comments()->where('is_internal', false)->where('body', 'Which order?')->exists())->toBeTrue();

    expect(CancelStaleTickets::run())->toBe(0);

    $this->travel(3)->hours();
    expect(CancelStaleTickets::run())->toBe(1)
        ->and($ticket->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($ticket->fresh()->waiting_until)->toBeNull();
});

test('asking the reporter can attach a screenshot to the question', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Which upload']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    post(route('grp.models.ticket.update', $ticket->id), [
        '_method'       => 'patch',
        'status'        => 'waiting',
        'question'      => 'Is it this upload box?',
        'waiting_hours' => 72,
        'images'        => [UploadedFile::fake()->image('where.png')],
    ])->assertRedirect();

    $question = $ticket->comments()->where('body', 'Is it this upload box?')->first();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::WAITING)
        ->and($question)->not->toBeNull()
        ->and($question->getMedia('ticket_images'))->toHaveCount(1);
});

test('ticket page shows a history from opened to its status changes, newest first', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Timeline']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('timeline.0.text', 'Status: Todo → In progress')->where('timeline.1.text', 'Ticket opened')
    );
});

test('the reporter or a lead engineer edits the ticket subject, description and its own files, and the history says so', function () {
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $outsider = User::factory()->create(['group_id' => $this->group->id]);
    $ticket   = StoreTicket::make()->action($this->group, [
        'subject'     => 'Totals wrong',
        'description' => 'The invoice total is off',
        'images'      => [UploadedFile::fake()->image('old-shot.png'), UploadedFile::fake()->image('keep.png')],
    ]);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    $comment       = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [UploadedFile::fake()->image('comment.png')]]);
    $oldShot       = $ticket->getMedia('ticket_images')->first(fn ($media) => str_starts_with($media->name, 'old-shot'));
    $commentImage  = $comment->getMedia('ticket_images')->first();
    $contentRoute  = route('grp.models.ticket.content.update', $ticket->id);

    actingAs($outsider);
    patch($contentRoute, ['subject' => 'Hijacked'])->assertForbidden();

    actingAs($reporter);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_edit_content', true));
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonPath('can_edit_content', true);
    patch($contentRoute, [
        'subject'      => 'Invoice totals wrong with vouchers',
        'description'  => 'The invoice total is off when a voucher is used',
        'remove_media' => [$oldShot->ulid, $commentImage->ulid],
        'images'       => [UploadedFile::fake()->createWithContent('steps.pdf', "%PDF-1.4\n%%EOF\n")],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->subject)->toBe('Invoice totals wrong with vouchers')
        ->and($ticket->description)->toBe('The invoice total is off when a voucher is used')
        ->and($ticket->getMedia('ticket_images'))->toHaveCount(1)
        ->and($ticket->getMedia('ticket_images')->first()->name)->toStartWith('keep')
        ->and($ticket->getMedia('ticket_attachments'))->toHaveCount(1)
        ->and($comment->refresh()->getMedia('ticket_images'))->toHaveCount(1);

    actingAs($this->user);
    patch($contentRoute, ['description' => 'Edited by the lead engineer'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->refresh()->description)->toBe('Edited by the lead engineer');

    $timeline = collect(get(route('grp.tickets.show', $ticket->reference))->inertiaProps()['timeline']);
    expect($timeline->firstWhere('text', 'Description edited')['change'])->toBe(['label' => 'Description', 'from' => 'The invoice total is off when a voucher is used', 'to' => 'Edited by the lead engineer'])
        ->and($timeline->firstWhere('text', 'Subject edited')['change']['from'])->toBe('Totals wrong');

    $history = $timeline->pluck('text');
    expect($history)->toContain('Subject edited')
        ->and($history)->toContain('Description edited')
        ->and($history->first(fn ($text) => str_starts_with($text, 'Removed old-shot')))->toContain(' · Added steps');
});

test('staff file a bug from anywhere without leaving the page', function () {
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    $response = post(route('grp.models.ticket.store'), [
        'subject' => 'Button dead',
        'kind'    => 'bug',
        'stay'    => true,
        'reference_url' => 'https://app.aiku.test/org/awa/shops',
    ], ['referer' => 'https://app.aiku.test/org/awa/shops']);

    $ticket = Ticket::where('subject', 'Button dead')->first();
    $response->assertRedirect('https://app.aiku.test/org/awa/shops')->assertSessionHas('notification.title', $ticket->reference);
    expect($ticket->kind)->toBe(TicketKindEnum::BUG)
        ->and($ticket->type)->toBe(TicketTypeEnum::HELP)
        ->and($ticket->data['reference_url'])->toBe('https://app.aiku.test/org/awa/shops');

    actingAs($this->user);
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('ticket.reference_url', 'https://app.aiku.test/org/awa/shops')
    );
});

test('assigning and starting a ticket stamps its lifecycle dates', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Lifecycle']);
    expect($ticket->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->assigned_at)->toBeNull()
        ->and($ticket->started_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id]);
    expect($ticket->status)->toBe(TicketStatusEnum::ASSIGNED)
        ->and($ticket->assigned_at)->not->toBeNull()
        ->and($ticket->started_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $startedAt = $ticket->started_at;
    expect($startedAt)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::WAITING->value]);
    expect($ticket->started_at->eq($startedAt))->toBeTrue()
        ->and($ticket->closed_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value]);
    expect($ticket->resolved_at)->not->toBeNull()->and($ticket->closed_at)->not->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::OPEN->value]);
    expect($ticket->status)->toBe(TicketStatusEnum::ASSIGNED)
        ->and($ticket->started_at)->toBeNull()
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull();

    $ticket = UpdateTicket::make()->action($ticket, ['assignee_id' => null]);
    expect($ticket->status)->toBe(TicketStatusEnum::OPEN)
        ->and($ticket->assigned_at)->toBeNull();
});

test('only the assignee and supervisors change kind and module, and no ticket is turned into or out of an escalation', function () {
    setPermissionsTeamId($this->group->id);
    $clerk    = User::factory()->create(['group_id' => $this->group->id]);
    $assignee = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    $assignee->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Kind rules', 'kind' => 'bug', 'assignee_id' => $assignee->id]);

    actingAs($clerk);
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'feature'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['module' => 'dispatching'])->assertForbidden();
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonPath('can_change_kind_module', false);

    actingAs($assignee);
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'feature', 'module' => 'dispatching'])->assertRedirect()->assertSessionHasNoErrors();
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()
        ->assertJsonPath('can_change_kind_module', true)
        ->assertJsonPath('ticket.reference', $ticket->reference)
        ->assertJsonStructure(['ticket', 'options' => ['kinds', 'modules', 'assignees'], 'can_manage', 'can_assign', 'routes' => ['update']]);

    actingAs($this->user);
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'bug'])->assertRedirect()->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.update', $ticket->id), ['kind' => 'escalation'])->assertSessionHasErrors('kind');
    expect($ticket->fresh()->kind?->value)->toBe('bug')
        ->and($ticket->fresh()->module?->value)->toBe('dispatching');

    $escalated = StoreTicket::make()->action($this->group, ['subject' => 'From a customer', 'kind' => 'escalation']);
    patch(route('grp.models.ticket.update', $escalated->id), ['kind' => 'bug'])->assertSessionHasErrors('kind');
    patch(route('grp.models.ticket.update', $escalated->id), ['kind' => 'escalation'])->assertSessionHasNoErrors();
    expect(fn () => UpdateTicket::make()->action($escalated->fresh(), ['kind' => 'feature']))->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($escalated->fresh()->kind?->value)->toBe('escalation');

    $ticket->update(['is_confidential' => true]);
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    get(route('grp.json.ticket.controls', $ticket->id))->assertForbidden();
});

test('ticket comment and history order are saved per user and come back on the ticket page', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Order settings']);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_newest_first', true)->where('history_newest_first', true)
    );

    patch(route('grp.models.profile.update'), ['ticket_comments_newest_first' => false])->assertSessionHasNoErrors();
    patch(route('grp.models.profile.update'), ['ticket_history_newest_first' => false])->assertSessionHasNoErrors();

    expect($this->user->fresh()->settings)->toMatchArray(['ticket_comments_newest_first' => false, 'ticket_history_newest_first' => false]);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments_newest_first', false)->where('history_newest_first', false)
    );
});

test('internal notes are visible to any staff member including the reporter, lead only notes stay with leads and customers see neither', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Visibility']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'public reply']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'internal note'])->update(['is_internal' => true]);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'lead note'])->update(['is_internal' => true, 'is_lead_only' => true]);

    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $ticket->update(['reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    expect($ticket->commentsVisibleTo($reporter)->pluck('body')->sort()->values()->all())->toBe(['internal note', 'public reply'])
        ->and($ticket->commentsVisibleTo($this->user)->count())->toBe(3)
        ->and($ticket->commentsVisibleTo($this->webUser)->pluck('body')->all())->toBe(['public reply']);
});

test('done and cancel publish the closing comment together with the status change', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Close with a note']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved', 'status_comment' => 'Fixed the rounding in the totals'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'Fixed the rounding in the totals')->where('is_internal', false)->value('author_id'))->toBe($this->user->id);

    $duplicate = StoreTicket::make()->action($this->group, ['subject' => 'Cancel with a note']);
    patch(route('grp.models.ticket.update', $duplicate->id), ['status' => 'cancelled', 'status_comment' => 'Duplicate of another ticket'])->assertRedirect()->assertSessionHasNoErrors();
    expect($duplicate->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($duplicate->comments()->where('body', 'Duplicate of another ticket')->exists())->toBeTrue();

    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    actingAs($reporter);
    $own = StoreTicket::make()->action($this->group, ['subject' => 'Mine to close', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    patch(route('grp.models.ticket.update', $own->id), ['priority' => 'urgent', 'status_comment' => 'sneaky'])->assertForbidden();
    patch(route('grp.models.ticket.update', $own->id), ['status' => 'cancelled', 'status_comment' => 'Not needed any more'])->assertRedirect()->assertSessionHasNoErrors();
    expect($own->fresh()->status)->toBe(TicketStatusEnum::CANCELLED)
        ->and($own->comments()->where('body', 'Not needed any more')->exists())->toBeTrue()
        ->and($own->comments()->where('body', 'sneaky')->exists())->toBeFalse();
});

test('the ticket write tool closes after next deployment and holds the comment until then', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Ship it by MCP']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'pending_deploy', 'comment' => 'Fixed, live after the deploy'])->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::PENDING_DEPLOY)
        ->and($ticket->comments()->count())->toBe(0)
        ->and($ticket->deployComment()->sole()->author_id)->toBe($this->user->id);

    get(route('grp.tickets.show', $ticket->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('ticket.deploy_comment.body', 'Fixed, live after the deploy'));

    CloseTicketsAfterDeployment::run();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'Fixed, live after the deploy')->sole()->author_id)->toBe($this->user->id);
});

test('a deployment only closes a pending deploy ticket when it includes the fix commit', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Pack weight']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'pending_deploy', 'commit' => 'F194AFF213', 'comment' => 'Live now'])->assertOk();

    expect(data_get($ticket->fresh()->data, 'deploy_commit'))->toBe('f194aff213');

    CloseTicketsAfterDeployment::run('910ba60751');

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::PENDING_DEPLOY)
        ->and($ticket->comments()->count())->toBe(0);

    CloseTicketsAfterDeployment::run('5f525f2033');

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'Live now')->count())->toBe(1)
        ->and(data_get($ticket->fresh()->data, 'deploy_commit'))->toBeNull();
});

test('the web ticket form records the fix commit when it sets the ticket to close on deployment', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Commit from the dialog']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    actingAs($this->user);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'pending_deploy', 'question' => 'Live now', 'deploy_commit' => 'not a hash'])->assertSessionHasErrors('deploy_commit');
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'pending_deploy', 'question' => 'Live now', 'deploy_commit' => ' F194AFF213 '])->assertSessionHasNoErrors();

    expect(data_get($ticket->fresh()->data, 'deploy_commit'))->toBe('f194aff213')
        ->and($ticket->deployComment()->sole()->body)->toBe('Live now');

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'pending_deploy', 'question' => 'Live now'])->assertSessionHasNoErrors();

    expect(data_get($ticket->fresh()->data, 'deploy_commit'))->toBeNull();
});

test('the held deploy comment can be rewritten while the ticket waits for the deployment', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Edit the held comment']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'pending_deploy', 'comment' => 'First wording'])->assertOk();

    actingAs($this->user);
    UpdateTicketDeployComment::make()->action($ticket->fresh(), ['body' => '  Second wording  ']);

    expect($ticket->deployComment()->sole()->body)->toBe('Second wording')
        ->and($ticket->comments()->count())->toBe(0);

    CloseTicketsAfterDeployment::run();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->where('body', 'Second wording')->count())->toBe(1)
        ->and($ticket->comments()->where('body', 'First wording')->count())->toBe(0);
});

test('clearing the held deploy comment closes the ticket on deployment without posting anything', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Drop the held comment']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'pending_deploy', 'comment' => 'Not wanted after all'])->assertOk();

    actingAs($this->user);
    UpdateTicketDeployComment::make()->action($ticket->fresh(), ['body' => '']);

    expect($ticket->deployComment()->exists())->toBeFalse();

    CloseTicketsAfterDeployment::run();

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED)
        ->and($ticket->comments()->count())->toBe(0);
});

test('the held deploy comment cannot be rewritten once the ticket has left pending deploy', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Too late to edit']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::RESOLVED->value, 'assignee_id' => $this->user->id]);

    actingAs($this->user);

    expect(fn () => UpdateTicketDeployComment::make()->action($ticket->fresh(), ['body' => 'nope']))
        ->toThrow(HttpException::class);
});

test('a ticket left in todo with an assignee can still be started and closed', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Reopened and stuck', 'assignee_id' => $this->user->id]);
    $ticket->forceFill([
        'status'      => TicketStatusEnum::OPEN->value,
        'resolved_at' => now()->subDay(),
        'closed_at'   => now()->subDay(),
    ])->saveQuietly();

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'in_progress'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::IN_PROGRESS)
        ->and($ticket->fresh()->closed_at)->toBeNull()
        ->and($ticket->fresh()->resolved_at)->toBeNull();

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved', 'status_comment' => 'Done at last'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED);

    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'open'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::ASSIGNED)
        ->and($ticket->fresh()->assignee_id)->toBe($this->user->id);

    UpdateTicket::make()->action($ticket->fresh(), ['assignee_id' => null]);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved', 'status_comment' => 'Nobody on it'])->assertRedirect()->assertSessionHasNoErrors();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'open'])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::OPEN);
});

test('the assignee cancels their own ticket, and the reporter still can too', function () {
    setPermissionsTeamId($this->group->id);
    $assignee  = User::factory()->create(['group_id' => $this->group->id]);
    $bystander = User::factory()->create(['group_id' => $this->group->id]);
    $assignee->assignRole('help-desk-clerk');
    $bystander->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'     => 'Cancel me',
        'assignee_id' => $assignee->id,
    ]);

    // an engineer who is neither the assignee nor the reporter is still kept out
    actingAs($bystander);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'cancelled', 'status_comment' => 'not mine'])->assertForbidden();

    actingAs($assignee);
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'cancelled', 'status_comment' => 'duplicate'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($ticket->refresh()->status)->toBe(TicketStatusEnum::CANCELLED);

    // the reporter's own cancel, which this branch was written for, keeps working: a staff
    // member with no help desk role at all, cancelling what they raised
    $reporter = User::factory()->create(['group_id' => $this->group->id]);

    actingAs($reporter);
    $reporterTicket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Mine to cancel',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
    ]);

    patch(route('grp.models.ticket.update', $reporterTicket->id), ['status' => 'cancelled', 'status_comment' => 'sorted itself out'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($reporterTicket->refresh()->status)->toBe(TicketStatusEnum::CANCELLED);
});

test('an engineer writes an incident post-mortem on a ticket and customers cannot', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Side basket empty']);
    UpdateTicket::make()->action($ticket, ['assignee_id' => $this->user->id, 'status' => TicketStatusEnum::IN_PROGRESS->value]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'Root cause: null charge', 'post_mortem' => true])->assertOk()->assertSee('"commented":true');

    $postMortem = $ticket->comments()->latest('id')->first();
    expect($postMortem->type)->toBe(\App\Enums\Helpers\Ticket\TicketCommentTypeEnum::POST_MORTEM)
        ->and(\App\Http\Resources\Helpers\TicketCommentResource::make($postMortem)->resolve()['type'])->toBe('post_mortem')
        ->and(StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'plain'], false)->type)->toBe(\App\Enums\Helpers\Ticket\TicketCommentTypeEnum::COMMENT);

    expect(fn () => StoreTicketComment::make()->action($ticket, $this->webUser, ['body' => 'mine', 'type' => 'post_mortem'], false))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

test('a deployment closes a pending deploy ticket whose fix commit was rewritten by a rebase once a commit naming it is deployed', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Rebased fix']);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value, 'assignee_id' => $this->user->id]);

    AikuServer::actingAs($this->user)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'pending_deploy', 'commit' => 'deadbeef00', 'comment' => 'Live now'])->assertOk();

    CloseTicketsAfterDeployment::run('5f525f2033');

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::PENDING_DEPLOY);

    $ticket->update(['data' => array_merge($ticket->fresh()->data, ['commits' => [['hash' => '5f525f2033']]])]);

    CloseTicketsAfterDeployment::run('5f525f2033');

    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::RESOLVED);
});
