<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Models\HumanResources\Employee;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\get;

require_once __DIR__.'/../Support/ChatHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createShop();
        $web = createWebsite($this->shop);
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
        $this->shop         = $web->shop;
    }
    $web->refresh();
    $this->web       = $web;
    $this->warehouse = createWarehouse();

    $this->customer = createCustomer($this->shop);

    $this->action               = new StoreChatSession();
    $this->sendMessageAction    = new SendChatMessage();
    $this->assignmentChatAction = new AssignChatToAgent();
    $this->closeChatAction      = new CloseChatSession();

    \Illuminate\Support\Facades\Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
});

test('staff task to a department is queued with a thread and the claimer joins it', function () {
    $requester = $this->user;
    $worker    = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Check the product looks like the picture', 'department' => 'warehouse']);

    expect($task->reference)->toStartWith('TASK-')
        ->and($task->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::TODO)
        ->and($task->assignee_id)->toBeNull()
        ->and($task->conversation->context_type)->toBe('StaffTask')
        ->and($task->conversation->participants()->count())->toBe(1)
        ->and($task->conversation->messages()->count())->toBe(1);

    expect(\App\Actions\Tasks\Json\GetStaffTasks::run($requester, 'requested')->pluck('id')->all())->toBe([$task->id])
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine'))->toBeEmpty();

    $task = \App\Actions\Tasks\UpdateStaffTask::run($task, $worker, ['status' => 'in_progress']);

    expect($task->assignee_id)->toBe($worker->id)
        ->and($task->started_at)->not->toBeNull()
        ->and($task->conversation->hasParticipant($worker))->toBeTrue()
        ->and($task->conversation->messages()->count())->toBe(2)
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine')->pluck('id')->all())->toBe([$task->id]);

    $task = \App\Actions\Tasks\UpdateStaffTask::run($task, $worker, ['status' => 'done']);

    expect($task->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::DONE)
        ->and($task->closed_at)->not->toBeNull()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine'))->toBeEmpty()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine', true)->pluck('id')->all())->toBe([$task->id]);
});

test('staff task raised from a chat message links back to the source thread', function () {
    $requester = $this->user;
    $colleague = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($requester, ['user_ids' => [$colleague->id]]);
    $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $colleague, ['body' => 'please update the homepage banners']);

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => $message->body, 'assignee_id' => $colleague->id, 'source_message_id' => $message->id]);

    expect($task->assignee_id)->toBe($colleague->id)
        ->and($task->assigned_at)->not->toBeNull()
        ->and($task->staff_conversation_id)->not->toBe($conversation->id)
        ->and($task->conversation->participants()->count())->toBe(2)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($conversation->messages()->latest('id')->first()->body)->toContain($task->reference);

    $cancelled = \App\Actions\Tasks\UpdateStaffTask::run($task, $colleague, ['status' => 'cancelled', 'note' => 'banner already updated']);

    expect($cancelled->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::CANCELLED)
        ->and($cancelled->conversation->messages()->latest('id')->first()->body)->toContain('banner already updated');
});

test('staff tasks page and options respond', function () {
    actingAs($this->user);

    get(route('grp.tasks.index'))->assertOk();
    get(route('grp.tasks.list_all'))->assertOk();
    get(route('grp.tasks.board'))->assertOk();
    get(route('grp.tasks.reports'))->assertOk();
    get(route('grp.tasks.reports', ['created' => '1w']))->assertOk();
    get(route('grp.tasks.eta_map'))->assertOk();
    getJson(route('grp.tasks.options'))->assertOk()->assertJsonStructure(['departments', 'my_departments', 'priorities', 'statuses']);
    getJson(route('grp.tasks.list', ['view' => 'department']))->assertOk();
});

test('whoever works on a task suggests a new ETA and the requester accepts or declines it', function () {
    $worker = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $task   = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Repaint the loading bay', 'assignee_id' => $worker->id, 'due_at' => now()->addDays(2)->toDateString()]);

    actingAs($worker)
        ->patchJson(route('grp.tasks.update', $task->reference), ['due_at' => now()->addDays(9)->toDateString()])
        ->assertForbidden();

    $newEta = now()->addDays(9)->toDateString();
    actingAs($worker)
        ->postJson(route('grp.tasks.eta_proposal.store', $task->reference), ['due_at' => $newEta, 'reason' => 'The paint arrives next week'])
        ->assertOk()
        ->assertJsonPath('data.eta_proposal.due_at', $newEta)
        ->assertJsonPath('data.eta_proposal.by_id', $worker->id);

    $notification = $this->user->notifications()->where('data', 'like', '%suggests a new ETA for '.$task->reference.'%')->latest()->first();

    expect($notification?->data['body'])->toBe('The paint arrives next week')
        ->and($task->conversation->messages()->latest('id')->first()->body)->toContain('The paint arrives next week');

    actingAs($worker)
        ->postJson(route('grp.tasks.eta_proposal.decide', $task->reference), ['decision' => 'accept'])
        ->assertForbidden();

    actingAs($this->user)
        ->postJson(route('grp.tasks.eta_proposal.decide', $task->reference), ['decision' => 'accept'])
        ->assertOk()
        ->assertJsonPath('data.due_at', $newEta)
        ->assertJsonPath('data.eta_proposal', null);

    actingAs($worker)->postJson(route('grp.tasks.eta_proposal.store', $task->reference), ['due_at' => now()->addDays(20)->toDateString(), 'reason' => 'Rain all week']);
    actingAs($this->user)
        ->postJson(route('grp.tasks.eta_proposal.decide', $task->reference), ['decision' => 'decline'])
        ->assertOk()
        ->assertJsonPath('data.due_at', $newEta)
        ->assertJsonPath('data.eta_proposal', null);

    actingAs($this->user)
        ->postJson(route('grp.tasks.eta_proposal.decide', $task->reference), ['decision' => 'accept'])
        ->assertUnprocessable();
});

test('someone taken off a task keeps the chat history up to then, cannot write, and sees everything again when added back', function () {
    fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $assignee     = $newColleague();
    $helper       = $newColleague();
    $nextAssignee = $newColleague();

    $task         = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Sort the returns', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id]]);
    $conversation = $task->conversation;
    $bodies       = fn (\App\Models\SysAdmin\User $user) => collect(actingAs($user)->getJson(route('grp.chat.staff.conversations.messages.index', $conversation))->assertOk()->json('data'))->pluck('body');

    \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'before the change']);
    expect($conversation->isActiveParticipant($helper))->toBeTrue();

    \App\Actions\Tasks\SyncStaffTaskCollaborators::run($task, [], $this->user);
    $this->travel(2)->seconds();
    \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'after the change']);

    expect($conversation->isActiveParticipant($helper))->toBeFalse()
        ->and($conversation->hasParticipant($helper))->toBeTrue()
        ->and($bodies($helper))->toContain('before the change')
        ->and($bodies($helper))->not->toContain('after the change');
    actingAs($helper)->postJson(route('grp.chat.staff.conversations.messages.store', $conversation), ['body' => 'can I still write?'])->assertForbidden();

    \App\Actions\Tasks\SyncStaffTaskCollaborators::run($task->refresh(), [$helper->id], $this->user);
    expect($conversation->isActiveParticipant($helper))->toBeTrue()
        ->and($bodies($helper))->toContain('after the change');

    \App\Actions\Tasks\UpdateStaffTask::run($task->refresh(), $this->user, ['assignee_id' => $nextAssignee->id]);
    expect($conversation->isActiveParticipant($nextAssignee))->toBeTrue()
        ->and($conversation->isActiveParticipant($assignee))->toBeFalse();
});

test('the person who raised a task edits its subject, description and files, the worker cannot, and the history says so', function () {
    $worker = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $task   = \App\Actions\Tasks\StoreStaffTask::run($this->user, [
        'subject'     => 'Count the returns shelf',
        'description' => 'Shelf B only',
        'assignee_id' => $worker->id,
        'images'      => [\Illuminate\Http\UploadedFile::fake()->image('old-photo.png')],
    ]);
    $oldPhoto     = $task->getMedia('ticket_images')->first();
    $contentRoute = route('grp.tasks.content.update', $task->reference);

    actingAs($worker);
    \Pest\Laravel\patchJson($contentRoute, ['subject' => 'Something else'])->assertForbidden();
    get(route('grp.tasks.show', $task->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_edit_content', false));
    getJson(route('grp.tasks.quick_look', $task->reference))->assertOk()->assertJsonPath('can_edit_content', false);

    actingAs($this->user);
    getJson(route('grp.tasks.quick_look', $task->reference))->assertOk()->assertJsonPath('can_edit_content', true);
    get(route('grp.tasks.show', $task->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_edit_content', true)->where('task.attachments.0.ulid', $oldPhoto->ulid));
    \Pest\Laravel\post($contentRoute, [
        '_method'      => 'patch',
        'subject'      => 'Count the returns shelves',
        'description'  => 'Shelves B and C',
        'remove_media' => [$oldPhoto->ulid],
        'images'       => [\Illuminate\Http\UploadedFile::fake()->image('new-photo.png')],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.subject', 'Count the returns shelves');

    $task->refresh();
    expect($task->description)->toBe('Shelves B and C')
        ->and($task->getMedia('ticket_images'))->toHaveCount(1)
        ->and($task->getMedia('ticket_images')->first()->ulid)->not->toBe($oldPhoto->ulid);

    $timeline = collect(get(route('grp.tasks.show', $task->reference))->inertiaProps()['timeline']);
    expect($timeline->firstWhere('text', 'Description edited')['change'])->toBe(['label' => 'Description', 'from' => 'Shelf B only', 'to' => 'Shelves B and C']);

    $history = $timeline->pluck('text');
    expect($history)->toContain('Subject edited')
        ->and($history)->toContain('Description edited')
        ->and($history->first(fn ($text) => str_starts_with($text, 'Removed old-photo')))->toContain(' · Added new-photo');
});

test('the tasks a person created are counted apart, with the ones waiting for their answer first', function () {
    $worker = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    \App\Models\Tasks\StaffTask::query()->where('requester_id', $this->user->id)->open()->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE]);

    $quiet   = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Order more tape', 'assignee_id' => $worker->id]);
    $waiting = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Repaint the dock', 'assignee_id' => $worker->id, 'due_at' => now()->addDay()->toDateString()]);
    \App\Actions\Tasks\ProposeStaffTaskEta::run($waiting, $worker, ['due_at' => now()->addDays(5)->toDateString(), 'reason' => 'Paint is late']);

    $created = \App\Actions\Tasks\GetStaffTaskBadgeData::make()->handle($this->user)['created'];

    expect($created['open'])->toBe(2)
        ->and($created['needs_answer'])->toBe(1)
        ->and($created['tasks'][0]['reference'])->toBe($waiting->reference)
        ->and($created['tasks'][0]['has_eta_proposal'])->toBeTrue()
        ->and(collect($created['tasks'])->pluck('reference'))->toContain($quiet->reference);

    actingAs($this->user);
    \Pest\Laravel\patch(route('grp.models.profile.update'), ['rail_hidden_badges' => ['tasks_created']])->assertSessionHasNoErrors();
});

test('the requester or assignee hands a task to someone else, who is told, while a helper cannot', function () {
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $assignee     = $newColleague();
    $helper       = $newColleague();
    $newAssignee  = $newColleague();

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Recount bay 7', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id]]);

    expect($task->canReassignBy($this->user))->toBeTrue()
        ->and($task->canReassignBy($assignee))->toBeTrue()
        ->and($task->canReassignBy($helper))->toBeFalse();

    actingAs($helper)->patchJson(route('grp.tasks.update', $task->reference), ['assignee_id' => $newAssignee->id])->assertForbidden();

    actingAs($assignee)->patchJson(route('grp.tasks.update', $task->reference), ['assignee_id' => $newAssignee->id])->assertOk();

    expect($task->refresh()->assignee_id)->toBe($newAssignee->id)
        ->and($newAssignee->notifications()->where('data', 'like', '%'.$task->reference.' is for you%')->exists())->toBeTrue();
});

test('a task sent to a department tells the people in it', function () {
    $groupPosition = \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $this->user->group_id)->whereNull('organisation_id')->whereNotNull('department')->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->first();
    $member        = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $member->id, 'job_position_id' => $groupPosition->id, 'group_id' => $member->group_id, 'scopes' => '{}']);

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Someone please check the new range', 'department' => $groupPosition->department]);

    expect($member->notifications()->where('data', 'like', '%'.$task->reference.' is waiting for someone from%')->exists())->toBeTrue();
});

test('a warehouse task tells its supervisors and stock controllers, not every picker and packer (HELP-3954)', function () {
    $withPosition = function (string $code) {
        $user = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
        \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert([
            'user_id'         => $user->id,
            'job_position_id' => \Illuminate\Support\Facades\DB::table('job_positions')->where('organisation_id', $this->organisation->id)->where('code', $code)->value('id'),
            'group_id'        => $user->group_id,
            'scopes'          => '{}',
        ]);

        return $user;
    };
    $supervisor      = $withPosition('wah-m');
    $stockController = $withPosition('wah-sc');
    $packer          = $withPosition('dist-pak');

    $task     = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Recount shelf 12', 'department' => 'warehouse', 'data' => ['organisation_id' => $this->organisation->id]]);
    $wasTold  = fn ($user) => $user->notifications()->where('data', 'like', '%'.$task->reference.' is waiting for someone from%')->exists();

    expect($wasTold($supervisor))->toBeTrue()
        ->and($wasTold($stockController))->toBeTrue()
        ->and($wasTold($packer))->toBeFalse()
        ->and(\App\Models\Tasks\StaffTask::departmentMembers($this->user, 'warehouse', $this->organisation->id)->pluck('id'))->toContain($packer->id);
});

test('a task goes to a person and a department at once, and only the department takes itself off with a reason', function () {
    $groupPosition = \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $this->user->group_id)->whereNull('organisation_id')->whereNotNull('department')
        ->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)
        ->whereNotIn('department', \Illuminate\Support\Facades\DB::table('job_positions')->where('slug', 'group-admin')->select('department'))
        ->first();
    $newColleague  = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $member        = $newColleague();
    $worker        = $newColleague();
    $outsider      = $newColleague();
    \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $member->id, 'job_position_id' => $groupPosition->id, 'group_id' => $member->group_id, 'scopes' => '{}']);
    $departmentLabel = \App\Models\Tasks\StaffTask::departmentLabel($groupPosition->department);

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, [
        'subject'     => 'Check the new supplier contract',
        'assignee_id' => $worker->id,
        'department'  => $groupPosition->department,
        'due_at'      => now()->addDays(3)->toDateString(),
    ]);
    $removal = route('grp.tasks.department.remove', $task->reference);

    expect(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine')->pluck('id'))->toContain($task->id)
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($member, 'department')->pluck('id'))->toContain($task->id);

    foreach ([$this->user, $worker, $outsider] as $notInTheDepartment) {
        actingAs($notInTheDepartment);
        \Pest\Laravel\postJson($removal, ['reason' => 'Not ours'])->assertForbidden();
    }
    actingAs($this->user);
    \Pest\Laravel\patchJson(route('grp.tasks.update', $task->reference), ['department' => null])->assertForbidden();

    actingAs($member);
    get(route('grp.tasks.show', $task->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_remove_department', true));
    \Pest\Laravel\postJson($removal, ['reason' => ''])->assertUnprocessable();
    \Pest\Laravel\postJson($removal, ['reason' => 'Contracts go to legal, not to us'])->assertOk();

    $task->refresh();
    expect($task->department)->toBeNull()
        ->and($task->assignee_id)->toBe($worker->id)
        ->and($task->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::TODO)
        ->and($task->due_at->toDateString())->toBe(now()->addDays(3)->toDateString())
        ->and($this->user->notifications()->where('data', 'like', '%removed '.$departmentLabel.' from '.$task->reference.'%')->exists())->toBeTrue()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($worker, 'mine')->pluck('id'))->toContain($task->id);

    actingAs($this->user);
    $removed = collect(get(route('grp.tasks.show', $task->reference))->inertiaProps()['timeline'])->firstWhere('text', 'Removed from '.$departmentLabel.': Contracts go to legal, not to us');
    expect($removed)->not->toBeNull()
        ->and($removed['by'])->toBe($member->chatName());

    \Pest\Laravel\patchJson(route('grp.tasks.update', $task->reference), ['department' => $groupPosition->department])->assertOk();
    expect($task->refresh()->department)->toBe($groupPosition->department);

    $nobodysTask = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Department only', 'department' => $groupPosition->department]);
    actingAs($member);
    \Pest\Laravel\postJson(route('grp.tasks.department.remove', $nobodysTask->reference), ['reason' => 'Not ours'])->assertUnprocessable();
});

test('open tasks are reminded the day before they are due and once they are late, once per due date', function () {
    $worker = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $task   = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Ship the samples', 'assignee_id' => $worker->id, 'due_at' => now()->addDay()->toDateString()]);
    $notified = fn (\App\Models\SysAdmin\User $user, string $text) => $user->notifications()->where('data', 'like', '%'.$task->reference.' '.$text.'%')->count();

    \App\Actions\Tasks\RemindStaffTaskDueDates::run();
    \App\Actions\Tasks\RemindStaffTaskDueDates::run();
    expect($notified($worker, 'is due tomorrow'))->toBe(1);

    $task->refresh()->update(['due_at' => now()->subDay()->toDateString()]);
    \App\Actions\Tasks\RemindStaffTaskDueDates::run();
    \App\Actions\Tasks\RemindStaffTaskDueDates::run();

    expect($notified($worker, 'is overdue'))->toBe(1)
        ->and($notified($this->user, 'is overdue'))->toBe(1);
});

test('whoever works on a task can ask for help and the requester hears why', function () {
    $worker   = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $outsider = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $task     = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Fix the label printer', 'assignee_id' => $worker->id]);

    actingAs($outsider)->postJson(route('grp.tasks.help.store', $task->reference), ['note' => 'not mine'])->assertForbidden();

    actingAs($worker)->postJson(route('grp.tasks.help.store', $task->reference), ['note' => 'The printer needs a part I cannot order'])->assertOk();

    expect($this->user->notifications()->where('data', 'like', '%needs help with '.$task->reference.'%')->exists())->toBeTrue()
        ->and($task->conversation->messages()->latest('id')->first()->body)->toContain('The printer needs a part I cannot order')
        ->and($task->refresh()->data['help_requested']['by_id'])->toBe($worker->id);
});

test('a supervisor working on a task asks for a new ETA while a supervisor outside it sets the date', function () {
    $newSupervisor = function () {
        $user     = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
        $position = \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $user->group_id)->where('code', 'like', '%-m')->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->value('id');
        \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $user->id, 'job_position_id' => $position, 'group_id' => $user->group_id, 'scopes' => '{}']);

        return $user;
    };
    $workingSupervisor = $newSupervisor();
    $outsideSupervisor = $newSupervisor();

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Check the returns shelf', 'assignee_id' => $workingSupervisor->id, 'due_at' => now()->addDays(3)->toDateString()]);

    expect($task->dueAccessFor($workingSupervisor))->toBe(['can_set' => false, 'can_suggest' => true])
        ->and($task->dueAccessFor($outsideSupervisor))->toBe(['can_set' => true, 'can_suggest' => false])
        ->and($task->dueAccessFor($this->user))->toBe(['can_set' => true, 'can_suggest' => false]);

    actingAs($workingSupervisor)
        ->patchJson(route('grp.tasks.update', $task->reference), ['due_at' => now()->addDays(10)->toDateString()])
        ->assertForbidden();

    actingAs($workingSupervisor)
        ->postJson(route('grp.tasks.eta_proposal.store', $task->reference), ['due_at' => now()->addDays(10)->toDateString(), 'reason' => 'Two returns still in transit'])
        ->assertOk();

    actingAs($outsideSupervisor)
        ->postJson(route('grp.tasks.eta_proposal.decide', $task->reference), ['decision' => 'accept'])
        ->assertOk()
        ->assertJsonPath('data.due_at', now()->addDays(10)->toDateString());
});

test('a collaborator can only take themselves off a task while its assignee can take anyone off', function () {
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $assignee     = $newColleague();
    $helper       = $newColleague();
    $otherHelper  = $newColleague();

    $task         = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Restack the pallets', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id, $otherHelper->id]]);
    $syncAs       = fn ($user, array $ids) => actingAs($user)->patchJson(route('grp.tasks.collaborators.update', $task->reference), ['collaborator_ids' => $ids]);
    $collaborators = fn () => $task->refresh()->collaborators()->pluck('users.id')->sort()->values()->all();

    expect($task->canRemoveCollaboratorsBy($helper))->toBeFalse()
        ->and($task->canRemoveCollaboratorsBy($assignee))->toBeTrue();

    $syncAs($helper, [$helper->id])->assertForbidden();
    expect($collaborators())->toBe(collect([$helper->id, $otherHelper->id])->sort()->values()->all());

    $syncAs($helper, [$otherHelper->id])->assertOk();
    expect($collaborators())->toBe([$otherHelper->id]);

    $syncAs($assignee, [])->assertOk();
    expect($collaborators())->toBe([]);
});

test('the ETA map lays open tasks out by person and day with how pressing each one is', function () {
    $worker = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $overdue  = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Late one', 'assignee_id' => $worker->id, 'due_at' => now()->subDays(2)->toDateString()]);
    $tomorrow = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Tomorrow one', 'assignee_id' => $worker->id, 'due_at' => now()->addDay()->toDateString()]);
    $pressing = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Urgent next week', 'assignee_id' => $worker->id, 'priority' => 'urgent', 'due_at' => now()->addDays(6)->toDateString()]);
    $relaxed  = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Some day', 'assignee_id' => $worker->id, 'due_at' => now()->addDays(30)->toDateString()]);
    $open     = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Whenever', 'department' => 'warehouse']);
    \App\Actions\Tasks\UpdateStaffTask::run($relaxed->refresh(), $worker, ['status' => 'done']);

    $map = \App\Actions\Tasks\UI\ShowStaffTasksEtaMap::make()->handle($this->organisation->group, $this->user);

    $chips = collect($map['rows'])->flatMap(fn (array $row) => collect($row['cells'])->flatMap(fn (array $cell, string $column) => collect($cell)->map(fn (array $chip) => [...$chip, 'row' => $row['key'], 'column' => $column])))->keyBy('id');

    expect($chips[$overdue->id])->toMatchArray(['urgency' => 'overdue', 'column' => 'overdue', 'row' => 'user:'.$worker->id])
        ->and($chips[$tomorrow->id])->toMatchArray(['urgency' => 'critical', 'column' => now()->addDay()->toDateString()])
        ->and($chips[$pressing->id])->toMatchArray(['urgency' => 'soon', 'column' => now()->addDays(6)->toDateString()])
        ->and($chips[$open->id])->toMatchArray(['urgency' => 'none', 'column' => 'none', 'row' => 'department:warehouse'])
        ->and($chips->has($relaxed->id))->toBeFalse()
        ->and(collect($map['columns'])->pluck('key')->first())->toBe('overdue')
        ->and(collect($map['columns'])->firstWhere('is_today', true)['key'])->toBe(now()->toDateString());
});

test('all tasks list counts tasks per status and tells who works on each row', function () {
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $assignee     = $newColleague();
    $helper       = $newColleague();

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Count the top shelf', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id]]);

    $row = (new \App\Http\Resources\Tasks\StaffTasksResource($task->load(['requester.image', 'assignee.image', 'collaborators', 'conversation'])))->resolve();

    expect($row['assignee_id'])->toBe($assignee->id)
        ->and($row['assignee_short'])->toBe(strtok($assignee->chatName(), ' '))
        ->and(collect($row['collaborators'])->pluck('id')->all())->toBe([$helper->id])
        ->and($row['conversation_ulid'])->not->toBeNull()
        ->and($row['is_overdue'])->toBeFalse();

    $summary = \App\Actions\Tasks\UI\IndexStaffTasks::make()->listSummary($this->organisation->group, $this->user);
    $visible = \App\Models\Tasks\StaffTask::query()->within($this->organisation->group)->visibleTo($this->user);

    expect(array_keys($summary))->toBe(['todo', 'in_progress', 'done', 'cancelled'])
        ->and($summary['todo'])->toBe((clone $visible)->where('status', 'todo')->count())
        ->and($summary['todo'])->toBeGreaterThanOrEqual(1);

    actingAs($this->user);
    get(route('grp.tasks.list_all'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('listSummary.todo', $summary['todo'])
        ->has('options.statuses', 4)
        ->has('options.priorities')
        ->where('showRoute.name', 'grp.tasks.show'));
});

test('all tasks list filters by assignee, including me and unassigned', function () {
    $colleague = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $mine       = \App\Actions\Tasks\StoreStaffTask::run($colleague, ['subject' => 'Check the returns shelf', 'assignee_id' => $this->user->id]);
    $theirs     = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Label the new pallets', 'assignee_id' => $colleague->id]);
    $unassigned = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Sweep the loading bay']);

    actingAs($this->user);

    $taskIdsFor = fn (string $assignee) => \Pest\Laravel\getJson(route('grp.tasks.list_all', ['filter' => ['assignee' => $assignee]]))
        ->assertOk()
        ->json('data.*.id');

    expect($taskIdsFor('me'))->toContain($mine->id)->not->toContain($theirs->id)->not->toContain($unassigned->id)
        ->and($taskIdsFor('unassigned'))->toContain($unassigned->id)->not->toContain($mine->id)
        ->and($taskIdsFor((string) $colleague->id))->toContain($theirs->id)->not->toContain($mine->id);

    get(route('grp.tasks.list_all', ['filter' => ['assignee' => 'me']]))->assertOk();
    get(route('grp.tasks.list_all', ['filter' => ['organisation' => $this->organisation->slug]]))->assertOk();

    get(route('grp.tasks.board', ['filter' => ['assignee' => 'me', 'department' => 'none']]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('appliedTaskFilters.assignee', 'me')
            ->where('appliedTaskFilters.department', 'none')
            ->where('taskFilterOptions.assignee', fn ($options) => collect($options)->take(3)->pluck('value')->all() === ['all', 'me', 'unassigned'])
            ->where('columns', fn ($columns) => collect($columns)->flatMap(fn ($column) => $column['tasks'])->pluck('id')->diff([$mine->id])->isEmpty()));

    get(route('grp.tasks.list_all'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('appliedTaskFilters.assignee', 'me')
            ->where('data.data', fn ($rows) => collect($rows)->pluck('id')->contains($mine->id) && !collect($rows)->pluck('id')->contains($theirs->id)));

    expect($taskIdsFor('all'))->toContain($mine->id)->toContain($theirs->id)->toContain($unassigned->id);

    \App\Models\Tasks\StaffTask::whereIn('id', [$mine->id, $theirs->id, $unassigned->id])->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
});

test('task page shows the task with its chat and only the people working on it can change it', function () {
    $newColleague = fn (array $positions) => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => $positions]))->getUser();
    $assignee     = $newColleague([['slug' => 'group-admin', 'scopes' => []]]);
    $helper       = $newColleague([['slug' => 'group-admin', 'scopes' => []]]);
    $outsider     = $newColleague([]);

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Restock the leaflets', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id]]);

    actingAs($this->user);
    get(route('grp.tasks.show', $task->reference))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('task.reference', $task->reference)
        ->where('conversation.ulid', $task->conversation->ulid)
        ->where('can_edit', false)
        ->has('options.statuses', 4));
    get(route('grp.org.tasks.show', [$this->organisation->slug, $task->reference]))->assertOk();

    actingAs($assignee);
    get(route('grp.tasks.show', $task->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_edit', true));

    actingAs($helper);
    get(route('grp.tasks.show', $task->reference))->assertInertia(fn (AssertableInertia $page) => $page->where('can_edit', true));

    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastStaffTaskChanged::class]);
    actingAs($assignee);
    \App\Actions\Tasks\UpdateStaffTask::run($task, $assignee, ['status' => 'in_progress', 'priority' => 'high']);
    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\BroadcastStaffTaskChanged::class, fn ($event) => $event->reference === $task->reference);

    get(route('grp.tasks.show', $task->reference))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('timeline.0.by', $assignee->chatName())
        ->where('timeline', fn ($timeline) => collect($timeline)->pluck('text')->contains(fn ($text) => str_contains($text, 'Working on it'))
            && collect($timeline)->last()['text'] === 'Task raised'));

    actingAs($this->user);
    getJson(route('grp.tasks.list', ['view' => 'requested']))->assertOk()->assertJsonStructure(['counts' => ['mine', 'department', 'requested']]);

    actingAs($helper);
    getJson(route('grp.tasks.quick_look', $task->reference))->assertOk()
        ->assertJsonPath('task.reference', $task->reference)
        ->assertJsonPath('can_edit', true)
        ->assertJsonStructure(['linked_url', 'options' => ['statuses', 'priorities'], 'messages' => [['id', 'user_name', 'body', 'created_at']], 'message_count']);

    actingAs($outsider);
    get(route('grp.tasks.show', $task->reference))->assertForbidden();
    getJson(route('grp.tasks.quick_look', $task->reference))->assertForbidden();

    $resource = (new \App\Http\Resources\Chat\StaffConversationResource($task->conversation->load(['participants', 'context'])))->resolve();
    expect($resource['context_url'])->toBe(route('grp.tasks.show', $task->reference));
});

test('tasks reports break down by person, requester and department and drill into the task list', function () {
    $colleague = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Report me', 'assignee_id' => $colleague->id]);

    actingAs($this->user);
    get(route('grp.tasks.reports', ['created' => '1w']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('stats.people.assignee')
        ->has('stats.people.collaborator')
        ->has('stats.people.involved')
        ->has('stats.requesters')
        ->has('stats.departments')
        ->has('stats.cleared.rows')
        ->has('personOptions')
        ->where('stats.people.assignee', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $colleague->id && $row['todo'] >= 1)));

    get(route('grp.tasks.reports', ['person' => $colleague->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('stats.person', $colleague->id)
        ->where('stats.cleared', null)
        ->where('stats.totals.created', fn ($created) => $created >= 1));

    get(route('grp.tasks.list_all', [
        'filter'   => ['created_since' => now()->subWeek()->toDateString(), 'involved' => $colleague->id, 'department' => 'none', 'has_assignee' => 1, 'created_before' => now()->addDay()->toDateString()],
        'elements' => ['status' => 'todo,in_progress,done,cancelled'],
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => collect($rows)->pluck('subject')->contains('Report me')));
});

test('people can hide right panel badges from the narrow bar', function () {
    actingAs($this->user);

    \Pest\Laravel\patch(route('grp.models.profile.update'), ['rail_hidden_badges' => ['tasks', 'crm_waiting']])->assertSessionHasNoErrors();
    expect($this->user->fresh()->settings['rail_hidden_badges'])->toBe(['tasks', 'crm_waiting'])
        ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['rail_hidden_badges'])->toBe(['tasks', 'crm_waiting']);

    \Pest\Laravel\patch(route('grp.models.profile.update'), ['rail_hidden_badges' => ['not_a_badge']])->assertSessionHasErrors('rail_hidden_badges.0');

    \Pest\Laravel\patch(route('grp.models.profile.update'), ['rail_hidden_badges' => []])->assertSessionHasNoErrors();
    expect($this->user->fresh()->settings['rail_hidden_badges'] ?? [])->toBe([]);
});

test('people get told about tasks for them and the task badge counts their work', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastStaffTaskBadgeUpdate::class]);
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $assignee     = $newColleague();
    $helper       = $newColleague();
    $taskNotes    = fn ($user) => $user->notifications()->whereRaw("(data::jsonb)->>'type' = 'staff_task'");

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Badge me', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id], 'due_at' => now()->subDay()->toDateString()]);

    expect($taskNotes($assignee)->count())->toBe(1)
        ->and($taskNotes($helper)->count())->toBe(1);

    $badges = \App\Actions\Tasks\GetStaffTaskBadgeData::run($assignee);
    expect($badges['mine']['todo']['count'])->toBe(1)
        ->and($badges['mine']['overdue']['count'])->toBe(1)
        ->and($badges['today'])->toBe(['done' => 0, 'open' => 1])
        ->and($badges['recent'][0]['read'])->toBeFalse();
    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\BroadcastStaffTaskBadgeUpdate::class, fn ($event) => $event->userId === $assignee->id);

    actingAs($assignee);
    \App\Actions\Tasks\UpdateStaffTask::run($task, $assignee, ['status' => 'done']);
    expect($taskNotes($this->user)->whereRaw("(data::jsonb)->>'title' = ?", ["{$task->reference} is done"])->exists())->toBeTrue()
        ->and(\App\Actions\Tasks\GetStaffTaskBadgeData::run($assignee)['today'])->toBe(['done' => 1, 'open' => 0]);

    get(route('grp.tasks.show', $task->reference))->assertOk();
    expect($assignee->unreadNotifications()->whereRaw("(data::jsonb)->>'type' = 'staff_task'")->count())->toBe(0);

    get(route('grp.tasks.list_all', ['filter' => ['involved' => $assignee->id, 'overdue' => 1, 'unassigned' => 0, 'department' => 'warehouse,office'], 'elements' => ['status' => 'todo,in_progress,done,cancelled']]))->assertOk();
});

test('the people working on a task keep its list of sub tasks', function () {
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $assignee     = $newColleague();
    $helper       = $newColleague();

    $task = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Prepare the stand', 'assignee_id' => $assignee->id, 'collaborator_ids' => [$helper->id], 'subtasks' => [['title' => ' Book the van ']]]);
    expect($task->fresh()->data['subtasks'])->toBe([['title' => 'Book the van', 'status' => 'todo']]);

    $task->update(['data' => ['nudged_at' => '2026-10-01']]);

    actingAs($assignee);
    \Pest\Laravel\patchJson(route('grp.tasks.subtasks.update', $task->reference), ['subtasks' => [
        ['title' => '  Do A ', 'status' => 'done'],
        ['title' => 'Check B', 'status' => 'todo'],
        ['title' => 'Do C', 'status' => 'in_progress'],
    ]])->assertOk()
        ->assertJsonPath('data.subtasks.0.title', 'Do A')
        ->assertJsonPath('data.subtasks.2.status', 'in_progress');

    expect($task->fresh()->data['subtasks'])->toHaveCount(3)
        ->and($task->fresh()->data['nudged_at'])->toBe('2026-10-01');

    $thread    = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($assignee)->firstWhere('id', $task->staff_conversation_id);
    $threadRow = (new \App\Http\Resources\Chat\StaffConversationResource($thread))->resolve();
    expect($threadRow['task']['reference'])->toBe($task->reference)
        ->and($threadRow['task']['assignee_id'])->toBe($assignee->id)
        ->and($threadRow['task']['collaborator_ids'])->toBe([$helper->id])
        ->and($threadRow['task']['subtasks'])->toHaveCount(3);

    actingAs($helper);
    \Pest\Laravel\patchJson(route('grp.tasks.subtasks.update', $task->reference), ['subtasks' => [['title' => 'Do A', 'status' => 'finished']]])->assertUnprocessable();
    \Pest\Laravel\patchJson(route('grp.tasks.subtasks.update', $task->reference), ['subtasks' => []])->assertOk();
    expect($task->fresh()->data['subtasks'])->toBe([]);

    actingAs($this->user);
    \Pest\Laravel\patchJson(route('grp.tasks.subtasks.update', $task->reference), ['subtasks' => []])->assertForbidden();
});

test('people offered for a task on a record are the colleagues who can open it', function () {
    $shop = $this->customer->shop;
    setPermissionsTeamId($this->user->group_id);
    SeedShopPermissions::run($shop);
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();

    $crmColleague = $newColleague();
    $crmColleague->givePermissionTo("crm.$shop->id.view");
    $outsider = $newColleague();

    $permissions = \App\Models\Tasks\StaffTask::viewPermissionsOf('Customer', $this->customer->id, $this->user->group_id);
    \Illuminate\Support\Facades\Cache::forget('staff-coworkers:'.$this->user->group_id);
    \Illuminate\Support\Facades\Cache::forget('staff-coworkers-able:'.$this->user->group_id.':'.implode('|', $permissions));

    actingAs($this->user);
    $everyone = collect(getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data'))->pluck('id');
    $scoped   = collect(getJson(route('grp.chat.staff.coworkers.index', ['model_type' => 'Customer', 'model_id' => $this->customer->id]))->assertOk()->json('data'))->pluck('id');

    expect($everyone)->toContain($crmColleague->id)->toContain($outsider->id)
        ->and($scoped)->toContain($crmColleague->id)
        ->and($scoped)->not->toContain($outsider->id)
        ->and($scoped)->not->toContain($this->user->id);

    $onStock = collect(getJson(route('grp.chat.staff.coworkers.index', ['model_type' => 'OrgStock', 'model_id' => 1]))->assertOk()->json('data'))->pluck('id');
    expect($onStock)->toContain($crmColleague->id)->toContain($outsider->id);

    getJson(route('grp.chat.staff.coworkers.index', ['model_type' => 'Invoice', 'model_id' => 1]))->assertUnprocessable();
});

test('stale staff task nudges its assignee once per window', function () {
    $requester = $this->user;
    $assignee  = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Quiet task', 'assignee_id' => $assignee->id]);

    expect(\App\Actions\Tasks\NudgeStaleStaffTasks::run(48))->toBe(0);

    $task->conversation->update(['last_message_at' => now()->subHours(50)]);
    $notificationsBeforeNudge = $assignee->notifications()->count();

    expect(\App\Actions\Tasks\NudgeStaleStaffTasks::run(48))->toBe(1)
        ->and(\App\Actions\Tasks\NudgeStaleStaffTasks::run(48))->toBe(0)
        ->and($assignee->notifications()->count())->toBe($notificationsBeforeNudge + 1)
        ->and($task->fresh()->data['nudged_at'])->not->toBeNull();
});

test('engineers and qa see staff tasks but cannot be assigned one', function () {
    $engineer = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'gp-hd', 'scopes' => []]]]))->getUser();

    expect(\App\Models\Tasks\StaffTask::canBeAssigned($engineer))->toBeFalse()
        ->and(\App\Models\Tasks\StaffTask::canBeAssigned($this->user))->toBeTrue()
        ->and(collect(\App\Models\Tasks\StaffTask::departments($this->organisation->group_id))->pluck('value'))->not->toContain('help-desk');

    actingAs($engineer);
    get(route('grp.tasks.index'))->assertOk();
    get(route('grp.tasks.board'))->assertOk();

    actingAs($this->user);
    \Pest\Laravel\postJson(route('grp.tasks.store'), ['subject' => 'Fix the bug', 'assignee_id' => $engineer->id])->assertUnprocessable();
});

test('staff task can be raised with attachments that only people who see the task can open', function () {
    $assignee = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $outsider = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert([
        'user_id'         => $outsider->id,
        'job_position_id' => \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $this->organisation->group_id)->where('department', 'warehouse')->where('code', 'not like', '%-m')->value('id'),
        'group_id'        => $this->organisation->group_id,
        'scopes'          => '{}',
    ]);

    actingAs($this->user);
    $response = \Pest\Laravel\post(route('grp.tasks.store'), [
        'subject'     => 'Repack the damaged boxes',
        'description' => 'See the photo',
        'assignee_id' => $assignee->id,
        'images'      => [
            \Illuminate\Http\UploadedFile::fake()->image('damage.png', 400, 300),
            \Illuminate\Http\UploadedFile::fake()->createWithContent('packing-list.pdf', "%PDF-1.4\n%%EOF\n"),
        ],
    ], ['Accept' => 'application/json'])->assertCreated();

    $task = \App\Models\Tasks\StaffTask::where('reference', $response->json('data.reference'))->firstOrFail();

    expect($task->getMedia('ticket_images'))->toHaveCount(1)
        ->and($task->getMedia('ticket_attachments'))->toHaveCount(1)
        ->and(collect($response->json('data.attachments'))->pluck('name')->all())->toBe(['damage.png', 'packing-list.pdf'])
        ->and($response->json('data.attachments.0.thumbnail'))->not->toBeNull();

    $pdf = $task->getMedia('ticket_attachments')->first();

    actingAs($assignee);
    get(route('grp.tasks.attachments.show', ['staffTask' => $task->reference, 'media' => $pdf->ulid]))->assertOk();
    \Pest\Laravel\getJson(route('grp.tasks.details', $task->reference))->assertOk()
        ->assertJsonPath('data.reference', $task->reference)
        ->assertJsonCount(2, 'data.attachments');

    $otherTask  = \App\Actions\Tasks\StoreStaffTask::run($assignee, ['subject' => 'Other task', 'assignee_id' => $assignee->id, 'images' => [\Illuminate\Http\UploadedFile::fake()->image('other.png')]]);
    $otherMedia = $otherTask->getMedia('ticket_images')->first();
    get(route('grp.tasks.attachments.show', ['staffTask' => $task->reference, 'media' => $otherMedia->ulid]))->assertNotFound();

    actingAs($outsider);
    get(route('grp.tasks.attachments.show', ['staffTask' => $task->reference, 'media' => $pdf->ulid]))->assertForbidden();
    \Pest\Laravel\getJson(route('grp.tasks.details', $task->reference))->assertForbidden();

    actingAs($this->user);
    \Pest\Laravel\postJson(route('grp.tasks.store'), [
        'subject'     => 'Too many files',
        'assignee_id' => $assignee->id,
        'images'      => array_map(fn (int $index) => \Illuminate\Http\UploadedFile::fake()->image("shot-$index.png"), range(1, 6)),
    ])->assertUnprocessable()->assertJsonValidationErrors('images');
});

test('staff tasks are raised from a pasted list with people, departments and due dates', function () {
    $colleague = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $department = \App\Models\Tasks\StaffTask::departments($this->organisation->group_id)[0];

    $list = "Task\tWho\tDue\n"
        ."1. Refresh the banner\t{$department['label']}\t30/10/2026\n"
        ."- Call the supplier | {$colleague->username} | 2026-11-02\n\n"
        ."Tidy my desk";

    $rows = \App\Actions\Tasks\StoreStaffTasksFromList::make()->parse($this->user, $list);

    expect(collect($rows)->pluck('subject')->all())->toBe(['Refresh the banner', 'Call the supplier', 'Tidy my desk'])
        ->and(collect($rows)->pluck('error')->filter()->all())->toBeEmpty()
        ->and($rows[0]['department'])->toBe($department['value'])
        ->and($rows[0]['due_at'])->toBe('2026-10-30')
        ->and($rows[1]['assignee_id'])->toBe($colleague->id)
        ->and($rows[2]['assignee_id'])->toBe($this->user->id);

    actingAs($this->user);
    \Pest\Laravel\postJson(route('grp.tasks.import'), ['list' => "Fine\nBroken | nobody-called-this | soon-ish"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('list');

    $tasks = \App\Actions\Tasks\StoreStaffTasksFromList::run($this->user, $list);

    expect($tasks)->toHaveCount(3)
        ->and($tasks[0]->department)->toBe($department['value'])
        ->and($tasks[1]->assignee_id)->toBe($colleague->id)
        ->and($tasks[1]->due_at->toDateString())->toBe('2026-11-02');
});

test('supervisor writes in any staff task thread without joining and can subscribe', function () {
    $requester  = $this->user;
    $colleague  = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $supervisor = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $supervisorPosition = \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $supervisor->group_id)->where('code', 'like', '%-m')->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->value('id');
    \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $supervisor->id, 'job_position_id' => $supervisorPosition, 'group_id' => $supervisor->group_id, 'scopes' => '{}']);

    $task         = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Supervised task', 'assignee_id' => $requester->id]);
    $conversation = $task->conversation;

    actingAs($colleague);
    \Pest\Laravel\postJson(route('grp.chat.staff.conversations.messages.store', $conversation->ulid), ['body' => 'hi'])->assertForbidden();
    getJson(route('grp.tasks.conversation', $task->reference))->assertForbidden();

    actingAs($supervisor);
    getJson(route('grp.tasks.conversation', $task->reference))->assertOk()->assertJsonPath('data.ulid', $conversation->ulid);
    getJson(route('grp.chat.staff.conversations.messages.index', $conversation->ulid))->assertOk();
    \Pest\Laravel\postJson(route('grp.chat.staff.conversations.messages.store', $conversation->ulid), ['body' => 'please prioritise'])->assertCreated();

    expect($conversation->hasParticipant($supervisor))->toBeFalse();

    \Pest\Laravel\postJson(route('grp.tasks.subscription.toggle', $task->reference))->assertOk()->assertJsonPath('data.is_subscribed', true);
    expect($conversation->hasParticipant($supervisor))->toBeTrue();

    \Pest\Laravel\postJson(route('grp.tasks.subscription.toggle', $task->reference))->assertOk()->assertJsonPath('data.is_subscribed', false);
    expect($conversation->hasParticipant($supervisor))->toBeFalse();

    actingAs($requester);
    \Pest\Laravel\postJson(route('grp.tasks.subscription.toggle', $task->reference))->assertOk();
    expect($conversation->hasParticipant($requester))->toBeTrue();
});

test('staff task collaborators join the thread and see the task as theirs', function () {
    $requester = $this->user;
    $owner     = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $helper    = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();

    $task = \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Two person job', 'assignee_id' => $owner->id, 'collaborator_ids' => [$helper->id, $owner->id]]);

    expect($task->collaborators()->pluck('users.id')->all())->toBe([$helper->id])
        ->and($task->conversation->hasParticipant($helper))->toBeTrue()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($helper, 'mine')->pluck('id')->all())->toBe([$task->id]);

    actingAs($requester);
    \Pest\Laravel\patchJson(route('grp.tasks.collaborators.update', $task->reference), ['collaborator_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.collaborators', []);

    expect($task->conversation->isActiveParticipant($helper))->toBeFalse()
        ->and(\App\Actions\Tasks\Json\GetStaffTasks::run($helper, 'mine'))->toBeEmpty();

    \App\Actions\Tasks\SyncStaffTaskCollaborators::run($task, [$helper->id], $requester);
    \App\Actions\Tasks\UpdateStaffTask::run($task->fresh(), $requester, ['assignee_id' => $helper->id]);

    expect($task->collaborators()->count())->toBe(0);
});

test('staff task reports count a task for its assignee and for each collaborator', function () {
    $requester = $this->user;
    $people    = collect(range(1, 3))->map(fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser());

    \App\Actions\Tasks\StoreStaffTask::run($requester, ['subject' => 'Three person job', 'assignee_id' => $people[0]->id, 'collaborator_ids' => [$people[1]->id, $people[2]->id]]);

    $peopleReport = \App\Actions\Tasks\UI\ShowStaffTasksReports::make()->handle($this->organisation->group, $requester, '1w')['people'];
    $byRole       = collect($peopleReport)->map(fn ($rows) => collect($rows)->keyBy('id'));

    expect($byRole['assignee'][$people[0]->id]['created'])->toBe(1)
        ->and($byRole['assignee']->has($people[1]->id))->toBeFalse()
        ->and($byRole['collaborator'][$people[1]->id]['created'])->toBe(1)
        ->and($byRole['collaborator'][$people[2]->id]['created'])->toBe(1)
        ->and($byRole['collaborator']->has($people[0]->id))->toBeFalse();

    foreach ($people as $person) {
        expect($byRole['involved'][$person->id]['created'])->toBe(1);
    }
});

test('an organisation holds the tasks its staff raised, own or help on, and no one else\'s', function () {
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $insider      = $newColleague();
    $outsider     = $newColleague();

    $employee = Employee::factory()->create(['group_id' => $this->organisation->group_id, 'organisation_id' => $this->organisation->id]);
    \Illuminate\Support\Facades\DB::table('user_has_models')->insert(['group_id' => $this->organisation->group_id, 'organisation_id' => $this->organisation->id, 'user_id' => $insider->id, 'model_type' => 'Employee', 'model_id' => $employee->id]);

    $raised  = \App\Actions\Tasks\StoreStaffTask::run($insider, ['subject' => 'Raised by our staff', 'assignee_id' => $outsider->id]);
    $owned   = \App\Actions\Tasks\StoreStaffTask::run($outsider, ['subject' => 'Owned by our staff', 'assignee_id' => $insider->id]);
    $helped  = \App\Actions\Tasks\StoreStaffTask::run($outsider, ['subject' => 'Helped by our staff', 'assignee_id' => $outsider->id, 'collaborator_ids' => [$insider->id]]);
    $foreign = \App\Actions\Tasks\StoreStaffTask::run($outsider, ['subject' => 'Not ours', 'assignee_id' => $outsider->id]);

    $otherOrganisation = (new \App\Models\SysAdmin\Organisation())->forceFill(['id' => 0, 'group_id' => $this->organisation->group_id]);
    $tasksIn           = fn ($parent) => \App\Models\Tasks\StaffTask::query()->within($parent)->whereIn('id', [$raised->id, $owned->id, $helped->id, $foreign->id])->orderBy('id')->pluck('id')->all();

    expect($tasksIn($this->organisation))->toBe([$raised->id, $owned->id, $helped->id])
        ->and($tasksIn($this->organisation->group))->toBe([$raised->id, $owned->id, $helped->id, $foreign->id])
        ->and($tasksIn($otherOrganisation))->toBe([]);

    actingAs($this->user);
    get(route('grp.org.tasks.list_all', $this->organisation->slug))->assertOk();
    get(route('grp.org.tasks.board', $this->organisation->slug))->assertOk();
    get(route('grp.org.tasks.reports', $this->organisation->slug))->assertOk();
});

test('staff see the tasks they raised, own, help on or were sent to their department, supervisors and engineers see them all', function () {
    $groupId       = $this->organisation->group_id;
    $newColleague  = fn (array $positions = []) => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => $positions]))->getUser();
    $givePosition  = fn (\App\Models\SysAdmin\User $user, ?int $jobPositionId) => \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $user->id, 'job_position_id' => $jobPositionId, 'group_id' => $groupId, 'scopes' => '{}']);
    $viewer        = $newColleague();
    $stranger      = $newColleague();
    $supervisor    = $newColleague();
    $engineer      = $newColleague([['slug' => 'gp-hd', 'scopes' => []]]);
    $helpDeskLead  = $newColleague();

    $givePosition($viewer, \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $groupId)->where('department', 'warehouse')->where('code', 'not like', '%-m')->value('id'));
    $givePosition($supervisor, \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $groupId)->where('code', 'like', '%-m')->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->value('id'));
    $givePosition($helpDeskLead, \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $groupId)->where('department', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->where('code', 'like', '%-m')->value('id'));
    $givePosition($helpDeskLead, \Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $groupId)->where('department', 'warehouse')->where('code', 'not like', '%-m')->value('id'));
    $otherDepartment = collect(\App\Models\Tasks\StaffTask::departments($groupId))->pluck('value')->first(fn (string $department) => $department !== 'warehouse');

    $tasks = collect([
        \App\Actions\Tasks\StoreStaffTask::run($viewer, ['subject' => 'Raised', 'assignee_id' => $stranger->id]),
        \App\Actions\Tasks\StoreStaffTask::run($stranger, ['subject' => 'Owned', 'assignee_id' => $viewer->id]),
        \App\Actions\Tasks\StoreStaffTask::run($stranger, ['subject' => 'Helped', 'assignee_id' => $stranger->id, 'collaborator_ids' => [$viewer->id]]),
        \App\Actions\Tasks\StoreStaffTask::run($stranger, ['subject' => 'To my department', 'department' => 'warehouse']),
        \App\Actions\Tasks\StoreStaffTask::run($stranger, ['subject' => 'To another department', 'department' => $otherDepartment]),
        \App\Actions\Tasks\StoreStaffTask::run($stranger, ['subject' => 'Between others', 'assignee_id' => $stranger->id]),
    ]);
    $taskIds = $tasks->pluck('id');

    $visibleTo = fn (\App\Models\SysAdmin\User $user) => \App\Models\Tasks\StaffTask::query()->within($this->organisation->group)->visibleTo($user)->whereIn('id', $taskIds)->orderBy('id')->pluck('id')->all();

    expect($visibleTo($viewer))->toBe($taskIds->take(4)->all())
        ->and($visibleTo($supervisor))->toBe($taskIds->all())
        ->and($visibleTo($engineer))->toBe($taskIds->all())
        ->and($visibleTo($helpDeskLead))->toBe($taskIds->all());

    $board = collect(\App\Actions\Tasks\UI\ShowStaffTasksBoard::make()->handle($this->organisation->group, $viewer, 'all'))->flatMap(fn (array $column) => array_column($column['tasks'], 'reference'));
    expect($board)->toContain($tasks->first()->reference)
        ->not->toContain($tasks->last()->reference);

    actingAs($viewer);
    \Pest\Laravel\patchJson(route('grp.tasks.update', $tasks->last()->reference), ['priority' => 'urgent'])->assertForbidden();
    \Pest\Laravel\patchJson(route('grp.tasks.collaborators.update', $tasks->last()->reference), ['collaborator_ids' => [$viewer->id]])->assertForbidden();
    \Pest\Laravel\patchJson(route('grp.tasks.update', $tasks[3]->reference), ['status' => 'in_progress'])->assertOk()->assertJsonPath('data.assignee.id', $viewer->id);

    actingAs($supervisor);
    \Pest\Laravel\patchJson(route('grp.tasks.update', $tasks->last()->reference), ['priority' => 'urgent'])->assertOk();
});

test('a change to a live page becomes a line on its shop review task for the shopkeeper, or the webmasters when the shopkeeper made it, and publishing ticks it off', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastStaffTaskBadgeUpdate::class, \App\Events\BroadcastStaffTaskChanged::class]);
    $newColleague = fn () => \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
    $shopkeeper   = $newColleague();
    $changer      = $newColleague();
    $settings     = $this->shop->settings;
    data_set($settings, 'catalog.shopkeeper_in_charge_id', $shopkeeper->id);
    $this->shop->update(['settings' => $settings]);

    \App\Models\Tasks\StaffTask::open()->where('data->kind', 'webpage_review')->where('data->shop_id', $this->shop->id)->update(['status' => 'cancelled', 'closed_at' => now()]);
    [$home, $other] = \App\Models\Web\Webpage::where('shop_id', $this->shop->id)->orderBy('id')->take(2)->get()->all();
    \App\Models\Web\Webpage::whereIn('id', [$home->id, $other->id])->update(['state' => \App\Enums\Web\Webpage\WebpageStateEnum::LIVE->value]);

    $task = \App\Actions\Web\Webpage\AskWebEditorsToReviewWebpage::run($home->fresh(), $changer);
    \App\Actions\Web\Webpage\AskWebEditorsToReviewWebpage::run($home->fresh(), $changer);
    \App\Actions\Web\Webpage\AskWebEditorsToReviewWebpage::run($other->fresh(), $shopkeeper);
    $task->refresh();

    expect($task->requester->username)->toBe(\App\Actions\Tasks\GetAikuAssistant::USERNAME)
        ->and($task->requester->status)->toBeFalse()
        ->and($task->isReview())->toBeTrue()
        ->and($task->assignee_id)->toBe($shopkeeper->id)
        ->and(collect($task->data['subtasks'])->pluck('status')->all())->toBe(['todo', 'todo']);

    $badges = \App\Actions\Tasks\GetStaffTaskBadgeData::run($shopkeeper);
    expect($badges['review']['open'])->toBe(2)
        ->and(collect($badges['review']['tasks'])->pluck('reference'))->toContain($task->reference)
        ->and($badges['mine']['todo']['count'])->toBe(0);

    actingAs($shopkeeper);
    get(route('grp.tasks.review'))->assertInertia(fn (AssertableInertia $page) => $page->where('title', 'To review & publish')->where('data.data.0.reference', $task->reference)->where('data.data.0.lines', '2 / 2'));
    get(route('grp.tasks.list_all', ['filter' => ['assignee' => 'all']]))->assertInertia(fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => !collect($rows)->contains('reference', $task->reference)));

    \App\Actions\Web\Webpage\AskWebEditorsToReviewWebpage::make()->tickPublished($home->fresh());
    expect(collect($task->fresh()->data['subtasks'])->pluck('status')->all())->toBe(['done', 'todo'])
        ->and($task->fresh()->isOpen())->toBeTrue();

    \App\Actions\Web\Webpage\AskWebEditorsToReviewWebpage::make()->tickPublished($other->fresh());
    expect($task->fresh()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::DONE);

    $byShopkeeper = \App\Actions\Web\Webpage\AskWebEditorsToReviewWebpage::run($home->fresh(), $shopkeeper);
    expect($byShopkeeper->id)->not->toBe($task->id)
        ->and($byShopkeeper->assignee_id)->toBeNull()
        ->and($byShopkeeper->department)->toBe('webmaster');
});

test('daily sales tasks go to the shopkeeper in charge, or the webmasters, quietly, and replace the ones nobody started', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastStaffTaskBadgeUpdate::class, \App\Events\BroadcastStaffTaskChanged::class]);
    \Illuminate\Support\Facades\Notification::fake();
    $groupId    = $this->organisation->group_id;
    $shopkeeper = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, \App\Models\SysAdmin\Guest::factory()->definition())->getUser();
    $settings   = $this->shop->settings;
    data_set($settings, 'catalog.shopkeeper_in_charge_id', $shopkeeper->id);
    $this->shop->update(['settings' => $settings]);

    $customer = createCustomer($this->shop);
    $customer->update(['email' => 'buyer-'.Str::random(6).'@customer-'.Str::lower(Str::random(6)).'.test']);
    $basket   = \App\Actions\Ordering\Order\StoreOrder::make()->action($customer, [
        'reference'        => 'BASKET-'.Str::random(6),
        'date'             => now()->toDateString(),
        'customer_id'      => $customer->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    \Illuminate\Support\Facades\DB::table('orders')->where('id', $basket->id)->update(['state' => 'creating', 'net_amount' => 1000000, 'org_net_amount' => 1000000, 'updated_by_customer_at' => now()->subDays(2), 'deleted_at' => null]);

    $staffCustomer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, [...\App\Models\CRM\Customer::factory()->definition(), 'email' => $shopkeeper->email ?? 'staff-'.Str::random(6).'@'.Str::after((string) $this->shop->email, '@')]);
    $staffBasket = \App\Actions\Ordering\Order\StoreOrder::make()->action($staffCustomer, [
        'reference'        => 'STAFF-'.Str::random(6),
        'date'             => now()->toDateString(),
        'customer_id'      => $staffCustomer->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    \Illuminate\Support\Facades\DB::table('orders')->where('id', $staffBasket->id)->update(['state' => 'creating', 'net_amount' => 2000000, 'org_net_amount' => 2000000, 'updated_by_customer_at' => now()->subDays(2), 'deleted_at' => null]);

    $first = \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop, withAi: false);
    $task  = \App\Models\Tasks\StaffTask::where('reference', $first['sales_open_baskets'])->firstOrFail();

    expect($task->assignee_id)->toBe($shopkeeper->id)
        ->and($task->department)->toBeNull()
        ->and($task->requester->username)->toBe(\App\Actions\Tasks\GetAikuAssistant::USERNAME)
        ->and($task->data['order_ids'][0])->toBe($basket->id)
        ->and($task->data['order_ids'])->not->toContain($staffBasket->id)
        ->and($task->description)->toContain($basket->reference)
        ->and(\App\Actions\Tasks\GetStaffTaskBadgeData::run($shopkeeper)['mine']['todo']['count'])->toBeGreaterThanOrEqual(1);
    \Illuminate\Support\Facades\Notification::assertNotSentTo($shopkeeper, \App\Notifications\StaffTaskNotification::class);

    \App\Actions\Tasks\GenerateDailySalesTasks::make()->notifyMorning(array_values(array_filter($first)));
    \Illuminate\Support\Facades\Notification::assertSentToTimes($shopkeeper, \App\Notifications\StaffTaskNotification::class, 1);
    \Illuminate\Support\Facades\Notification::assertSentTo($shopkeeper, \App\Notifications\StaffTaskNotification::class, fn ($notification) => str_contains($notification->title, 'sales task') && $notification->toArray($shopkeeper)['route'] === route('grp.tasks.index'));

    if (!\Illuminate\Support\Facades\DB::table('job_positions')->where('group_id', $groupId)->where('department', 'webmaster')->exists()) {
        $webmaster = \App\Models\HumanResources\JobPosition::where('group_id', $groupId)->where('code', 'not like', '%-m')->orderBy('id')->firstOrFail()->replicate();
        $webmaster->fill(['code' => 'gp-wm-t', 'slug' => 'gp-wm-t-'.$groupId, 'name' => 'Webmaster test', 'department' => 'webmaster'])->save();
    }
    data_set($settings, 'catalog.shopkeeper_in_charge_id', null);
    $this->shop->update(['settings' => $settings]);

    $second   = \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop->fresh(), withAi: false);
    $fallback = \App\Models\Tasks\StaffTask::where('reference', $second['sales_open_baskets'])->firstOrFail();
    expect($task->fresh()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::CANCELLED)
        ->and($fallback->assignee_id)->toBeNull()
        ->and($fallback->department)->toBe('webmaster');
});

test('dropshipping sales tasks ask merchants how their store is going instead of treating them as wholesale buyers due to reorder', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastStaffTaskBadgeUpdate::class, \App\Events\BroadcastStaffTaskChanged::class]);
    $customer = createCustomer($this->shop);
    $customer->update(['email' => 'buyer-'.Str::random(6).'@customer-'.Str::lower(Str::random(6)).'.test']);
    $basket   = \App\Actions\Ordering\Order\StoreOrder::make()->action($customer, [
        'reference'        => 'DSBASKET-'.Str::random(6),
        'date'             => now()->toDateString(),
        'customer_id'      => $customer->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    \Illuminate\Support\Facades\DB::table('orders')->where('id', $basket->id)->update(['state' => 'creating', 'net_amount' => 3000000, 'org_net_amount' => 3000000, 'updated_by_customer_at' => now()->subDays(2), 'deleted_at' => null]);

    $merchant = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, [...\App\Models\CRM\Customer::factory()->definition(), 'email' => 'merchant-'.Str::random(6).'@store-'.Str::lower(Str::random(6)).'.test']);
    $channel  = \App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel::make()->action($merchant, \App\Models\Dropshipping\Platform::where('type', \App\Enums\Ordering\Platform\PlatformTypeEnum::WOOCOMMERCE)->firstOrFail(), ['reference' => 'broken-'.Str::random(6)]);
    \Illuminate\Support\Facades\DB::table('customer_sales_channels')->where('id', $channel->id)->update(['status' => 'open', 'can_connect_to_platform' => false, 'connection_failure' => 'firewall', 'last_order_created_at' => now()->subDays(5)]);
    $merchantBasket = \App\Actions\Ordering\Order\StoreOrder::make()->action($merchant, [
        'reference'        => 'MBASKET-'.Str::random(6),
        'date'             => now()->toDateString(),
        'customer_id'      => $merchant->id,
        'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
    ]);
    \Illuminate\Support\Facades\DB::table('orders')->where('id', $merchantBasket->id)->update(['state' => 'creating', 'net_amount' => 4000000, 'org_net_amount' => 4000000, 'updated_by_customer_at' => now()->subDays(2), 'deleted_at' => null]);

    $prompts = [];
    \App\Actions\Helpers\AI\AskToAi::shouldRun()->andReturnUsing(function (string $prompt) use (&$prompts) {
        $prompts[] = $prompt;

        return '{}';
    });
    $basketPrompt = function () use (&$prompts) {
        return collect($prompts)->first(fn (string $prompt) => str_contains($prompt, 'order left open') || str_contains($prompt, 'filled a basket'));
    };

    $originalType = $this->shop->type;
    $this->shop->update(['type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::DROPSHIPPING]);
    $created = \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop->fresh());
    $this->shop->update(['type' => $originalType]);

    $broken = \App\Models\Tasks\StaffTask::where('reference', $created['sales_broken_channels'])->firstOrFail();
    expect($broken->data['customer_sales_channel_ids'])->toContain($channel->id)
        ->and($broken->description)->toContain('Blocked by a firewall')
        ->and(\App\Models\Tasks\StaffTask::where('reference', $created['sales_open_baskets'])->firstOrFail()->data['order_ids'])->not->toContain($merchantBasket->id)
        ->and(collect($prompts)->contains(fn (string $prompt) => str_contains($prompt, 'stopped syncing')))->toBeTrue();

    expect(\App\Models\Tasks\StaffTask::where('reference', $created['sales_open_baskets'])->firstOrFail()->description)->toContain('waiting for their customer')
        ->and($basketPrompt())->toContain('dropshipping supplier')->toContain('Never mention order intervals')->not->toContain('basket_amount')->not->toContain('wholesale');

    $prompts = [];
    $wholesale = \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop->fresh());
    expect($wholesale['sales_broken_channels'])->toBeNull()
        ->and($basketPrompt())->toContain('a wholesale business')->toContain('basket_amount')->not->toContain('dropshipping');
});

test('dropshipping open baskets list each merchant once with all their orders, wholesale one line per basket, and a merchant checked in with is left off the check-in lists for a month', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastStaffTaskBadgeUpdate::class, \App\Events\BroadcastStaffTaskChanged::class]);
    $merchant = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, [...\App\Models\CRM\Customer::factory()->definition(), 'email' => 'merchant-'.Str::random(6).'@store-'.Str::lower(Str::random(6)).'.test']);
    $openBasket = function (int $amount, ?\App\Models\CRM\Customer $customer = null) use ($merchant) {
        $customer ??= $merchant;
        $order = \App\Actions\Ordering\Order\StoreOrder::make()->action($customer, [
            'reference'        => 'GRP-'.Str::random(6),
            'date'             => now()->toDateString(),
            'customer_id'      => $customer->id,
            'delivery_address' => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
            'billing_address'  => new \App\Models\Helpers\Address(\App\Models\Helpers\Address::factory()->definition()),
        ]);
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update(['state' => 'creating', 'net_amount' => $amount, 'org_net_amount' => $amount, 'updated_by_customer_at' => now()->subDays(2), 'deleted_at' => null]);

        return $order;
    };
    $first  = $openBasket(9000000);
    $second = $openBasket(8000000);

    $originalType = $this->shop->type;
    $this->shop->update(['type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::DROPSHIPPING]);
    $created = \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop->fresh(), withAi: false);
    $task    = \App\Models\Tasks\StaffTask::where('reference', $created['sales_open_baskets'])->firstOrFail();

    expect($task->data['order_ids'])->toContain($first->id)->toContain($second->id)
        ->and($task->data['customer_ids'])->toContain($merchant->id)
        ->and(substr_count($task->description, '('.$merchant->reference.')'))->toBe(1)
        ->and($task->description)->toContain($first->reference.', '.$second->reference);

    $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE]);
    $third = $openBasket(9500000);
    $next  = \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop->fresh(), withAi: false);
    $this->shop->update(['type' => $originalType]);

    expect($next['sales_open_baskets'] ? \App\Models\Tasks\StaffTask::where('reference', $next['sales_open_baskets'])->firstOrFail()->data['order_ids'] : [])->not->toContain($third->id);

    $shopper = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, [...\App\Models\CRM\Customer::factory()->definition(), 'email' => 'shopper-'.Str::random(6).'@shop-'.Str::lower(Str::random(6)).'.test']);
    $fifth  = $openBasket(9600000, $shopper);
    $sixth  = $openBasket(9700000, $shopper);

    $wholesale = \App\Models\Tasks\StaffTask::where('reference', \App\Actions\Tasks\GenerateDailySalesTasks::make()->handle($this->shop->fresh(), withAi: false)['sales_open_baskets'])->firstOrFail();
    expect($wholesale->data['order_ids'])->toContain($fifth->id)->toContain($sixth->id)
        ->and(substr_count($wholesale->description, '('.$shopper->reference.')'))->toBe(2);
});

test('a department task raised for one organisation reaches only that organisation\'s people in the department', function () {
    $groupId  = $this->organisation->group_id;
    $position = \App\Models\HumanResources\JobPosition::where('organisation_id', $this->organisation->id)->whereNotNull('department')->where('department', '!=', \App\Models\Tasks\StaffTask::EXCLUDED_DEPARTMENT)->where('code', 'not like', '%-m')->firstOrFail();
    $member   = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, \App\Models\SysAdmin\Guest::factory()->definition())->getUser();
    $outsider = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, \App\Models\SysAdmin\Guest::factory()->definition())->getUser();
    foreach ([$member, $outsider] as $user) {
        \Illuminate\Support\Facades\DB::table('user_has_pseudo_job_positions')->insert(['user_id' => $user->id, 'job_position_id' => $position->id, 'group_id' => $groupId, 'scopes' => '{}']);
    }
    \Illuminate\Support\Facades\DB::table('user_has_authorised_models')->insert(['user_id' => $member->id, 'model_type' => 'Organisation', 'model_id' => $this->organisation->id, 'org_id' => $this->organisation->id, 'created_at' => now(), 'updated_at' => now()]);

    $task = \App\Actions\Tasks\StoreStaffTask::run(\App\Actions\Tasks\GetAikuAssistant::run($groupId), ['subject' => 'For one organisation', 'department' => $position->department, 'data' => ['organisation_id' => $this->organisation->id]]);
    $sees = fn (\App\Models\SysAdmin\User $user) => \App\Models\Tasks\StaffTask::query()->visibleTo($user)->whereKey($task->id)->exists();

    expect(\App\Models\Tasks\StaffTask::seesEveryTask($outsider))->toBeFalse()
        ->and($sees($member))->toBeTrue()
        ->and($sees($outsider))->toBeFalse();
});
