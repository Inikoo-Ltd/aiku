<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Chat\UpdateShopChatKnowledgeNote;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\ShoppingAssistant\AlertShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\CloseUnansweredShoppingAssistantTeamQuestions;
use App\Actions\CRM\ShoppingAssistant\DeclineShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantTools;
use App\Actions\CRM\ShoppingAssistant\ResearchShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\SearchShopKnowledge;
use App\Actions\CRM\ShoppingAssistant\SendShoppingAssistantMorningRound;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantTeamQuestion;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\MerlinQuestionWriteTool;
use App\Mcp\Tools\MerlinQuestionsTool;
use App\Models\Tasks\StaffTask;
use App\Notifications\ShoppingAssistantMorningRoundNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();

    config(['services.openrouter.api_key' => 'test', 'services.shopping_assistant.light_model' => null, 'services.shopping_assistant.plain_search' => false]);
    $this->notAnAnswerProbability = null;
    $this->followsFromEvidence    = null;
    $this->isFault                = null;
    Http::fake(['*alpha/decisions' => fn ($request) => Http::response(['answers' => ['answer' => ['noul' => match (true) {
        str_contains($request->body(), 'follow from the evidence')   => $this->followsFromEvidence,
        str_contains($request->body(), 'engineers must look at it') => $this->isFault,
        default                                                      => $this->notAnAnswerProbability,
    }]]])]);
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

test('a question our texts do not answer goes to the question person now and to the backup later, with one waiting task per customer and at most three tasks a day', function () {
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
    AnswerShoppingAssistantTeamQuestion::run($first['task'], $this->person, 'A', false);
    foreach (range(1, 2) as $ignored) {
        $next = StoreShoppingAssistantTeamQuestion::run(StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH), 'Q', 'Q');
        expect($next['status'])->toBe('created');
        AnswerShoppingAssistantTeamQuestion::run($next['task'], $this->person, 'A', false);
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
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[0]->is($toBackup['task']) && $arguments[1]->id === $this->person->id && $job->delay !== null);
    AlertShoppingAssistantTeamQuestion::assertPushed(2, fn ($action, array $arguments) => $arguments[0]->is($toBackup['task']));
    expect(StoreShoppingAssistantTeamQuestion::waitingFor($this->person)->pluck('id'))->not->toContain($toBackup['task']->id);
    Carbon::setTestNow(now()->addMinutes(StoreShoppingAssistantTeamQuestion::BACKUP_AFTER_MINUTES + 1));
    expect(StoreShoppingAssistantTeamQuestion::waitingFor($this->person)->pluck('id'))->toContain($toBackup['task']->id);

    Illuminate\Support\Facades\Cache::put(StoreShoppingAssistantTeamQuestion::ONLINE_CACHE_KEY, [], 30);
    $nobody = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Do you sell samples?', 'Do you sell samples?');
    $routed = data_get($nobody['task']->data, 'routed_to');

    expect($routed)->toBeIn(['nobody_online', 'customer_services'])
        ->and($routed === 'customer_services' || ($nobody['task']->assignee_id === $this->person->id && $nobody['live'] === false))->toBeTrue();

    Illuminate\Support\Facades\Cache::forget(StoreShoppingAssistantTeamQuestion::ONLINE_CACHE_KEY);
});

test('a shop guru is asked first and the organisation guru and backup are its backups, in that order', function () {
    $shopGuru = teamQuestionStaff($this);
    UpdateShop::make()->action($this->shop, ['assistant_questions_user_id' => $shopGuru->id]);

    $task = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Do you sell samples?', 'Do you sell samples?')['task'];

    expect($task->assignee_id)->toBe($shopGuru->id)
        ->and(StoreShoppingAssistantTeamQuestion::canAnswer($task, $this->person))->toBeTrue()
        ->and(StoreShoppingAssistantTeamQuestion::canAnswer($task, $this->backup))->toBeTrue();
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[1]->id === $shopGuru->id && $job->delay === null);
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[1]->id === $this->person->id && $job->delay !== null);
    AlertShoppingAssistantTeamQuestion::assertPushed(fn ($action, array $arguments, $job) => $arguments[1]->id === $this->backup->id && $job->delay !== null);

    actingAs($shopGuru)->postJson(route('grp.tasks.assistant_decline', $task->reference))->assertOk()->assertJson(['result' => 'to_backup']);
    expect($task->refresh()->assignee_id)->toBe($this->person->id);

    actingAs($this->person)->postJson(route('grp.tasks.assistant_decline', $task->reference))->assertOk()->assertJson(['result' => 'to_backup']);
    expect($task->refresh()->assignee_id)->toBe($this->backup->id);

    $held = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Any discount code?', 'Any discount code?')['task'];
    actingAs($this->backup)->postJson(route('grp.tasks.assistant_decline', $held->reference))->assertOk()->assertJson(['result' => 'kept']);
    expect($held->refresh()->assignee_id)->toBe($shopGuru->id)
        ->and(data_get($held->data, 'answered_at'))->toBeNull();

    UpdateOrganisation::make()->action($this->organisation, ['assistant_questions_user_id' => null]);
    expect(StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'Q', 'Q')['task']->assignee_id)->toBe($shopGuru->id);

    UpdateShop::make()->action($this->shop->refresh(), ['assistant_questions_user_id' => null]);
});

test('a question nobody answered is closed a day after the gurus first had it in their morning round, so a Friday evening question gets Monday, and a day after the managers were told when it went to them', function () {
    Notification::fake();
    $tz      = $this->organisation->timezone?->name ?? 'UTC';
    $friday  = CarbonImmutable::parse('next friday 18:00', $tz);
    Carbon::setTestNow($friday);
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];

    Carbon::setTestNow($friday->addDay()->setTime(10, 0));
    SendShoppingAssistantMorningRound::run();
    expect(data_get($task->refresh()->data, 'round_first_at'))->toBeNull();

    Carbon::setTestNow($friday->addDays(3)->setTime(8, 30));
    SendShoppingAssistantMorningRound::run();
    expect(data_get($task->refresh()->data, 'round_first_at'))->toBeNull();

    Carbon::setTestNow($friday->addDays(3)->setTime(9, 15));
    SendShoppingAssistantMorningRound::run();
    CloseUnansweredShoppingAssistantTeamQuestions::run();
    $task->refresh();
    expect(data_get($task->data, 'answered_at'))->toBeNull()
        ->and(data_get($task->data, 'rounds'))->toBe(1);

    Carbon::setTestNow($friday->addDays(4)->setTime(9, 0));
    CloseUnansweredShoppingAssistantTeamQuestions::run();
    expect(data_get($task->refresh()->data, 'answered_at'))->toBeNull();

    Carbon::setTestNow($friday->addDays(4)->setTime(10, 15));
    expect(CloseUnansweredShoppingAssistantTeamQuestions::isDue($task->refresh()))->toBeTrue();

    Carbon::setTestNow($friday->addDays(4)->setTime(9, 5));
    SendShoppingAssistantMorningRound::run();
    Carbon::setTestNow($friday->addDays(4)->setTime(10, 15));
    CloseUnansweredShoppingAssistantTeamQuestions::run();
    expect(data_get($task->refresh()->data, 'answered_at'))->toBeNull()
        ->and(data_get($task->data, 'managers_told_at'))->not->toBeNull();

    Carbon::setTestNow($friday->addDays(5)->setTime(9, 5));
    SendShoppingAssistantMorningRound::run();
    Carbon::setTestNow($friday->addDays(5)->setTime(10, 15));
    expect(CloseUnansweredShoppingAssistantTeamQuestions::run())->toBeGreaterThanOrEqual(1);
    $task->refresh();

    expect($task->status)->toBe(StaffTaskStatusEnum::CANCELLED)
        ->and(data_get($task->data, 'unanswered'))->toBeTrue()
        ->and($conversation->messages()->latest('id')->first()->flags)->toBe(['team_unanswered'])
        ->and($task->conversation->messages()->pluck('body')->implode("\n"))->toContain('customer services was asked to send it');
});

test('a question that never reached a morning round is closed after three days', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell testers?', 'testers?')['task'];

    Carbon::setTestNow(now()->addDay()->addHour());
    expect(CloseUnansweredShoppingAssistantTeamQuestions::isDue($task))->toBeFalse();

    Carbon::setTestNow(now()->addDays(2));
    expect(CloseUnansweredShoppingAssistantTeamQuestions::isDue($task))->toBeTrue();
});

test('the morning round sends each guru one message a day with their open questions and the customers their notes helped, and a question already in an earlier round goes to the managers', function () {
    Notification::fake();
    $settings = $this->organisation->settings;
    $this->organisation->update(['settings' => [...$settings, 'sales_leads' => ['manager_user_ids' => [$this->backup->id]]]]);

    $tz = $this->organisation->timezone?->name ?? 'UTC';
    Carbon::setTestNow(CarbonImmutable::parse('next tuesday 17:00', $tz));
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'Do you sell samples?')['task'];

    $note = UpdateShopChatKnowledgeNote::make()->handle($this->shop, null, ['title' => 'Outers', 'body' => 'An outer is the pack the price is for.'], $this->person);
    $conversation->messages()->create(['role' => ShoppingAssistantMessageRoleEnum::ASSISTANT, 'body' => 'An outer is a pack.', 'model' => 'test', 'sources' => [['id' => $note->id, 'kind' => 'note', 'source_type' => 'manual', 'source_id' => null, 'title' => 'Outers', 'url' => null]]]);

    Carbon::setTestNow(CarbonImmutable::parse('next wednesday 09:05', $tz));
    SendShoppingAssistantMorningRound::run();
    SendShoppingAssistantMorningRound::run();

    $forTask = fn (bool $forManager) => fn (ShoppingAssistantMorningRoundNotification $notification) => $notification->forManager === $forManager && $notification->tasks->contains('id', $task->id);
    Notification::assertSentToTimes($this->person, ShoppingAssistantMorningRoundNotification::class, 1);
    Notification::assertSentTo($this->person, ShoppingAssistantMorningRoundNotification::class, fn (ShoppingAssistantMorningRoundNotification $notification) => $forTask(false)($notification) && $notification->helpedCustomers === 1 && str_contains($notification->summary(), 'helped 1 customer'));
    Notification::assertSentTo($this->backup, ShoppingAssistantMorningRoundNotification::class, $forTask(false));
    Notification::assertNotSentTo($this->backup, ShoppingAssistantMorningRoundNotification::class, $forTask(true));
    expect(data_get($task->refresh()->data, 'rounds'))->toBe(1)
        ->and(data_get($task->data, 'round_first_at'))->not->toBeNull();

    Carbon::setTestNow(CarbonImmutable::parse('next thursday 09:05', $tz));
    SendShoppingAssistantMorningRound::run();

    Carbon::setTestNow(CarbonImmutable::parse('next friday 09:05', $tz));
    SendShoppingAssistantMorningRound::run();
    expect(Notification::sent($this->backup, ShoppingAssistantMorningRoundNotification::class)->filter($forTask(true)))->toHaveCount(1);

    Notification::assertSentTo($this->backup, ShoppingAssistantMorningRoundNotification::class, fn (ShoppingAssistantMorningRoundNotification $notification) => $forTask(true)($notification)
        && str_starts_with($notification->title(), 'Urgent')
        && str_contains($notification->summary(), 'still waiting')
        && str_contains($notification->summary(), 'asked '));
    expect(data_get($task->refresh()->data, 'rounds'))->toBe(3);

    $this->organisation->update(['settings' => $settings]);
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

test('a customer with a question waiting gets no second task: a different question from another conversation joins it, and the answer lands in the conversation they asked from last', function () {
    $conversation = teamQuestionConversation($this);
    $customer     = $conversation->customer;
    $first        = StoreShoppingAssistantTeamQuestion::run($conversation, 'Who is the legal manufacturer of the hemp backpack?', 'fabricant légal du sac ?');
    $tasksBefore  = StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)->where('data->customer_id', $customer->id)->count();

    $later  = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH);
    $joined = StoreShoppingAssistantTeamQuestion::run($later, 'Is there a list of products with complete compliance documents?', 'liste des produits conformes ?');
    $again  = StoreShoppingAssistantTeamQuestion::run($later, 'Is there a list of products with complete compliance documents?', 'liste des produits conformes ?');
    $task   = $first['task']->refresh();
    $popup  = StoreShoppingAssistantTeamQuestion::popup($task);

    expect($joined['status'])->toBe('already_asked')
        ->and($joined['task']->id)->toBe($task->id)
        ->and($again['status'])->toBe('already_asked')
        ->and(StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)->where('data->customer_id', $customer->id)->count())->toBe($tasksBefore)
        ->and(data_get($task->data, 'more_questions'))->toHaveCount(1)
        ->and(data_get($task->data, 'conversation_id'))->toBe($later->id)
        ->and($popup['question'])->toBe("1. Who is the legal manufacturer of the hemp backpack?\n2. Is there a list of products with complete compliance documents?")
        ->and($popup['customer_words'])->toBe("1. fabricant légal du sac ?\n2. liste des produits conformes ?")
        ->and($task->description)->toContain('The customer also asked: Is there a list of products with complete compliance documents?')
        ->and(StoreShoppingAssistantTeamQuestion::openFor($later)?->id)->toBe($task->id);

    AlertShoppingAssistantTeamQuestion::assertPushed(3);

    foreach (range(3, StoreShoppingAssistantTeamQuestion::MAX_QUESTIONS_PER_TASK) as $number) {
        expect(StoreShoppingAssistantTeamQuestion::run($later, "Question $number", "Question $number")['status'])->toBe('already_asked');
    }
    expect(StoreShoppingAssistantTeamQuestion::run($later, 'One too many', 'One too many')['status'])->toBe('limit');

    $notesBefore = ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count();
    AnswerShoppingAssistantTeamQuestion::run($task->refresh(), $this->person, 'Ancient Wisdom is the importer; there is no such list.');

    expect($later->messages()->latest('id')->first()->body)->toContain('Ancient Wisdom is the importer; there is no such list.')
        ->and($conversation->messages()->count())->toBe(0)
        ->and(ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count())->toBe($notesBefore + 1)
        ->and(ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->latest('id')->first()->title)->toStartWith('1. Who is the legal manufacturer of the hemp backpack? 2. Is there a list');
});

test('a reply that is a question back to us is refused: nothing reaches the customer or the shop notes, from the pop-up and from the done note', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'How can a customer change the quantity inside a bundle?', 'how to change the quantity of a bundle i create')['task'];
    $notesBefore  = ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count();

    $this->notAnAnswerProbability = 0.97;

    actingAs($this->person)->postJson(route('grp.tasks.assistant_answer', $task->reference), ['answer' => 'which customer is this? Can I have a link to the bundle.'])
        ->assertStatus(422)
        ->assertJson(['result' => 'not_an_answer', 'message' => AnswerShoppingAssistantTeamQuestion::notAnAnswerMessage()]);

    $task->refresh();
    expect(data_get($task->data, 'answered_at'))->toBeNull()
        ->and($task->status->isOpen())->toBeTrue()
        ->and($conversation->messages()->count())->toBe(0)
        ->and(ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->count())->toBe($notesBefore);

    UpdateStaffTask::run($task, $this->person, ['status' => StaffTaskStatusEnum::DONE->value, 'note' => 'which customer is this?']);
    $task->refresh();

    expect(data_get($task->data, 'answered_at'))->toBeNull()
        ->and($conversation->messages()->count())->toBe(0)
        ->and($task->conversation->messages()->pluck('body')->implode("\n"))->toContain(AnswerShoppingAssistantTeamQuestion::notAnAnswerMessage());

    $this->notAnAnswerProbability = 0.4;
    expect(AnswerShoppingAssistantTeamQuestion::run($task, $this->person, 'Open the bundle on the My Bundles tab and edit its products.', false))->toBe('answered')
        ->and($conversation->messages()->latest('id')->first()->body)->toContain('Open the bundle on the My Bundles tab');
});

test('an answer written about the customer is reworded to speak to them, and goes as written when the rewording loses a figure or a link', function () {
    config(['services.shopping_assistant.light_model' => 'light-model']);
    $reworded = fn (string $text) => ['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $text]]], 'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20, 'cost' => 0.0001]];
    Http::fake(['*chat/completions' => Http::sequence()
        ->push($reworded('Yes, you will need to create another channel in Channels management.'))
        ->push($reworded('Delivery is about three pounds, see our shipping page.'))]);

    $first = teamQuestionConversation($this);
    AnswerShoppingAssistantTeamQuestion::run(StoreShoppingAssistantTeamQuestion::run($first, 'Can a customer connect several stores?', 'several stores?')['task'], $this->person, 'Yes, they will need to create another channel in Channels management.', false);

    $second  = teamQuestionConversation($this);
    $written = 'It is £2.99 + vat up to 2 kg, see https://www.aw-dropship.com/shipping';
    AnswerShoppingAssistantTeamQuestion::run(StoreShoppingAssistantTeamQuestion::run($second, 'What is the delivery charge?', 'delivery charge?')['task'], $this->person, $written, false);

    expect($first->messages()->latest('id')->first()->body)->toContain('Yes, you will need to create another channel')
        ->and($first->messages()->latest('id')->first()->body)->not->toContain('they will need')
        ->and($second->messages()->latest('id')->first()->body)->toContain($written);
});

test('a question that went to customer services still reaches the gurus: they see it once the backups are due, and the agent declining passes it to the first guru instead of closing it', function () {
    $agent = teamQuestionStaff($this);
    $task  = StoreShoppingAssistantTeamQuestion::run(teamQuestionConversation($this), 'What are outers?', 'what are outers')['task'];
    $task->update(['assignee_id' => $agent->id, 'data' => [...$task->data, 'routed_to' => 'customer_services']]);

    expect(StoreShoppingAssistantTeamQuestion::waitingFor($agent)->pluck('id'))->toContain($task->id)
        ->and(StoreShoppingAssistantTeamQuestion::waitingFor($this->person)->pluck('id'))->not->toContain($task->id);

    Carbon::setTestNow(now()->addMinutes(StoreShoppingAssistantTeamQuestion::BACKUP_AFTER_MINUTES + 1));

    expect(StoreShoppingAssistantTeamQuestion::waitingFor($this->person)->pluck('id'))->toContain($task->id)
        ->and(StoreShoppingAssistantTeamQuestion::waitingFor($this->backup)->pluck('id'))->toContain($task->id)
        ->and(DeclineShoppingAssistantTeamQuestion::run($task->refresh(), $agent))->toBe('to_backup')
        ->and($task->refresh()->assignee_id)->toBe($this->person->id)
        ->and(data_get($task->data, 'answered_at'))->toBeNull();
});

test('the researcher lists and claims the open questions so two runs never work the same one, and a drafted question waits for the guru with the draft in the pop-up until the customer asks more', function () {
    $this->user->update(['can_use_mcp_customer_service' => true]);
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Which courier delivers to Poland?', 'Jaki kurier?')['task'];
    $listed       = fn (string $run, bool $claim = false) => AikuServer::actingAs($this->user)->tool(MerlinQuestionsTool::class, ['run' => $run, 'claim' => $claim, 'limit' => 20])->assertOk();

    $listed('run-a', true)->assertSee($task->reference);
    $listed('run-b')->assertDontSee($task->reference);

    AikuServer::actingAs($this->user)->tool(MerlinQuestionWriteTool::class, ['action' => 'save_draft', 'reference' => $task->reference, 'run' => 'run-b', 'answer' => 'We send to Poland with DPD.', 'evidence' => 'shipments of shop plsk to PL in the last 90 days: DPD 754, DPP 13'])
        ->assertHasErrors(['Another run is working this question']);

    Carbon::setTestNow(now()->addMinutes(ResearchShoppingAssistantTeamQuestion::CLAIM_MINUTES + 1));
    $listed('run-b')->assertSee($task->reference);

    AikuServer::actingAs($this->user)->tool(MerlinQuestionWriteTool::class, ['action' => 'save_draft', 'reference' => $task->reference, 'run' => 'run-b', 'answer' => 'We send to Poland with DPD.', 'evidence' => 'shipments of shop plsk to PL in the last 90 days: DPD 754, DPP 13'])->assertOk();
    $task->refresh();

    expect(StoreShoppingAssistantTeamQuestion::popup($task))->toMatchArray(['draft' => 'We send to Poland with DPD.', 'draft_evidence' => 'shipments of shop plsk to PL in the last 90 days: DPD 754, DPP 13'])
        ->and(data_get($task->data, 'answered_at'))->toBeNull();
    $listed('run-c')->assertDontSee($task->reference);

    StoreShoppingAssistantTeamQuestion::run($conversation, 'Does the courier phone before delivery?', 'Czy kurier dzwoni?');
    $listed('run-c')->assertSee($task->reference);

    $this->user->update(['can_use_mcp_customer_service' => false]);
    AikuServer::actingAs($this->user)->tool(MerlinQuestionsTool::class, ['run' => 'run-c'])->assertHasErrors(['not enabled']);
});

test('the researcher answers only when an independent check is very sure the evidence proves it, else the answer stays a draft, and its answers are listed in the next morning round', function () {
    Notification::fake();
    $this->user->update(['can_use_mcp_customer_service' => true]);
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'What is an outer?', 'what is an outer')['task'];
    $evidence     = 'resources/js/Iris/Components/BlocksUtils/Prices.vue shows "/ outer" when product.units != 1';

    AikuServer::actingAs($this->user)->tool(MerlinQuestionWriteTool::class, ['action' => 'answer', 'reference' => $task->reference, 'run' => 'run-a', 'answer' => 'An outer is the pack the price is for.', 'evidence' => 'it is likely'])
        ->assertHasErrors();

    $this->followsFromEvidence = 0.6;
    AikuServer::actingAs($this->user)->tool(MerlinQuestionWriteTool::class, ['action' => 'answer', 'reference' => $task->reference, 'run' => 'run-a', 'answer' => 'An outer is the pack the price is for.', 'evidence' => $evidence])
        ->assertOk()->assertSee('drafted');
    expect(data_get($task->refresh()->data, 'answered_at'))->toBeNull()
        ->and(data_get($task->data, 'draft_answer'))->toBe('An outer is the pack the price is for.');

    $this->followsFromEvidence = 0.95;
    AikuServer::actingAs($this->user)->tool(MerlinQuestionWriteTool::class, ['action' => 'answer', 'reference' => $task->reference, 'run' => 'run-a', 'answer' => 'An outer is the pack the price is for.', 'evidence' => $evidence])
        ->assertOk()->assertSee('answered');
    $task->refresh();

    expect(data_get($task->data, 'answered_at'))->not->toBeNull()
        ->and(data_get($task->data, 'answered_by_researcher'))->toBeTrue()
        ->and(data_get($task->data, 'answer_evidence'))->toBe($evidence)
        ->and($conversation->messages()->latest('id')->first()->flags)->toBe(['team_answer']);

    $tz = $this->organisation->timezone?->name ?? 'UTC';
    Carbon::setTestNow(CarbonImmutable::parse('next thursday 09:05', $tz));
    SendShoppingAssistantMorningRound::run();

    Notification::assertSentTo($this->person, ShoppingAssistantMorningRoundNotification::class, fn (ShoppingAssistantMorningRoundNotification $notification) => $notification->researcherAnswers?->contains('id', $task->id) && str_contains($notification->summary(), 'An outer is the pack the price is for.'));
    expect(data_get($task->refresh()->data, 'researcher_answer_listed_at'))->not->toBeNull()
        ->and(ResearchShoppingAssistantTeamQuestion::toConfirm($this->organisation->id)->pluck('id'))->not->toContain($task->id);
});

test('a kept answer waits for a second person before Merlin uses it, and the morning round asks the other gurus, with a few of the agents\' answers waiting for a yes', function () {
    Notification::fake();
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell testers?', 'testers?')['task'];
    AnswerShoppingAssistantTeamQuestion::run($task, $this->person, 'Yes, testers are sold in packs of three.');

    $note = ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->where('created_by_user_id', $this->person->id)->latest('id')->first();
    $learned = ChatKnowledgeEntry::create(['group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id, 'kind' => 'learned', 'source_type' => 'learned', 'status' => 'proposed', 'title' => 'Samples', 'body' => 'We do not send free samples.', 'customers_count' => 4]);

    expect($note->status)->toBe('proposed')
        ->and(ChatKnowledgeEntry::forShop($this->shop)->whereKey($note->id)->exists())->toBeFalse()
        ->and(UpdateShopChatKnowledgeNote::awaitsDecisionBy($note, $this->person))->toBeFalse()
        ->and(UpdateShopChatKnowledgeNote::awaitsDecisionBy($note, $this->backup))->toBeTrue();

    $tz = $this->organisation->timezone?->name ?? 'UTC';
    Carbon::setTestNow(CarbonImmutable::parse('next thursday 09:05', $tz));
    SendShoppingAssistantMorningRound::run();

    Notification::assertSentTo($this->backup, ShoppingAssistantMorningRoundNotification::class, fn (ShoppingAssistantMorningRoundNotification $notification) => $notification->facts?->contains('id', $note->id) && $notification->facts->contains('id', $learned->id) && str_contains($notification->summary(), 'Still true?'));
    Notification::assertSentTo($this->person, ShoppingAssistantMorningRoundNotification::class, fn (ShoppingAssistantMorningRoundNotification $notification) => !$notification->facts?->contains('id', $note->id) && $notification->facts->contains('id', $learned->id));

    $note->update(['status' => 'active']);
    expect(ChatKnowledgeEntry::forShop($this->shop)->whereKey($note->id)->exists())->toBeTrue();
    $learned->delete();
});

test('the shop knowledge is searched in the customer\'s words and in English in one embeddings call, nearest first and each text once', function () {
    Http::fake(['*embeddings' => fn ($request) => Http::response(['data' => collect($request['input'])->values()->map(fn ($text, $index) => ['index' => $index, 'embedding' => array_fill(0, 1024, $index === 0 ? 0.1 : 0.2)])->all()])]);
    $entry = ChatKnowledgeEntry::create(['group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id, 'kind' => 'note', 'source_type' => 'manual', 'is_manual' => true, 'status' => 'active', 'title' => 'Manufacturer', 'body' => 'The manufacturer is on the label.', 'embedding' => array_fill(0, 1024, 0.2)]);

    $found = SearchShopKnowledge::run($this->shop, ['Comment savoir qui est le fabricant ?', 'Who is the manufacturer?']);

    expect($found->where('id', $entry->id))->toHaveCount(1)
        ->and($found->count())->toBeLessThanOrEqual(SearchShopKnowledge::LIMIT)
        ->and(Http::recorded(fn ($request) => str_contains($request->url(), 'embeddings')))->toHaveCount(1);
    $entry->delete();
});

test('with no other guru to say yes, a kept answer is used at once, and a researcher draft never reopens a question a guru answered meanwhile', function () {
    UpdateOrganisation::make()->action($this->organisation, ['assistant_questions_backup_user_id' => null]);
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell gift boxes?', 'gift boxes?')['task'];
    $stale        = StaffTask::find($task->id);

    AnswerShoppingAssistantTeamQuestion::run($task, $this->person, 'Yes, gift boxes are in the packaging section.');
    $note = ChatKnowledgeEntry::where('shop_id', $this->shop->id)->where('is_manual', true)->where('created_by_user_id', $this->person->id)->latest('id')->first();

    expect($note->status)->toBe('active')
        ->and(ResearchShoppingAssistantTeamQuestion::saveDraft($stale, $this->person, 'Maybe.', 'nothing found in the shop settings or pages'))->toBeFalse()
        ->and(data_get($task->refresh()->data, 'answered_at'))->not->toBeNull()
        ->and(data_get($task->data, 'draft_answer'))->toBeNull();
});

test('the researcher only reaches open questions of shops whose chats the user works, and a question the customer adds starts the closing clock again', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell display stands?', 'display stands?')['task'];
    $outsider     = teamQuestionStaff($this);
    $outsider->update(['can_use_mcp_customer_service' => true]);
    $outsider->syncRoles([]);
    App\Actions\SysAdmin\CleanUserCaches::make()->clearPermissionsCache($outsider);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    AikuServer::actingAs($outsider)->tool(MerlinQuestionsTool::class, ['run' => 'run-x', 'limit' => 20])->assertOk()->assertDontSee($task->reference);
    AikuServer::actingAs($outsider)->tool(MerlinQuestionWriteTool::class, ['action' => 'save_draft', 'reference' => $task->reference, 'run' => 'run-x', 'answer' => 'Yes.', 'evidence' => 'the catalogue has a display stands family with products in stock'])
        ->assertHasErrors(['Nothing was changed']);
    expect(data_get($task->refresh()->data, 'draft_answer'))->toBeNull();

    $task->update(['data' => [...$task->data, 'round_first_at' => now()->subHours(23)->toIso8601String(), 'managers_told_at' => now()->subHours(23)->toIso8601String()]]);
    Carbon::setTestNow(now()->addHours(2));
    StoreShoppingAssistantTeamQuestion::run($conversation, 'And do you sell shelf labels?', 'shelf labels?');
    $task->refresh();
    expect(data_get($task->data, 'round_first_at'))->toBeNull()
        ->and(data_get($task->data, 'managers_told_at'))->toBeNull()
        ->and(CloseUnansweredShoppingAssistantTeamQuestions::isDue($task))->toBeFalse();

    UpdateStaffTask::run($task, $this->person, ['status' => StaffTaskStatusEnum::CANCELLED->value, 'note' => 'Handled by phone']);
    $this->user->update(['can_use_mcp_customer_service' => true]);
    expect(ResearchShoppingAssistantTeamQuestion::make()->mayResearch($this->user, $task->refresh()))->toBeFalse();
});

test('an answer still holding a [[gap]] from a researcher draft is refused from the pop-up and the done note, and nothing reaches the customer', function () {
    $conversation = teamQuestionConversation($this);
    $task         = StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you send samples?', 'samples?')['task'];
    $messages     = $conversation->messages()->count();

    $this->postJson(route('grp.tasks.assistant_answer', $task->reference), ['answer' => 'Hello, thank you. [[Guru: state our samples policy.]]'])
        ->assertStatus(422)
        ->assertJsonPath('result', 'has_gap');

    UpdateStaffTask::run($task->refresh(), $this->person, ['status' => StaffTaskStatusEnum::DONE->value, 'note' => 'Hello. [[Guru: fill in]]']);

    expect(data_get($task->refresh()->data, 'answered_at'))->toBeNull()
        ->and($conversation->messages()->count())->toBe($messages)
        ->and($task->conversation->messages()->pluck('body')->implode("\n"))->toContain('double brackets');
});

test('a question that reports something of ours not working goes to the engineers as a MER ticket, not to a guru, and the customer is told its reference', function () {
    $conversation = teamQuestionConversation($this);
    $tasks        = StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)->count();

    $this->isFault = 0.92;
    $result        = StoreShoppingAssistantTeamQuestion::run($conversation, 'I uploaded a product to Allegro but the listing shows a different product photo.', 'the listing shows a different photo');

    expect($result['status'])->toBe('to_engineers')
        ->and($result['ticket']->type)->toBe(App\Enums\Helpers\Ticket\TicketTypeEnum::MERLIN)
        ->and($result['ticket']->customer_id)->toBe($conversation->customer_id)
        ->and($result['ticket']->description)->toContain('the listing shows a different photo')
        ->and(StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)->count())->toBe($tasks)
        ->and(App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantEngineerTicket::customerSentence($conversation, $result['ticket'], false))->toContain($result['ticket']->reference);

    $again = StoreShoppingAssistantTeamQuestion::run($conversation, 'Stock does not update in my Allegro store either.', 'stock not updating');
    expect($again['status'])->toBe('to_engineers')
        ->and($again['already_reported'])->toBeTrue()
        ->and($again['ticket']->id)->toBe($result['ticket']->id);

    $this->isFault = 0.3;
    expect(StoreShoppingAssistantTeamQuestion::run($conversation, 'Do you sell samples?', 'samples?')['status'])->toBe('created');

    $this->isFault = 0.95;
    $refused       = teamQuestionConversation($this);
    expect(StoreShoppingAssistantTeamQuestion::run($refused, 'My products do not sync to Shopify.', 'products not syncing', false)['status'])->toBe('created');

    $result['ticket']->delete();
});

test('an engineer can raise a MER ticket from a Merlin conversation with the ticket tool, tied to its customer and conversation', function () {
    $conversation = teamQuestionConversation($this);
    $engineer     = teamQuestionStaff($this);
    actingAsUserWithOnlyPermissions($engineer, ['help-desk.resolve']);

    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['subject' => 'Allegro shows another product photo', 'type' => 'merlin'])
        ->assertHasErrors();
    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['subject' => 'Allegro shows another product photo', 'type' => 'merlin', 'conversation' => '01NOTACONVERSATION00000000'])
        ->assertHasErrors(['No Merlin conversation']);

    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['subject' => 'Allegro shows another product photo', 'type' => 'merlin', 'conversation' => $conversation->ulid, 'tags' => ['allegro']])
        ->assertOk()->assertSee('MER-');

    $ticket = App\Models\Helpers\Ticket::where('source_type', 'ShoppingAssistantConversation')->where('source_id', $conversation->id)->latest('id')->first();
    expect($ticket->type)->toBe(App\Enums\Helpers\Ticket\TicketTypeEnum::MERLIN)
        ->and($ticket->customer_id)->toBe($conversation->customer_id)
        ->and($ticket->shop_id)->toBe($conversation->customer->shop_id)
        ->and($ticket->tags)->toContain('merlin', 'allegro');

    $ticket->delete();
    actingAs($this->user);
});
