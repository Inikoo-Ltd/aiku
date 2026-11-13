<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\AnonymiseCustomer;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantReport;
use App\Actions\CRM\ShoppingAssistant\SendShoppingAssistantUnmetNeeds;
use App\Actions\CRM\ShoppingAssistant\SendShoppingAssistantWeeklySummary;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\TagShoppingAssistantConversationChallenges;
use App\Actions\CRM\ShoppingAssistant\WipeShoppingAssistantConversations;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\SysAdmin\Group\UpdateGroupSettings;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use App\Notifications\ShoppingAssistantUnmetNeedsNotification;
use App\Notifications\ShoppingAssistantWeeklySummaryNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();

    config(['services.openrouter.api_key' => 'test']);
    actingAs($this->user);
});

afterEach(function () {
    UpdateGroupSettings::make()->action($this->organisation->group->refresh(), ['shopping_assistant_weekly_summary_user_ids' => [], 'shopping_assistant_retention_months' => null, 'shopping_assistant_unmet_needs_user_ids' => [], 'shopping_assistant_unmet_needs_slack_channel' => null]);
});

function insightsCustomer(object $test): Customer
{
    return StoreCustomer::make()->action($test->shop, Customer::factory()->definition());
}

function insightsWebsite(object $test): Website
{
    $website = createWebsite($test->shop);
    $website->update(['status' => true]);
    UpdateWebsite::make()->action($website, ['shopping_assistant_enabled' => true]);

    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($website->domain);

    return $website->refresh();
}

function insightsWebUser(Customer $customer): WebUser
{
    $id = uniqid();

    return StoreWebUser::make()->action($customer, ['username' => 'sai-'.$id, 'email' => 'sai-'.$id.'@example.com', 'password' => 'test'], sendRegistrationEmail: false);
}

/**
 * @param  array<int, array{0: string, 1: string}>  $turns  [role, body]
 */
function insightsConversation(Customer $customer, array $turns, ?string $at = null, ShoppingAssistantEntryPointEnum $entryPoint = ShoppingAssistantEntryPointEnum::SEARCH): ShoppingAssistantConversation
{
    if ($at) {
        Carbon::setTestNow(CarbonImmutable::parse($at));
    }

    $conversation = StartShoppingAssistantConversation::run($customer, $entryPoint);
    foreach ($turns as [$role, $body]) {
        if ($role === 'customer') {
            AskShoppingAssistant::make()->storeCustomerMessage($conversation, $body);
        } else {
            $conversation->messages()->create(['role' => ShoppingAssistantMessageRoleEnum::ASSISTANT, 'body' => $body]);
        }
    }
    $conversation->update(['last_message_at' => now()]);

    Carbon::setTestNow();

    return $conversation->refresh();
}

function insightsTagReply(array $challenges): array
{
    return [
        'choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
            'id'       => 'call_1',
            'type'     => 'function',
            'function' => ['name' => 'record_challenges', 'arguments' => json_encode(['challenges' => $challenges])],
        ]]]]],
        'usage'   => ['prompt_tokens' => 500, 'completion_tokens' => 40, 'cost' => 0.0002],
    ];
}

function insightsFilters(Website $website): array
{
    return ['website_ids' => [$website->id], 'search_website_ids' => [$website->id], 'language' => null, 'entry_point' => null, 'shop_type' => null, 'country' => null];
}

test('a customer rates an answer of their own conversation, can take it back, and the report and its drill-down count it', function () {
    $website      = insightsWebsite($this);
    $customer     = insightsCustomer($this);
    $webUser      = insightsWebUser($customer);
    $conversation = insightsConversation($customer, [['customer', 'Candles please'], ['assistant', 'Here are candles.'], ['customer', 'And mugs?'], ['assistant', 'Here are mugs.']]);
    [$first, $second] = $conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::ASSISTANT)->orderBy('id')->get()->all();
    $question     = $conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)->first();
    $url          = 'http://'.$website->domain.'/json/shopping-assistant/'.$conversation->ulid.'/feedback';
    $report       = GetShoppingAssistantReport::make();
    $today        = CarbonImmutable::today();
    $before       = $report->handle(insightsFilters($website), $today, $today, 'GBP', true)['feedback']['current'];

    $this->actingAs($webUser, 'retina')->postJson($url, ['message_id' => $first->id, 'feedback' => 'up'])->assertNoContent();
    $this->actingAs($webUser, 'retina')->postJson($url, ['message_id' => $second->id, 'feedback' => 'down'])->assertNoContent();
    $this->actingAs($webUser, 'retina')->postJson($url, ['message_id' => $question->id, 'feedback' => 'up'])->assertNotFound();
    $this->actingAs($webUser, 'retina')->postJson($url, ['message_id' => $first->id, 'feedback' => 'meh'])->assertStatus(422);

    $other = insightsWebUser(insightsCustomer($this));
    $this->actingAs($other, 'retina')->postJson($url, ['message_id' => $first->id, 'feedback' => 'down'])->assertNotFound();

    expect($first->refresh()->feedback)->toBe('up')
        ->and($first->feedback_at)->not->toBeNull()
        ->and($second->refresh()->feedback)->toBe('down');

    $polled = collect($this->actingAs($webUser, 'retina')->getJson('http://'.$website->domain.'/json/shopping-assistant/'.$conversation->ulid)->assertOk()->json('messages'));
    expect($polled->firstWhere('id', $first->id)['feedback'])->toBe('up');

    expect($report->handle(insightsFilters($website), $today, $today, 'GBP', true)['feedback']['current'])->toBe(['answers' => $before['answers'], 'up' => $before['up'] + 1, 'down' => $before['down'] + 1])
        ->and(ShoppingAssistantConversation::whereRaw($report->generalFilterCondition('thumbs_down'))->pluck('id')->all())->toContain($conversation->id);

    $this->actingAs($webUser, 'retina')->postJson($url, ['message_id' => $first->id, 'feedback' => null])->assertNoContent();
    expect($first->refresh()->feedback)->toBeNull()->and($first->feedback_at)->toBeNull();
});

test('a quiet conversation is tagged with the challenges the customer raised, a quote only in their own words, and read again after a new message', function () {
    $website      = insightsWebsite($this);
    $customer     = insightsCustomer($this);
    $conversation = insightsConversation($customer, [['customer', 'Do you sell shungite pyramids? Your carton of 24 is too big for me.'], ['assistant', 'We do not sell shungite.']], now()->subHours(3)->toIso8601String());
    $quiet        = TagShoppingAssistantConversationChallenges::make()->pendingQuery(120)->pluck('shopping_assistant_conversations.id');
    $today        = CarbonImmutable::today();
    $before       = GetShoppingAssistantReport::make()->challenges(insightsFilters($website), $today->subDay(), $today->endOfDay());
    $typesBefore  = collect($before['by_type'])->pluck('conversations', 'type');

    expect($quiet)->toContain($conversation->id);

    Http::fake(['*chat/completions' => Http::sequence()->push(insightsTagReply([
        ['type' => 'unmet_need', 'topic' => '  Shungite   Pyramids ', 'quote' => 'Do you sell shungite pyramids?'],
        ['type' => 'moq_carton', 'topic' => 'carton of 24 too big', 'quote' => 'The assistant said cartons are fixed'],
        ['type' => 'made_up', 'topic' => 'nothing', 'quote' => 'x'],
    ]))]);

    TagShoppingAssistantConversationChallenges::run($conversation);

    $challenges = DB::table('shopping_assistant_challenges')->where('shopping_assistant_conversation_id', $conversation->id)->orderBy('id')->get();
    expect($challenges->pluck('type')->all())->toBe(['unmet_need', 'moq_carton'])
        ->and($challenges->pluck('topic')->all())->toBe(['shungite pyramids', 'carton of 24 too big'])
        ->and($challenges->pluck('quote')->all())->toBe(['Do you sell shungite pyramids?', null])
        ->and($conversation->refresh()->challenges_checked_at)->not->toBeNull()
        ->and(TagShoppingAssistantConversationChallenges::make()->pendingQuery(120)->pluck('shopping_assistant_conversations.id'))->not->toContain($conversation->id);

    Http::assertSent(fn ($request) => $request['model'] === config('services.shopping_assistant.challenges_model') && $request['tool_choice']['function']['name'] === 'record_challenges');
    expect(DB::table('shopping_assistant_model_calls')->where('shopping_assistant_conversation_id', $conversation->id)->count())->toBe(0);

    $conversation->update(['challenges_checked_at' => now()->subHours(3), 'last_message_at' => now()->subMinutes(130)]);
    expect(TagShoppingAssistantConversationChallenges::make()->pendingQuery(120)->pluck('shopping_assistant_conversations.id'))->toContain($conversation->id);

    $report = GetShoppingAssistantReport::make()->challenges(insightsFilters($website), $today->subDay(), $today->endOfDay());
    $types  = collect($report['by_type'])->pluck('conversations', 'type');
    expect($report['tagged'])->toBe($before['tagged'] + 1)
        ->and(collect($report['top'])->firstWhere('topic', 'shungite pyramids'))->toMatchArray(['type' => 'unmet_need', 'quote' => 'Do you sell shungite pyramids?', 'conversation' => $conversation->ulid])
        ->and($types['unmet_need'])->toBe(($typesBefore['unmet_need'] ?? 0) + 1)
        ->and($types['moq_carton'])->toBe(($typesBefore['moq_carton'] ?? 0) + 1);
});

test('staff, command line and wiped conversations are never read for challenges, and a conversation with no customer words asks no model', function () {
    insightsWebsite($this);
    $staff = insightsCustomer($this);
    $staff->update(['is_staff' => true]);
    $at = now()->subHours(3)->toIso8601String();

    $staffConversation   = insightsConversation($staff, [['customer', 'Too expensive']], $at);
    $commandConversation = insightsConversation(insightsCustomer($this), [['customer', 'Too expensive']], $at, ShoppingAssistantEntryPointEnum::COMMAND);
    $wiped               = insightsConversation(insightsCustomer($this), [['customer', 'Too expensive']], $at);
    WipeShoppingAssistantConversations::run([$wiped->id]);
    $silent = insightsConversation(insightsCustomer($this), [['assistant', 'Hello']], $at);

    $pending = TagShoppingAssistantConversationChallenges::make()->pendingQuery(120)->pluck('shopping_assistant_conversations.id');
    expect($pending)->not->toContain($staffConversation->id)
        ->and($pending)->not->toContain($commandConversation->id)
        ->and($pending)->not->toContain($wiped->id)
        ->and($pending)->toContain($silent->id);

    $failing = insightsConversation(insightsCustomer($this), [['customer', 'The carton is too big']], $at);
    Http::fake(['*chat/completions' => Http::sequence()->push([], 500)->push(['choices' => [['message' => ['content' => 'no tool call']]]])]);
    TagShoppingAssistantConversationChallenges::run($failing);
    expect($failing->refresh()->challenges_checked_at)->toBeNull();
    TagShoppingAssistantConversationChallenges::run($failing);
    expect($failing->refresh()->challenges_checked_at)->not->toBeNull()
        ->and(DB::table('shopping_assistant_challenges')->where('shopping_assistant_conversation_id', $failing->id)->count())->toBe(0);

    Http::fake();
    TagShoppingAssistantConversationChallenges::run($silent);
    TagShoppingAssistantConversationChallenges::run($wiped->refresh());
    Http::assertNothingSent();

    expect($silent->refresh()->challenges_checked_at)->not->toBeNull()
        ->and($wiped->refresh()->challenges_checked_at)->toBeNull();
});

test('texts older than the group retention period are wiped with their challenge quotes and refused questions, figures stay, and anonymising wipes at once', function () {
    $website  = insightsWebsite($this);
    $customer = insightsCustomer($this);
    $old      = insightsConversation($customer, [['customer', 'Shungite please'], ['assistant', 'We have none.']], now()->subMonths(13)->toIso8601String());
    $recent   = insightsConversation($customer, [['customer', 'Candles please']], now()->subMonths(2)->toIso8601String());
    DB::table('shopping_assistant_challenges')->insert(['shopping_assistant_conversation_id' => $old->id, 'type' => 'unmet_need', 'topic' => 'shungite', 'quote' => 'Shungite please', 'created_at' => now()->subMonths(13)]);
    DB::table('shopping_assistant_refusals')->insert(['group_id' => $customer->group_id, 'organisation_id' => $customer->organisation_id, 'shop_id' => $customer->shop_id, 'website_id' => $website->id, 'customer_id' => $customer->id, 'reason' => 'rate_limit', 'question' => 'Old question', 'created_at' => now()->subMonths(13)]);

    UpdateGroupSettings::make()->action($this->organisation->group, ['shopping_assistant_retention_months' => 12]);
    expect(WipeShoppingAssistantConversations::retentionMonths($this->organisation->group->refresh()))->toBe(12);

    $this->artisan('shopping-assistant:wipe-old')->assertSuccessful();

    expect($old->messages()->pluck('body')->filter()->all())->toBe([])
        ->and($old->refresh()->wiped_at)->not->toBeNull()
        ->and($old->messages_count)->toBeGreaterThan(0)
        ->and(DB::table('shopping_assistant_challenges')->where('shopping_assistant_conversation_id', $old->id)->first())->toMatchArray(['type' => 'unmet_need', 'topic' => '', 'quote' => null])
        ->and(DB::table('shopping_assistant_refusals')->where('customer_id', $customer->id)->value('question'))->toBeNull()
        ->and($recent->messages()->pluck('body')->all())->toBe(['Candles please'])
        ->and($recent->refresh()->wiped_at)->toBeNull();

    $webUser = insightsWebUser($customer);
    $base    = 'http://'.$website->domain.'/json/shopping-assistant/';
    $this->actingAs($webUser, 'retina')->getJson($base.$old->ulid)->assertNotFound();
    $this->actingAs($webUser, 'retina')->getJson($base.$recent->ulid)->assertOk();
    expect(collect($this->actingAs($webUser, 'retina')->getJson($base.'conversations')->assertOk()->json())->flatten()->all())
        ->not->toContain($old->ulid)
        ->toContain($recent->ulid);

    $late = $old->messages()->create(['role' => ShoppingAssistantMessageRoleEnum::ASSISTANT, 'body' => 'Our purchasing team answered your question']);
    $this->actingAs($webUser, 'retina')->postJson($base.$old->ulid.'/feedback', ['message_id' => $late->id, 'feedback' => 'down'])->assertNotFound();
    $this->artisan('shopping-assistant:wipe-old')->assertSuccessful();
    expect($late->refresh()->body)->toBeNull()->and($late->feedback)->toBeNull();

    AnonymiseCustomer::make()->action($customer, 'Erasure request', false);
    expect($recent->messages()->pluck('body')->filter()->all())->toBe([])
        ->and($recent->refresh()->wiped_at)->not->toBeNull();
});

test('the weekly summary goes only to the users picked in the group settings, covers last Monday to Sunday and carries no quotes', function () {
    Notification::fake();
    $website  = insightsWebsite($this);
    $customer = insightsCustomer($this);
    $lastWeek = CarbonImmutable::today()->startOfWeek()->subWeek()->addDays(2);
    $conversation = insightsConversation($customer, [['customer', 'Do you have shungite?']], $lastWeek->setTime(10, 0)->toIso8601String());
    DB::table('shopping_assistant_challenges')->insert(['shopping_assistant_conversation_id' => $conversation->id, 'type' => 'unmet_need', 'topic' => 'shungite', 'quote' => 'Do you have shungite?', 'created_at' => $lastWeek]);

    $group = $this->organisation->group;
    UpdateGroupSettings::make()->action($group, ['shopping_assistant_weekly_summary_user_ids' => []]);
    expect(SendShoppingAssistantWeeklySummary::run($group->refresh()))->toBe(0);
    Notification::assertNothingSent();

    $sender  = SendShoppingAssistantWeeklySummary::make();
    $outside = User::where('group_id', $group->id)->where('status', true)->get()->first(fn (User $user) => !$sender->canViewGroupShoppingAssistant($user));
    expect($sender->canViewGroupShoppingAssistant($this->user))->toBeTrue();
    UpdateGroupSettings::make()->action($group, ['shopping_assistant_weekly_summary_user_ids' => array_values(array_filter([$this->user->id, $outside?->id]))]);
    expect(SendShoppingAssistantWeeklySummary::run($group->refresh()))->toBe(1);

    Notification::assertSentTo($this->user, ShoppingAssistantWeeklySummaryNotification::class, function (ShoppingAssistantWeeklySummaryNotification $notification) use ($lastWeek, $website) {
        $mail = implode("\n", $notification->toMail($this->user)->introLines);

        return $notification->summary['from'] === $lastWeek->startOfWeek()->toDateString()
            && $notification->summary['to'] === $lastWeek->startOfWeek()->addDays(6)->toDateString()
            && $notification->summary['kpis']['conversations']['value'] >= 1
            && str_contains($mail, 'shungite')
            && !str_contains($mail, 'Do you have shungite?')
            && $website->exists;
    });
    if ($outside) {
        Notification::assertNotSentTo($outside, ShoppingAssistantWeeklySummaryNotification::class);
    }

    expect((new ShoppingAssistantWeeklySummaryNotification([]))->plain('[win](https://evil.example) - *now*'))
        ->not->toContain('](')
        ->not->toContain('://')
        ->toContain('\\[win\\]');
});

test('purchasing gets every Monday what customers asked for last week that we do not have or had out of stock, by topic and shop, with no quotes', function () {
    Notification::fake();
    insightsWebsite($this);
    $customer = insightsCustomer($this);
    $lastWeek = CarbonImmutable::today()->startOfWeek()->subWeek()->addDays(2);
    $topic    = 'dried orange peel '.Str::lower(Str::random(5));
    foreach ([['unmet_need', $topic, 'Dried orange peel please'], ['out_of_stock', 'raised incense holders', 'have they come back?'], ['price', 'too dear', 'too dear']] as [$type, $challengeTopic, $quote]) {
        $conversation = insightsConversation($customer, [['customer', $quote]], $lastWeek->setTime(10, 0)->toIso8601String());
        DB::table('shopping_assistant_challenges')->insert(['shopping_assistant_conversation_id' => $conversation->id, 'type' => $type, 'topic' => $challengeTopic, 'quote' => $quote, 'created_at' => $lastWeek]);
    }
    $old = insightsConversation($customer, [['customer', 'old ask']], $lastWeek->subWeeks(2)->toIso8601String());
    DB::table('shopping_assistant_challenges')->insert(['shopping_assistant_conversation_id' => $old->id, 'type' => 'unmet_need', 'topic' => 'old topic '.$topic, 'quote' => 'old ask', 'created_at' => $lastWeek->subWeeks(2)]);

    $group = $this->organisation->group;
    expect(SendShoppingAssistantUnmetNeeds::run($group->refresh()))->toBe(['emailed' => 0, 'slack' => false]);
    Notification::assertNothingSent();

    UpdateGroupSettings::make()->action($group, ['shopping_assistant_unmet_needs_user_ids' => [$this->user->id]]);
    expect(SendShoppingAssistantUnmetNeeds::run($group->refresh()))->toBe(['emailed' => 1, 'slack' => false]);

    Notification::assertSentTo($this->user, ShoppingAssistantUnmetNeedsNotification::class, function (ShoppingAssistantUnmetNeedsNotification $notification) use ($topic) {
        $mail  = implode("\n", $notification->toMail($this->user)->introLines);
        $slack = json_encode($notification->toSlackMessage()->toArray());

        return str_contains($mail, $topic) && str_contains($mail, 'raised incense holders') && str_contains($mail, $this->shop->name)
            && str_contains($slack, $topic)
            && !str_contains($mail, 'too dear') && !str_contains($mail, 'old topic') && !str_contains($mail, 'please') && !str_contains($mail, 'come back');
    });

    expect((new ShoppingAssistantUnmetNeedsNotification(['from' => '', 'to' => '', 'needs' => []]))->plain('<https://evil.example|*win*>'))
        ->not->toContain('://')->not->toContain('<')->not->toContain('*');
});

test('the group settings page offers the weekly summary recipients and the retention period, and saves them', function () {
    $this->withoutVite();
    $group = $this->organisation->group;

    UpdateGroupSettings::make()->action($group, ['shopping_assistant_weekly_summary_user_ids' => [$this->user->id], 'shopping_assistant_retention_months' => 18]);

    expect(data_get($group->refresh()->settings, 'shopping_assistant.weekly_summary_user_ids'))->toBe([$this->user->id])
        ->and(data_get($group->settings, 'shopping_assistant.retention_months'))->toBe(18);

    $this->get(route('grp.sysadmin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('formData.blueprint.5.fields.shopping_assistant_weekly_summary_user_ids.value', [$this->user->id])
            ->where('formData.blueprint.5.fields.shopping_assistant_retention_months.value', 18));
});
