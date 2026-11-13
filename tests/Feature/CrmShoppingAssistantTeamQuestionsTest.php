<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\ShoppingAssistant\AlertShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\CloseUnansweredShoppingAssistantTeamQuestions;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantTools;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantTeamQuestion;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();

    config(['services.openrouter.api_key' => 'test']);
    actingAs($this->user);
    $tz = $this->organisation->timezone?->name ?? 'UTC';
    Carbon::setTestNow(CarbonImmutable::parse('next wednesday 10:00', $tz));
    Queue::fake();

    $this->person = $this->user;
    $this->backup = teamQuestionStaff($this);
    UpdateOrganisation::make()->action($this->organisation, ['assistant_questions_user_id' => $this->person->id, 'assistant_questions_backup_user_id' => $this->backup->id]);
    $this->organisation->refresh();
});

afterEach(function () {
    UpdateOrganisation::make()->action($this->organisation->refresh(), ['assistant_questions_user_id' => null, 'assistant_questions_backup_user_id' => null]);
    Carbon::setTestNow();
});

function teamQuestionStaff(object $test): User
{
    return StoreGuest::make()->action($test->organisation->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
}

function teamQuestionConversation(object $test): ShoppingAssistantConversation
{
    $customer = StoreCustomer::make()->action($test->shop, Customer::factory()->definition());

    return StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH);
}

test('a question our texts do not answer goes to the question person now and to the backup later, once per conversation and at most three a day per customer', function () {
    $conversation = teamQuestionConversation($this);

    $first  = StoreShoppingAssistantTeamQuestion::run($conversation, 'Can I order samples of the fragrance oils?', 'samples of fragrance oils?');
    $second = StoreShoppingAssistantTeamQuestion::run($conversation, 'Samples?', 'samples?');

    expect($first['status'])->toBe('created')
        ->and($first['live'])->toBeTrue()
        ->and($first['task']->assignee_id)->toBe($this->person->id)
        ->and(data_get($first['task']->data, 'question'))->toBe('Can I order samples of the fragrance oils?')
        ->and($second['status'])->toBe('already_asked')
        ->and(StoreShoppingAssistantTeamQuestion::isAwaited($first['task']))->toBeTrue();

    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments) => $arguments[1]->id === $this->person->id);
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[1]->id === $this->backup->id && $job->delay !== null);

    $customer = $conversation->customer;
    foreach (range(1, 2) as $ignored) {
        AnswerShoppingAssistantTeamQuestion::run(StoreShoppingAssistantTeamQuestion::run(StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH), 'Q', 'Q')['task'], $this->person, 'A', false);
    }
    expect(StoreShoppingAssistantTeamQuestion::run(StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH), 'Q', 'Q')['status'])->toBe('limit');

    UpdateOrganisation::make()->action($this->organisation, ['assistant_questions_user_id' => null]);
    expect(StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Q', 'Q')['status'])->toBe('off');
});

test('the first answer reaches the customer conversation, closes the task and is kept for every customer unless unticked; a second answer is refused', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];
    $notesBefore  = ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count();

    expect(AnswerShoppingAssistantTeamQuestion::run($task, $this->backup, 'Yes, 10ml samples of every oil, on request.'))->toBe('answered')
        ->and(AnswerShoppingAssistantTeamQuestion::run($task->refresh(), $this->person, 'No.'))->toBe('already_answered');

    $task->refresh();
    $last = $conversation->messages()->latest('id')->first();

    expect($last->body)->toContain('Yes, 10ml samples of every oil, on request.')
        ->and($last->flags)->toBe(['team_answer'])
        ->and($task->status)->toBe(StaffTaskStatusEnum::DONE)
        ->and(data_get($task->data, 'answered_by'))->toBe($this->backup->id)
        ->and(StoreShoppingAssistantTeamQuestion::openFor($conversation))->toBeNull()
        ->and(ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count())->toBe($notesBefore + 1)
        ->and(ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->latest('id')->first()->title)->toBe('Do you sell samples?');

    $private = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Can I have 20% off?', 'discount?')['task'];
    AnswerShoppingAssistantTeamQuestion::run($private, $this->person, 'For you, yes.', false);
    expect(ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count())->toBe($notesBefore + 1);
});

test('an answer written after the customer stopped waiting is also sent on, and marking the task done with a note answers it', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];
    Carbon::setTestNow(now()->addMinutes(StoreShoppingAssistantTeamQuestion::WAIT_MINUTES + 1));

    UpdateStaffTask::run($task->refresh(), $this->person, ['status' => StaffTaskStatusEnum::DONE->value, 'note' => 'Yes, on request.']);
    $task->refresh();

    expect(data_get($task->data, 'answer'))->toBe('Yes, on request.')
        ->and($conversation->messages()->latest('id')->first()->body)->toContain('Yes, on request.')
        ->and($task->conversation->messages()->pluck('body')->implode("\n"))->toContain('customer services was asked to send it');
});

test('only the question person or the backup may answer through the pop-up, and the first answer wins', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];
    $stranger     = teamQuestionStaff($this);

    expect(StoreShoppingAssistantTeamQuestion::canAnswer($task, $this->backup))->toBeTrue()
        ->and(StoreShoppingAssistantTeamQuestion::canAnswer($task, $stranger))->toBeFalse();

    actingAs($stranger)->postJson(route('grp.tasks.assistant_answer', $task->reference), ['answer' => 'No'])->assertForbidden();
    actingAs($this->backup)->postJson(route('grp.tasks.assistant_answer', $task->reference), ['answer' => 'Yes', 'keep_for_everyone' => false])->assertOk()->assertJson(['result' => 'answered']);
    actingAs($this->person)->postJson(route('grp.tasks.assistant_answer', $task->reference), ['answer' => 'No'])->assertStatus(409);
});

test('the ask_our_team tool passes the question on, and a reply Merlin was not sure of asks the team instead of only offering a person', function () {
    $conversation = teamQuestionConversation($this);
    $tool         = GetShoppingAssistantTools::make()->execute($conversation, 'ask_our_team', ['question' => 'Do you sell samples?']);

    expect($tool['error'])->toBeNull()
        ->and($tool['content']['passed_on'])->toBeTrue()
        ->and($tool['content']['answer_may_come_here'])->toBeTrue();

    $other = teamQuestionConversation($this);
    Http::fake(['*chat/completions' => Http::sequence()
        ->push(['choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
            'id' => 'call_present', 'type' => 'function', 'function' => ['name' => 'present_products', 'arguments' => json_encode(['reply' => 'Not sure.', 'answer_kind' => 'offer_person', 'offer_reason' => 'not_sure'])],
        ]]]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'cost' => 0.01]])]);

    $turn = AskShoppingAssistant::run($other, 'avete schede sds delle fragranze?');

    expect($turn['message']->flags)->toBe(['asked_team'])
        ->and($turn['reply'])->toBe(__('I don\'t know this one, so I have asked one of our experts. If someone is free in the next few minutes, their answer will appear here; otherwise we will get back to you as soon as we can.', [], $other->language_code))
        ->and(data_get(StoreShoppingAssistantTeamQuestion::openFor($other)->data, 'customer_words'))->toBe('avete schede sds delle fragranze?');
});

test('a guru who does not want to answer is named on the task and it moves to the backup at once; passed on, the colleague owns it, and when they do not want to answer either the task closes and the customer is told', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];

    expect(StoreShoppingAssistantTeamQuestion::waitingFor($this->person)->pluck('id'))->toContain($task->id)
        ->and(StoreShoppingAssistantTeamQuestion::waitingFor($this->backup)->pluck('id'))->not->toContain($task->id);

    actingAs($this->person)->postJson(route('grp.tasks.assistant_decline', $task->reference))->assertOk()->assertJson(['result' => 'to_backup']);
    $task->refresh();

    expect($task->assignee_id)->toBe($this->backup->id)
        ->and(data_get($task->data, 'declined_by'))->toBe([$this->person->id])
        ->and($task->conversation->messages()->pluck('body')->implode("\n"))->toContain('did not want to answer')
        ->and(StoreShoppingAssistantTeamQuestion::waitingFor($this->person)->pluck('id'))->not->toContain($task->id)
        ->and(StoreShoppingAssistantTeamQuestion::waitingFor($this->backup)->pluck('id'))->toContain($task->id);
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[1]->id === $this->backup->id && $job->delay === null);

    $colleague = teamQuestionStaff($this);
    actingAs($this->backup)->postJson(route('grp.tasks.assistant_pass', $task->reference), ['user_id' => $colleague->id])->assertOk();
    $task->refresh();

    expect($task->assignee_id)->toBe($colleague->id)
        ->and(StoreShoppingAssistantTeamQuestion::canAnswer($task, $colleague))->toBeTrue()
        ->and(collect(actingAs($colleague)->getJson(route('grp.tasks.assistant_questions', ['colleagues' => 1]))->assertOk()->json('questions'))->pluck('task'))->toContain($task->reference);
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments) => $arguments[1]->id === $colleague->id);

    actingAs($colleague)->postJson(route('grp.tasks.assistant_decline', $task->reference))->assertOk()->assertJson(['result' => 'closed']);
    $task->refresh();

    expect($task->status)->toBe(StaffTaskStatusEnum::CANCELLED)
        ->and(data_get($task->data, 'unanswered'))->toBeTrue()
        ->and($conversation->messages()->latest('id')->first()->flags)->toBe(['team_unanswered'])
        ->and(StoreShoppingAssistantTeamQuestion::openFor($conversation))->toBeNull()
        ->and(actingAs($colleague)->getJson(route('grp.tasks.assistant_questions'))->json('questions'))->toBe([]);
    actingAs($this->backup)->postJson(route('grp.tasks.assistant_answer', $task->reference), ['answer' => 'Too late'])->assertStatus(409);
});

test('a question goes to the guru when online, else to the backup online, and with nobody online it waits for the guru and the customer is not told to wait', function () {
    Illuminate\Support\Facades\Cache::put(StoreShoppingAssistantTeamQuestion::ONLINE_CACHE_KEY, [$this->backup->id], 30);
    $toBackup = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Do you sell samples?', 'Do you sell samples?');

    expect($toBackup['task']->assignee_id)->toBe($this->backup->id)
        ->and(data_get($toBackup['task']->data, 'routed_to'))->toBe('backup')
        ->and($toBackup['live'])->toBeTrue();
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[0]->is($toBackup['task']) && $arguments[1]->id === $this->backup->id && $job->delay === null);
    AlertShoppingAssistantTeamQuestion::assertPushed(1, fn ($action, array $arguments) => $arguments[0]->is($toBackup['task']));

    Illuminate\Support\Facades\Cache::put(StoreShoppingAssistantTeamQuestion::ONLINE_CACHE_KEY, [], 30);
    $nobody = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Do you sell samples?', 'Do you sell samples?');
    $routed = data_get($nobody['task']->data, 'routed_to');

    expect($routed)->toBeIn(['nobody_online', 'customer_services'])
        ->and($routed === 'customer_services' || ($nobody['task']->assignee_id === $this->person->id && $nobody['live'] === false))->toBeTrue();

    Illuminate\Support\Facades\Cache::forget(StoreShoppingAssistantTeamQuestion::ONLINE_CACHE_KEY);
});

test('a question nobody answered within a day is closed in working hours and the customer is told to contact customer services', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];

    Carbon::setTestNow(now()->addHours(2));
    expect(CloseUnansweredShoppingAssistantTeamQuestions::run())->toBe(0);

    Carbon::setTestNow(now()->addDay());
    expect(CloseUnansweredShoppingAssistantTeamQuestions::run())->toBeGreaterThanOrEqual(1);
    $task->refresh();

    expect($task->status)->toBe(StaffTaskStatusEnum::CANCELLED)
        ->and(data_get($task->data, 'unanswered'))->toBeTrue()
        ->and($conversation->messages()->latest('id')->first()->flags)->toBe(['team_unanswered'])
        ->and($task->conversation->messages()->pluck('body')->implode("\n"))->toContain('customer services was asked to send it');
});

test('the Merlin report counts the guru questions: answered, in time, not answered, by person with declines and passes, and the latest ones', function () {
    $website = createWebsite($this->shop);
    $filters = ['website_ids' => [$website->id], 'search_website_ids' => [$website->id], 'language' => null, 'entry_point' => null, 'shop_type' => null, 'country' => null];
    $report  = App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantReport::make();
    $before  = $report->gurus($filters, now()->subDay()->toImmutable(), now()->addDay()->toImmutable());

    $answered = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Do you sell samples?', 'Do you sell samples?')['task'];
    Carbon::setTestNow(now()->addMinutes(2));
    AnswerShoppingAssistantTeamQuestion::run($answered, $this->person, 'Yes, 10ml ones.');

    $declined = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Any discount code?', 'Any discount code?')['task'];
    actingAs($this->person)->postJson(route('grp.tasks.assistant_decline', $declined->reference))->assertOk();
    actingAs($this->backup)->postJson(route('grp.tasks.assistant_decline', $declined->reference))->assertOk();

    $gurus = $report->gurus($filters, now()->subDay()->toImmutable(), now()->addDay()->toImmutable());
    $me    = collect($gurus['people'])->firstWhere('name', $this->person->chatName());

    expect($gurus['asked'])->toBe($before['asked'] + 2)
        ->and($gurus['answered'])->toBe($before['answered'] + 1)
        ->and($gurus['answered_live'])->toBe($before['answered_live'] + 1)
        ->and($gurus['closed'])->toBe($before['closed'] + 1)
        ->and($me['answered'])->toBeGreaterThanOrEqual(1)
        ->and($me['declined'])->toBeGreaterThanOrEqual(1)
        ->and(collect($gurus['latest'])->pluck('status', 'reference')->only([$answered->reference, $declined->reference])->all())->toBe([$declined->reference => 'closed', $answered->reference => 'answered']);
});
