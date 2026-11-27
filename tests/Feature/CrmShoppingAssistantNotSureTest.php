<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\MarkShoppingAssistantGuruFarewellSeen;
use App\Actions\CRM\ShoppingAssistant\SearchShopKnowledge;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantEngineerTicket;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use App\Mcp\Servers\AikuServer;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();

    config(['services.openrouter.api_key' => 'test', 'services.shopping_assistant.light_model' => null, 'services.shopping_assistant.premium_model' => null, 'services.shopping_assistant.plain_search' => false]);
    $this->followsFromEvidence = null;
    $this->isFault             = null;
    $this->aboutOneCustomer    = 0.05;
    Http::fake(['*alpha/decisions' => fn ($request) => Http::response(['answers' => ['answer' => ['noul' => match (true) {
        str_contains($request->body(), 'specific customer')          => $this->aboutOneCustomer,
        str_contains($request->body(), 'follow from the evidence')   => $this->followsFromEvidence,
        str_contains($request->body(), 'engineers must look at it') => $this->isFault,
        default                                                      => null,
    }]]])]);
    actingAs($this->user);
    $tz = $this->organisation->timezone?->name ?? 'UTC';
    Carbon::setTestNow(CarbonImmutable::parse('next wednesday 10:00', $tz));
    Queue::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

function merlinNotSureStaff(object $test): User
{
    return StoreGuest::make()->action($test->organisation->group, array_merge(Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]]))->getUser();
}

function merlinNotSureConversation(object $test): ShoppingAssistantConversation
{
    $customer = StoreCustomer::make()->action($test->shop, Customer::factory()->definition());

    return StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::SEARCH);
}

/**
 * @return array<string, mixed>
 */
function merlinNotSureReply(string $reply = '', string $callId = 'call_present'): array
{
    return ['choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
        'id' => $callId, 'type' => 'function', 'function' => ['name' => 'present_products', 'arguments' => json_encode(['reply' => $reply, 'answer_kind' => 'offer_person', 'offer_reason' => 'not_sure'])],
    ]]]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'cost' => 0.01]];
}

function merlinGuruTask(Customer $customer, User $assignee, array $data): StaffTask
{
    return StoreStaffTask::run(GetAikuAssistant::run($customer->group_id), [
        'subject'     => 'Question',
        'assignee_id' => $assignee->id,
        'notify'      => false,
        'data'        => ['kind' => 'shopping_assistant_team_question', 'customer_id' => $customer->id, ...$data],
    ]);
}

test('the shop knowledge is searched in the customer\'s words and in English in one embeddings call, nearest first and each text once', function () {
    Http::fake(['*embeddings' => fn ($request) => Http::response(['data' => collect($request['input'])->values()->map(fn ($text, $index) => ['index' => $index, 'embedding' => array_fill(0, 1024, $index === 0 ? 0.1 : 0.2)])->all()])]);
    $entry = ChatKnowledgeEntry::create(['group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id, 'kind' => 'note', 'source_type' => 'manual', 'is_manual' => true, 'status' => 'active', 'title' => 'Manufacturer', 'body' => 'The manufacturer is on the label.', 'embedding' => array_fill(0, 1024, 0.2)]);

    $found = SearchShopKnowledge::run($this->shop, ['Comment savoir qui est le fabricant ?', 'Who is the manufacturer?']);

    expect($found->where('id', $entry->id))->toHaveCount(1)
        ->and($found->count())->toBeLessThanOrEqual(SearchShopKnowledge::LIMIT)
        ->and(Http::recorded(fn ($request) => str_contains($request->url(), 'embeddings')))->toHaveCount(1);
    $entry->delete();
});

test('a question Merlin is not sure of that reports something of ours not working goes to the engineers as a MER ticket; any other offers a person and creates no task', function () {
    Http::fake(['*chat/completions' => Http::sequence()->push(merlinNotSureReply())->push(merlinNotSureReply())]);

    $faulty        = merlinNotSureConversation($this);
    $this->isFault = 0.92;
    $reported      = AskShoppingAssistant::run($faulty, 'I uploaded a product to Allegro but the listing shows a different product photo.');
    $ticket        = Ticket::where('type', TicketTypeEnum::MERLIN)->where('customer_id', $faulty->customer_id)->latest('id')->first();

    expect($reported['message']->flags)->toContain('reported_engineers')
        ->and($reported['message']->refresh()->hand_over_offer)->toBeNull()
        ->and($ticket->description)->toContain('the listing shows a different product photo')
        ->and($reported['reply'])->toContain($ticket->reference);

    $again = StoreShoppingAssistantEngineerTicket::make()->fromQuestion($faulty, 'Stock does not update in my Allegro store either.', 'stock not updating');
    expect($again['status'])->toBe('already_reported')
        ->and($again['ticket']->id)->toBe($ticket->id);

    $question      = merlinNotSureConversation($this);
    $this->isFault = 0.3;
    $offered       = AskShoppingAssistant::run($question, 'Do you sell samples of the selenite lamps?');

    expect($offered['message']->refresh()->hand_over_offer)->toBe(ShoppingAssistantHandOverReasonEnum::NOT_SURE)
        ->and($offered['message']->flags ?? [])->not->toContain('reported_engineers')
        ->and(StaffTask::where('data->customer_id', $question->customer_id)->count())->toBe(0);

    $ticket->delete();
});

test('an engineer can raise a MER ticket from a Merlin conversation with the ticket tool, tied to its customer and conversation', function () {
    $conversation = merlinNotSureConversation($this);
    $engineer     = merlinNotSureStaff($this);
    actingAsUserWithOnlyPermissions($engineer, ['help-desk.resolve']);

    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['subject' => 'Allegro shows another product photo', 'type' => 'merlin'])
        ->assertHasErrors();
    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['subject' => 'Allegro shows another product photo', 'type' => 'merlin', 'conversation' => '01NOTACONVERSATION00000000'])
        ->assertHasErrors(['No Merlin conversation']);

    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['subject' => 'Allegro shows another product photo', 'type' => 'merlin', 'conversation' => $conversation->ulid, 'tags' => ['allegro']])
        ->assertOk()->assertSee('MER-');

    $ticket = Ticket::where('source_type', 'ShoppingAssistantConversation')->where('source_id', $conversation->id)->latest('id')->first();
    expect($ticket->type)->toBe(TicketTypeEnum::MERLIN)
        ->and($ticket->customer_id)->toBe($conversation->customer_id)
        ->and($ticket->shop_id)->toBe($conversation->customer->shop_id)
        ->and($ticket->tags)->toContain('merlin', 'allegro')
        ->and(App\Http\Resources\Helpers\TicketResource::make($ticket)->resolve()['source']['url'])->toContain('/ai/shopping-assistant/conversations/'.$conversation->ulid);

    $ticket->update(['assignee_id' => $engineer->id]);
    $messages = $conversation->messages()->count();
    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'Fixed, please try again.', 'to_customer' => true, 'status' => 'pending_deploy'])
        ->assertHasErrors(['not held for a deployment']);
    AikuServer::actingAs($engineer)->tool(App\Mcp\Tools\TicketWriteTool::class, ['reference' => $ticket->reference, 'comment' => 'Fixed, please try again.', 'to_customer' => true])
        ->assertOk();
    expect($conversation->messages()->count())->toBe($messages + 1);

    StaffTask::where('data->ticket_id', $ticket->id)->delete();
    $ticket->delete();
    actingAs($this->user);
});

test('a comment staff send to the customer on a MER ticket reaches their Merlin conversation and is passed on to them; a plain public comment stays on the ticket', function () {
    $conversation = merlinNotSureConversation($this);
    $this->isFault = 0.92;
    $ticket        = StoreShoppingAssistantEngineerTicket::make()->fromQuestion($conversation, 'My eBay products do not reach my store.', 'products not reaching eBay')['ticket'];
    $messages      = $conversation->messages()->count();

    App\Actions\Helpers\Ticket\StoreTicketComment::make()->action($ticket, $this->user, ['body' => '@rodrigo can you check the eBay logs?']);
    expect($conversation->messages()->count())->toBe($messages);

    App\Actions\Helpers\Ticket\StoreTicketComment::make()->action($ticket, $this->user, ['body' => 'We closed your duplicate eBay channel. Please press Upload on the new products.', 'to_customer' => true]);

    $last  = $conversation->messages()->latest('id')->first();
    $relay = StaffTask::where('data->kind', 'shopping_assistant_restock_answer_relay')->where('data->ticket_id', $ticket->id)->sole();
    expect($conversation->messages()->count())->toBe($messages + 1)
        ->and($last->body)->toContain('Our team answered your question:', 'Please press Upload on the new products.')
        ->and($last->flags)->toContain('ticket_reply')
        ->and($relay->description)->toContain($ticket->reference, 'Please press Upload on the new products.');

    $relay->delete();
    $ticket->delete();
});

test('focus mode, chosen next to the search, drops Slack and push and ends by itself', function () {
    actingAs($this->user)->patch(route('grp.models.profile.update'), ['focus_minutes' => 60])->assertSessionHasNoErrors();
    $this->user->refresh();

    expect($this->user->isFocusing())->toBeTrue()
        ->and(\App\Enums\SysAdmin\User\UserNotificationEnum::SALES_LEAD->deliverTo($this->user))->not->toContain('slack')->not->toContain('browser');

    Carbon::setTestNow(now()->addMinutes(61));
    expect($this->user->isFocusing())->toBeFalse();
    Carbon::setTestNow();

    actingAs($this->user)->patch(route('grp.models.profile.update'), ['focus_minutes' => 45])->assertSessionHasErrors('focus_minutes');
    actingAs($this->user)->patch(route('grp.models.profile.update'), ['focus_minutes' => 0]);
    expect($this->user->refresh()->isFocusing())->toBeFalse();
});

test('an answer about one customer is never knowledge for every customer, and the check fails closed', function () {
    $this->aboutOneCustomer = 0.9;
    expect(\App\Actions\CRM\ShoppingAssistant\IsAboutOneCustomer::run('Contact', 'Write to buyer@example-shop.test'))->toBeTrue();

    $this->aboutOneCustomer = null;
    expect(\App\Actions\CRM\ShoppingAssistant\IsAboutOneCustomer::run('Samples', 'We do not sell samples.'))->toBeTrue();

    $this->aboutOneCustomer = 0.05;
    expect(\App\Actions\CRM\ShoppingAssistant\IsAboutOneCustomer::run('Samples', 'We do not sell samples. Ask care@ancientwisdom.biz.'))->toBeFalse();
});

test('the shop notes gurus kept from a customer\'s questions are deleted when the customer is anonymised, by note id and, for older tasks, by the question as title', function () {
    $customer = merlinNotSureConversation($this)->customer;
    $note     = fn (string $title) => ChatKnowledgeEntry::create(['group_id' => $this->shop->group_id, 'organisation_id' => $this->shop->organisation_id, 'shop_id' => $this->shop->id, 'kind' => 'note', 'source_type' => 'manual', 'is_manual' => true, 'status' => 'active', 'title' => $title, 'body' => 'We do not offer it.']);
    $byId     = $note('Do you sell gift wrapping?');
    merlinGuruTask($customer, $this->user, ['question' => 'Do you sell gift wrapping?', 'shop_id' => $this->shop->id, 'kept_note_id' => $byId->id]);
    merlinGuruTask($customer, $this->user, ['question' => 'Do you sell samples?', 'more_questions' => [['question' => 'And catalogues?']], 'shop_id' => $this->shop->id, 'kept_for_everyone' => true]);
    $byTitle  = $note('1. Do you sell samples? 2. And catalogues?');
    $other    = $note('Do you deliver to Malta?');

    \App\Actions\CRM\Customer\AnonymiseCustomer::make()->action($customer->refresh(), 'Erasure request', false);

    expect(ChatKnowledgeEntry::whereKey($byId->id)->exists())->toBeFalse()
        ->and(ChatKnowledgeEntry::whereKey($byTitle->id)->exists())->toBeFalse()
        ->and(ChatKnowledgeEntry::whereKey($other->id)->exists())->toBeTrue();
    $other->delete();
});

test('the Merlin report counts staff time from what people actually did: measured active time where the pop-up or inbox sent it, else five seconds plus typing, nothing for unanswered questions or AI messages, and saved conversations at the average reply', function () {
    $website = $this->shop->website ?? createWebsite($this->shop);
    $this->shop->refresh();
    Carbon::setTestNow(CarbonImmutable::parse('2025-02-03 10:00:00'));
    collect(range(1, 6))->each(function () {
        merlinNotSureConversation($this)->messages()->create(['role' => ShoppingAssistantMessageRoleEnum::ASSISTANT, 'body' => 'Orders over 150 get 10% off.', 'answer_kind' => 'shop_answer']);
    });
    $unanswered = merlinNotSureConversation($this);
    $answered   = merlinNotSureConversation($this);
    $handedOver = merlinNotSureConversation($this);
    Carbon::setTestNow();

    merlinGuruTask($unanswered->customer, $this->user, ['conversation_id' => $unanswered->id, 'question' => 'Do you sell samples?']);
    merlinGuruTask($answered->customer, $this->user, ['conversation_id' => $answered->id, 'question' => 'Do you sell gift wrapping?', 'answer' => 'We do not offer gift wrapping.', 'answer_seconds' => 1800]);

    $chat = \App\Models\Chat\ChatSession::create(['ulid' => (string) Str::ulid(), 'status' => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE, 'language_id' => 68, 'priority' => \App\Enums\CRM\Livechat\ChatPriorityEnum::NORMAL, 'shop_id' => $this->shop->id]);
    \App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver::create(['shopping_assistant_conversation_id' => $handedOver->id, 'chat_session_id' => $chat->id, 'reason' => 'asked_for_person', 'route' => 'web_chat', 'in_hours' => true, 'agent_available' => true, 'created_at' => now()]);
    $message = fn (array $data) => \App\Models\Chat\ChatMessage::create([...['chat_session_id' => $chat->id, 'message_type' => 'text', 'sender_type' => 'agent', 'is_read' => false], ...$data]);
    $message(['message_text' => 'Measured reply', 'metadata' => ['writing_seconds' => 900]]);
    $message(['message_text' => str_repeat('a', 2000)]);
    $message(['message_text' => str_repeat('b', 2000), 'is_ai_generated' => true]);
    $message(['message_text' => str_repeat('c', 2000), 'sender_type' => 'guest']);

    $filters = ['website_ids' => [$website->id], 'search_website_ids' => [$website->id], 'language' => null, 'entry_point' => null, 'shop_type' => null, 'country' => null];
    $time    = App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantReport::make()->staffTime($filters, CarbonImmutable::parse('2025-02-03')->startOfDay(), CarbonImmutable::parse('2025-02-03')->endOfDay());

    expect($time)->toBe(['saved_hours' => 0.9, 'guru_hours' => 0.5, 'hand_over_hours' => 0.4, 'net_hours' => 0.0]);
});

test('a staff reply sent from the inbox keeps the active time measured while it was written, clamped, and a customer message never does', function () {
    $chat = \App\Models\Chat\ChatSession::create(['ulid' => (string) Str::ulid(), 'status' => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::ACTIVE, 'language_id' => 68, 'priority' => \App\Enums\CRM\Livechat\ChatPriorityEnum::NORMAL, 'shop_id' => $this->shop->id]);

    $staff    = \App\Actions\Chat\ChatSession\SendChatMessage::make()->handle($chat, ['message_text' => 'We ship tomorrow.', 'message_type' => 'text', 'sender_type' => 'agent', 'active_time' => ['writing_seconds' => '42', 'typing_seconds' => 30]]);
    $long     = \App\Actions\Chat\ChatSession\SendChatMessage::make()->handle($chat, ['message_text' => 'We ship on Monday.', 'message_type' => 'text', 'sender_type' => 'agent', 'active_time' => ['writing_seconds' => 5000, 'typing_seconds' => 'x']]);
    $customer = \App\Actions\Chat\ChatSession\SendChatMessage::make()->handle($chat, ['message_text' => 'Thanks', 'message_type' => 'text', 'sender_type' => 'guest', 'sender_id' => null, 'active_time' => ['writing_seconds' => 42]]);

    expect(data_get($staff->refresh()->metadata, 'writing_seconds'))->toBe(42)
        ->and(data_get($staff->metadata, 'typing_seconds'))->toBe(30)
        ->and(data_get($long->refresh()->metadata, 'writing_seconds'))->toBe(\App\Actions\Chat\ChatSession\SendChatMessage::MAX_MEASURED_SECONDS)
        ->and(data_get($long->metadata, 'typing_seconds'))->toBe(0)
        ->and(data_get($customer->refresh()->metadata, 'writing_seconds'))->toBeNull();
});

test('when Merlin is not sure, what it did find is shown before the offer of a person only when Jev finds it follows from its tool results', function () {
    $draft = 'Orders usually leave our warehouse within a few working days of being placed.';
    $turn  = fn (Illuminate\Http\Client\ResponseSequence $sequence) => $sequence
        ->push(['choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
            'id' => 'call_times', 'type' => 'function', 'function' => ['name' => 'dispatch_times', 'arguments' => '{}'],
        ]]]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'cost' => 0.01]])
        ->push(merlinNotSureReply($draft));
    Http::fake(['*chat/completions' => $turn($turn(Http::sequence()))]);

    $kept = merlinNotSureConversation($this);
    $this->followsFromEvidence = 0.95;
    $first = AskShoppingAssistant::run($kept, 'How long until my order to Lisbon arrives?');

    $refused = merlinNotSureConversation($this);
    $this->followsFromEvidence = 0.2;
    $second = AskShoppingAssistant::run($refused, 'How long until my parcel to Porto gets there?');

    expect($first['message']->flags)->toContain('partial_answer')
        ->and($first['reply'])->toStartWith($draft."\n\n")
        ->and($first['message']->refresh()->hand_over_offer)->toBe(ShoppingAssistantHandOverReasonEnum::NOT_SURE)
        ->and($second['message']->flags)->toContain('partial_answer_refused')
        ->and($second['reply'])->not->toContain($draft);
});

test('a guru still named in the settings sees the farewell once, OK keeps it away, and a colleague who never was a guru never sees it', function () {
    $guru      = merlinNotSureStaff($this);
    $colleague = merlinNotSureStaff($this);
    $settings  = $this->organisation->settings;
    $this->organisation->update(['settings' => [...($settings ?? []), 'assistant_questions' => ['user_id' => null, 'backup_user_id' => $guru->id]]]);

    actingAs($guru)->getJson(route('grp.tasks.guru_farewell'))->assertOk()->assertJson(['farewell' => true]);
    actingAs($colleague)->getJson(route('grp.tasks.guru_farewell'))->assertOk()->assertJson(['farewell' => false]);

    actingAs($guru)->postJson(route('grp.tasks.guru_farewell.seen'))->assertOk();
    expect(data_get($guru->refresh()->settings, MarkShoppingAssistantGuruFarewellSeen::SEEN))->not->toBeNull();
    actingAs($guru)->getJson(route('grp.tasks.guru_farewell'))->assertOk()->assertJson(['farewell' => false]);

    $this->organisation->update(['settings' => $settings]);
    actingAs($this->user);
});
