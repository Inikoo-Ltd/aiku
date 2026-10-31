<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Helpers\Ticket\UpdateTicket;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use Illuminate\Support\Facades\DB;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Models\SysAdmin\User;
use App\Mcp\Tools\TicketsTool;
use App\Mcp\Tools\TicketWriteTool;
use App\Http\Resources\Helpers\TicketResource;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketNotification;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use Inertia\Testing\AssertableInertia;
use App\Enums\SysAdmin\Authorisation\RolesEnum;

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

test('tickets take free tags and the known list grows with them', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Tagged', 'tags' => ['not a bug'], 'module' => 'dispatching']);
    UpdateTicket::make()->action($ticket, ['tags' => ['not a bug', 'printer voodoo']]);

    expect($ticket->fresh()->tags)->toBe(['not a bug', 'printer voodoo'])
        ->and($ticket->module)->toBe(TicketModuleEnum::DISPATCHING)
        ->and(Ticket::knownTags($this->group->id))->toContain('printer voodoo', 'lack of training');
});

test('confidential tickets are only visible to reporter, assignee and lead engineers', function () {
    $outsider = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $outsider->removeRole('group-admin');
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'HR matter', 'is_confidential' => true, 'reporter_type' => 'User', 'reporter_id' => $this->user->id]);

    expect(Ticket::visibleTo($this->user)->whereKey($ticket->id)->exists())->toBeTrue()
        ->and(Ticket::visibleTo($outsider)->whereKey($ticket->id)->exists())->toBeFalse();

    actingAs($outsider);
    get(route('grp.tickets.show', $ticket->reference))->assertForbidden();
    get(route('grp.tickets.list', ['elements' => ['mine' => 'reported']]))->assertOk();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'peek'])->assertForbidden();
    get(route('grp.tickets.reports'))->assertInertia(fn (AssertableInertia $page) => $page->where('stats.open', Ticket::visibleTo($outsider)->whereNotIn('status', ['resolved', 'cancelled'])->count()));

    UpdateTicket::make()->action($ticket, ['assignee_id' => $outsider->id]);
    expect(Ticket::visibleTo($outsider)->whereKey($ticket->id)->exists())->toBeTrue();
    post(route('grp.models.ticket.comment.store', $ticket->id), ['body' => 'on it'])->assertRedirect();
});

test('only the help desk manages tickets, everyone else reports, comments and closes their own', function () {
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $helper   = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $helper->assignRole('help-desk-clerk');
    $boss = User::factory()->create(['group_id' => $this->group->id]);
    $boss->assignRole('help-desk-supervisor');

    actingAs($reporter);
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Mine', 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);
    $other  = StoreTicket::make()->action($this->group, ['subject' => 'Not mine']);

    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false)->where('is_reporter', true));
    get(route('grp.tickets.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Tickets/TicketsDashboard')->where('can_manage', false)->has('mine', 1)->missing('queue'));
    get(route('grp.tickets.reports'))->assertOk();
    get(route('grp.tickets.board'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false));
    patch(route('grp.models.ticket.update', $other->id), ['status' => 'resolved'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['priority' => 'urgent'])->assertForbidden();
    patch(route('grp.models.ticket.update', $ticket->id), ['status' => 'resolved'])->assertForbidden();
    post(route('grp.models.ticket.comment.store', $other->id), ['body' => 'secret?'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(TicketStatusEnum::OPEN)
        ->and($other->comments()->sole()->body)->toBe('secret?');

    $manager = User::factory()->create(['group_id' => $this->group->id]);
    $manager->assignRole('group-admin');
    actingAs($manager);
    get(route('grp.tickets.show', $other->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false));
    get(route('grp.tickets.board'))->assertOk();
    patch(route('grp.models.ticket.update', $other->id), ['status' => 'resolved'])->assertForbidden();

    actingAs($helper);
    get(route('grp.tickets.create'))->assertOk();
    post(route('grp.models.ticket.store'), ['subject' => 'Engineers report too'])->assertRedirect();
    get(route('grp.tickets.show', $other->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true));
    get(route('grp.tickets.reports'))->assertOk();
    get(route('grp.tickets.board'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', true));
    patch(route('grp.models.ticket.update', $other->id), ['is_confidential' => true])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['priority' => 'urgent'])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $helper->id])->assertRedirect()->assertSessionHasNoErrors();
    expect($other->fresh()->assignee_id)->toBe($helper->id);
    patch(route('grp.models.ticket.update', $other->id), ['priority' => 'urgent'])->assertRedirect();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => null])->assertForbidden();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id])->assertRedirect();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $helper->id])->assertForbidden();
    UpdateTicket::make()->action($other->refresh(), ['assignee_id' => $helper->id]);
    post(route('grp.models.ticket.comment.store', $other->id), ['body' => 'from the page'])->assertRedirect();
    expect($other->fresh()->priority)->toBe(ChatPriorityEnum::URGENT)
        ->and($other->fresh()->assignee_id)->toBe($helper->id)
        ->and($other->comments()->where('body', 'from the page')->count())->toBe(1)
        ->and($other->comments()->where('body', 'like', 'Passed from%')->count())->toBe(0);

    actingAs($boss);
    get(route('grp.tickets.reports'))->assertOk();
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id])->assertRedirect();
    expect($other->fresh()->assignee_id)->toBe($boss->id);

    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();
    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $ticket->reference, 'status' => 'cancelled'])->assertHasErrors();
    AikuServer::actingAs($reporter)->tool(TicketsTool::class, ['reference' => $ticket->reference])->assertHasErrors();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $reporter->username])->assertHasErrors();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $helper->username])->assertOk();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'assignee' => $boss->username])->assertOk();
    AikuServer::actingAs($reporter)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'comment' => 'reporters use the page'])->assertHasErrors();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'comment' => 'not my ticket any more'])->assertOk();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'comment' => 'on it'])->assertOk();
    $unassigned = StoreTicket::make()->action($this->group, ['subject' => 'Nobody owns me']);
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $unassigned->reference, 'comment' => 'too early'])->assertHasErrors();
    expect($other->comments()->where('body', 'on it')->value('author_id'))->toBe($boss->id)
        ->and($other->comments()->where('body', 'not my ticket any more')->value('author_id'))->toBe($helper->id)
        ->and($other->comments()->where('body', 'reporters use the page')->exists())->toBeFalse()
        ->and($unassigned->comments()->exists())->toBeFalse();

    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'acting_as' => $helper->username, 'comment' => 'said at the desk'])->assertOk();
    AikuServer::actingAs($boss)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'acting_as' => 'nobody-here', 'comment' => 'ghost'])->assertHasErrors();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'acting_as' => $boss->username, 'comment' => 'impersonating the boss'])->assertHasErrors();
    expect($other->comments()->where('body', 'said at the desk')->value('author_id'))->toBe($helper->id)
        ->and($other->comments()->whereIn('body', ['ghost', 'impersonating the boss'])->exists())->toBeFalse();

    $oldNote = $other->comments()->create(['body' => 'old internal note', 'is_internal' => true]);
    expect($other->commentsVisibleTo($helper)->whereKey($oldNote->id)->exists())->toBeTrue()
        ->and($other->commentsVisibleTo($reporter)->whereKey($oldNote->id)->exists())->toBeTrue()
        ->and($other->commentsVisibleTo($boss)->whereKey($oldNote->id)->exists())->toBeTrue();
    actingAs($helper);
    patch(route('grp.models.ticket.comment.toggle_visibility', $oldNote->id))->assertForbidden();
    actingAs($boss);
    patch(route('grp.models.ticket.comment.toggle_visibility', $oldNote->id))->assertRedirect();
    expect($oldNote->fresh()->is_lead_only)->toBeTrue()
        ->and($other->commentsVisibleTo($helper)->whereKey($oldNote->id)->exists())->toBeFalse()
        ->and($other->commentsVisibleTo($boss)->whereKey($oldNote->id)->exists())->toBeTrue();
    patch(route('grp.models.ticket.comment.toggle_visibility', $oldNote->id))->assertRedirect();
    expect($oldNote->fresh()->is_lead_only)->toBeFalse();

    actingAs($helper);
    delete(route('grp.models.ticket.delete', $other->id))->assertForbidden();
    actingAs($boss);
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $boss->id, 'is_confidential' => true])->assertRedirect();
    expect($other->fresh()->is_confidential)->toBeTrue();
    AikuServer::actingAs($helper)->tool(TicketWriteTool::class, ['reference' => $other->reference, 'priority' => 'low'])->assertHasErrors();

    actingAs($boss);
    delete(route('grp.models.ticket.delete', $other->id))->assertRedirect(route('grp.tickets.index'));
    expect(Ticket::find($other->id))->toBeNull()
        ->and(Ticket::withTrashed()->find($other->id))->not->toBeNull();
});

test('the assignee adds collaborators who can see the ticket, tag it and ask QA, while only the assignee moves it', function () {
    Mail::fake();
    Notification::fake();
    setPermissionsTeamId($this->group->id);
    $assignee  = User::factory()->create(['group_id' => $this->group->id]);
    $helper    = User::factory()->create(['group_id' => $this->group->id]);
    $qa        = User::factory()->create(['group_id' => $this->group->id]);
    $bystander = User::factory()->create(['group_id' => $this->group->id]);
    $outsider  = User::factory()->create(['group_id' => $this->group->id]);
    $assignee->assignRole('help-desk-clerk');
    $helper->assignRole('help-desk-clerk');
    $bystander->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Needs three people', 'assignee_id' => $assignee->id, 'is_confidential' => true]);
    UpdateTicket::make()->action($ticket, ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    $collaborators = route('grp.models.ticket.collaborators.update', $ticket->id);
    $update        = route('grp.models.ticket.update', $ticket->id);

    actingAs($bystander);
    expect($ticket->fresh()->isVisibleTo($bystander))->toBeFalse();
    patch($collaborators, ['collaborator_ids' => [$bystander->id]])->assertForbidden();
    patch($update, ['tags' => ['sneaky']])->assertForbidden();

    actingAs($assignee);
    patch($collaborators, ['collaborator_ids' => [$helper->id, $qa->id, $outsider->id]])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->collaborators()->pluck('users.id')->all())->not->toContain($outsider->id);
    patch($collaborators, ['collaborator_ids' => [$helper->id, $qa->id]])->assertRedirect()->assertSessionHasNoErrors();

    expect($ticket->collaborators()->pluck('users.id')->sort()->values()->all())->toBe(collect([$helper->id, $qa->id])->sort()->values()->all())
        ->and($ticket->collaborators()->whereKey($helper->id)->first()->pivot->added_by_id)->toBe($assignee->id);
    Notification::assertSentTo($helper, TicketNotification::class, fn ($notification) => str_contains($notification->subject, $ticket->reference));
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_manage_collaborators', true)
            ->where('timeline', fn ($timeline) => collect($timeline)->contains(fn ($event) => str_starts_with($event['text'], 'Collaborators:')))
    );

    actingAs($helper);
    expect($ticket->fresh()->isVisibleTo($helper))->toBeTrue();
    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_update', false)->where('can_contribute', true)->where('can_manage_collaborators', false)
    );
    patch($update, ['tags' => ['data fix']])->assertRedirect()->assertSessionHasNoErrors();
    patch($update, ['qa_status' => 'requested'])->assertRedirect()->assertSessionHasNoErrors();
    patch($update, ['status' => 'resolved'])->assertForbidden();
    patch($update, ['priority' => 'urgent'])->assertForbidden();
    patch($collaborators, ['collaborator_ids' => []])->assertForbidden();
    expect($ticket->fresh()->tags)->toContain('data fix')
        ->and(GetTicketBadgeData::run($helper)['queue']['collaborating']['count'])->toBeGreaterThanOrEqual(1);
    get(route('grp.tickets.list', ['elements' => ['mine' => 'collaborating']]))->assertInertia(
        fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => collect($rows)->pluck('reference')->contains($ticket->reference))
    );

    actingAs($assignee);
    patch($update, ['assignee_id' => $helper->id])->assertRedirect();
    expect($ticket->fresh()->assignee_id)->toBe($helper->id)
        ->and($ticket->collaborators()->pluck('users.id')->all())->toBe([$qa->id]);
});

test('reporters follow progress from their badge, cannot move their ticket, and engineering notes stay with staff', function () {
    Mail::fake();
    setPermissionsTeamId($this->group->id);
    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $engineer  = User::factory()->create(['group_id' => $this->group->id]);
    $bystander = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $bystander->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'My printer',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'assignee_id'   => $engineer->id,
    ]);
    $update  = route('grp.models.ticket.update', $ticket->id);
    $comment = route('grp.models.ticket.comment.store', $ticket->id);

    expect(GetTicketBadgeData::run($reporter)['mine']['to_do']['count'])->toBe(1);

    actingAs($reporter);
    patch($update, ['status' => 'in_progress'])->assertForbidden();
    post($comment, ['body' => 'secret from the reporter', 'is_internal' => true])->assertSessionHasErrors('is_internal');
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_update', false)->where('can_comment_internally', false)
    );

    actingAs($bystander);
    post($comment, ['body' => 'Engineering note from another engineer', 'is_internal' => true])->assertRedirect()->assertSessionHasNoErrors();

    actingAs($engineer);
    patch($update, ['status' => 'in_progress'])->assertRedirect()->assertSessionHasNoErrors();
    post($comment, ['body' => 'Looking into it'])->assertRedirect()->assertSessionHasNoErrors();
    post($comment, ['body' => 'Driver is broken, not telling yet', 'is_internal' => true])->assertRedirect()->assertSessionHasNoErrors();
    get(route('grp.tickets.show', $ticket->reference))->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_comment_internally', true)
            ->where('comments', fn ($comments) => collect($comments)->pluck('body')->contains('Driver is broken, not telling yet'))
    );

    $badges = GetTicketBadgeData::run($reporter->fresh());
    $titles = collect($badges['recent'])->pluck('title');
    expect($badges['mine']['in_progress']['count'])->toBe(1)
        ->and($badges['mine']['to_do']['count'])->toBe(0)
        ->and($titles->all())->toContain(__(':reference is now :status', ['reference' => $ticket->reference, 'status' => TicketStatusEnum::labels()['in_progress']]))
        ->and($titles->all())->toContain(__(':reference has a new comment', ['reference' => $ticket->reference]))
        ->and(collect($badges['recent'])->where('read', false)->count())->toBeGreaterThanOrEqual(2)
        ->and($ticket->comments()->where('body', 'like', 'secret%')->count())->toBe(0);

    actingAs($reporter);
    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('comments', fn ($comments) => collect($comments)->pluck('body')->contains('Driver is broken, not telling yet'))
    );
    expect(collect(GetTicketBadgeData::run($reporter->fresh())['recent'])->where('read', false)->count())->toBe(0);

    $note      = $ticket->comments()->where('body', 'Driver is broken, not telling yet')->first();
    $showsNote = fn () => get(route('grp.tickets.show', $ticket->reference))->assertOk()->viewData('page')['props']['comments'];

    actingAs($bystander);
    expect(collect($showsNote())->pluck('body')->all())->toContain('Driver is broken, not telling yet');

    actingAs($this->user);
    patch(route('grp.models.ticket.comment.toggle_visibility', $note->id))->assertRedirect();
    expect($note->fresh()->is_lead_only)->toBeTrue()
        ->and(collect($showsNote())->pluck('body')->all())->toContain('Driver is broken, not telling yet');

    actingAs($engineer);
    expect(collect($showsNote())->pluck('body')->all())->not->toContain('Driver is broken, not telling yet');
});

test('comments show who wrote them with their role, but never in the customer portal', function () {
    setPermissionsTeamId($this->group->id);
    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Who said what',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'assignee_id'   => $engineer->id,
    ]);
    StoreTicketComment::make()->action($ticket, $reporter, ['body' => 'from the reporter']);
    StoreTicketComment::make()->action($ticket, $engineer, ['body' => 'from the engineer']);
    StoreTicketComment::make()->action($ticket, $qa, ['body' => 'from qa']);
    StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'from the lead']);
    $bot = User::factory()->create(['group_id' => $this->group->id, 'is_bot' => true]);
    $bot->assignRole('help-desk-clerk');
    StoreTicketComment::make()->action($ticket, $bot, ['body' => 'from the bot']);

    $roles = collect(get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->json('comments'))
        ->mapWithKeys(fn (array $comment) => [$comment['body'] => collect($comment['author_roles'])->pluck('key')->all()]);
    expect($roles['from the reporter'])->toBe(['reporter'])
        ->and($roles['from the engineer'])->toBe(['engineer'])
        ->and($roles['from qa'])->toBe(['qa'])
        ->and($roles['from the lead'])->toBe(['lead_engineer'])
        ->and($roles['from the bot'])->toBe(['bot'])
        ->and(collect(get(route('grp.json.ticket.controls', $ticket->id))->json('comments'))->every(fn ($comment) => array_key_exists('author_avatar', $comment)))->toBeTrue();

    $customerTicket = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Customer thread']);
    StoreTicketComment::make()->action($customerTicket, $this->user, ['body' => 'we are on it']);

    $customerComments = $this->actingAs($this->webUser, 'retina')
        ->get('http://'.$this->website->domain.'/app/dropshipping/support/'.$customerTicket->reference)
        ->assertOk()
        ->viewData('page')['props']['comments'];
    expect(collect($customerComments)->pluck('author_roles')->flatten()->all())->toBe([]);
});

test('help desk staff and QA can raise engineering tickets with an INI reference', function () {
    setPermissionsTeamId($this->group->id);
    $engineer = User::factory()->create(['group_id' => $this->group->id]);
    $qa       = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    actingAs($engineer);
    get(route('grp.tickets.create'))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['help', 'engineer', 'customer', 'premonition'])
    );
    post(route('grp.models.ticket.store'), ['subject' => 'Refactor the queue', 'type' => 'engineer'])->assertRedirect()->assertSessionHasNoErrors();
    expect(Ticket::where('subject', 'Refactor the queue')->value('reference'))->toStartWith('INI-');

    AikuServer::actingAs($engineer)->tool(TicketWriteTool::class, ['subject' => 'Claim list repeats products', 'type' => 'premonition'])->assertOk();
    expect(Ticket::where('subject', 'Claim list repeats products')->value('reference'))->toStartWith('PRE-');

    actingAs($qa);
    post(route('grp.models.ticket.store'), ['subject' => 'Test plan', 'type' => 'engineer'])->assertRedirect()->assertSessionHasNoErrors();

    actingAs($colleague);
    get(route('grp.tickets.create'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('types', []));
    post(route('grp.models.ticket.store'), ['subject' => 'Not for me', 'type' => 'engineer'])->assertSessionHasErrors('type');
    AikuServer::actingAs($colleague)->tool(TicketWriteTool::class, ['subject' => 'Not mine to raise', 'type' => 'premonition'])->assertHasErrors();
    post(route('grp.models.ticket.store'), ['subject' => 'Plain help'])->assertRedirect()->assertSessionHasNoErrors();
    post(route('grp.models.ticket.store'), ['subject' => 'Empty type', 'type' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect(Ticket::where('subject', 'Plain help')->value('reference'))->toStartWith('HELP-')
        ->and(Ticket::where('subject', 'Empty type')->value('reference'))->toStartWith('HELP-');
});

test('engineers claim unassigned tickets, then only the assignee or a lead engineer hands them over', function () {
    setPermissionsTeamId($this->group->id);
    $engineer  = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $colleague->assignRole('help-desk-clerk');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Up for grabs']);
    $update = route('grp.models.ticket.update', $ticket->id);

    actingAs($engineer);
    patch($update, ['assignee_id' => $engineer->id])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->assignee_id)->toBe($engineer->id);

    actingAs($colleague);
    patch($update, ['assignee_id' => $colleague->id])->assertForbidden();

    actingAs($engineer);
    patch($update, ['assignee_id' => $colleague->id])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->assignee_id)->toBe($colleague->id);

    patch($update, ['assignee_id' => null])->assertForbidden();

    actingAs($this->user);
    patch($update, ['assignee_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->assignee_id)->toBeNull();

    $other = StoreTicket::make()->action($this->group, ['subject' => 'Also free']);
    actingAs($engineer);
    patch(route('grp.models.ticket.update', $other->id), ['assignee_id' => $colleague->id])->assertRedirect()->assertSessionHasNoErrors();
    expect($other->fresh()->assignee_id)->toBe($colleague->id);
});

test('status changes and QA verdicts reach collaborators, and field edits reach the assignee', function () {
    Mail::fake();
    Notification::fake();
    $assignee     = User::factory()->create(['group_id' => $this->group->id]);
    $collaborator = User::factory()->create(['group_id' => $this->group->id]);
    $qa           = User::factory()->create(['group_id' => $this->group->id]);
    setPermissionsTeamId($this->group->id);
    $assignee->assignRole('help-desk-clerk');
    $collaborator->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Notify the crew', 'assignee_id' => $assignee->id]);
    $ticket->collaborators()->attach($collaborator->id);
    Notification::fake();

    UpdateTicket::make()->action($ticket->fresh(), ['status' => TicketStatusEnum::IN_PROGRESS->value]);
    Notification::assertSentTo($collaborator, App\Notifications\TicketNotification::class, fn ($notification) => $notification->reason === 'status');

    actingAs($qa);
    UpdateTicket::make()->action($ticket->fresh(), ['qa_status' => TicketQaStatusEnum::REQUESTED->value]);
    patch(route('grp.models.ticket.update', $ticket->id), ['qa_status' => 'failed', 'qa_note' => 'nope'])->assertRedirect();
    Notification::assertSentTo($collaborator, App\Notifications\TicketNotification::class, fn ($notification) => $notification->reason === 'qa');
    Notification::assertSentTo($assignee, App\Notifications\TicketNotification::class, fn ($notification) => $notification->reason === 'qa');

    Notification::fake();
    actingAs($this->user);
    UpdateTicket::make()->action($ticket->fresh(), ['priority' => 'urgent', 'description' => 'More detail']);
    Notification::assertSentTo($assignee, App\Notifications\TicketNotification::class, fn ($notification) => $notification->reason === 'edited' && str_contains($notification->lines[0], 'urgency, description'));
    Notification::assertNotSentTo($collaborator, App\Notifications\TicketNotification::class);

    Notification::fake();
    actingAs($assignee);
    UpdateTicket::make()->action($ticket->fresh(), ['module' => TicketModuleEnum::cases()[0]->value]);
    Notification::assertNotSentTo($assignee, App\Notifications\TicketNotification::class);
    actingAs($this->user);
});

test('the link to somebody account is only offered to those who may open it', function () {
    setPermissionsTeamId($this->group->id);

    $reporter = User::factory()->create(['group_id' => $this->group->id]);
    $ticket   = StoreTicket::make()->action($this->group, [
        'subject'       => 'Who raised this',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
    ]);
    StoreTicketComment::make()->action($ticket, $reporter, ['body' => 'and who commented']);

    // every help desk role carries sysadmin through groupAdminPermissions, so the person who
    // must not see the link is somebody working a shop rather than the group
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $clerk->forgetWildcardPermissionIndex();

    // the resource reads the viewer off the request, so the request has to carry one
    $seenBy = function (User $viewer) use ($ticket) {
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $viewer);

        return [
            'reporter' => TicketResource::make($ticket->refresh())->toArray($request)['reporter_profile_url'],
            'author'   => \App\Http\Resources\Helpers\TicketCommentResource::make($ticket->comments()->first())->toArray($request)['author_profile_url'],
        ];
    };

    actingAs($clerk);
    $asClerk = $seenBy($clerk);

    expect($clerk->authTo('sysadmin.view'))->toBeFalse()
        ->and($asClerk['reporter'])->toBeNull()
        ->and($asClerk['author'])->toBeNull();

    $admin = User::factory()->create(['group_id' => $this->group->id]);
    $admin->assignRole('group-admin');
    $admin->forgetWildcardPermissionIndex();

    actingAs($admin);
    $asAdmin = $seenBy($admin);

    expect($admin->authTo('sysadmin.view'))->toBeTrue()
        ->and($asAdmin['reporter'])->toBe(route('grp.sysadmin.users.show', ['user' => $reporter->slug]))
        ->and($asAdmin['author'])->toBe(route('grp.sysadmin.users.show', ['user' => $reporter->slug]));
});
