<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\GetChatCustomerTimeline;
use App\Actions\Chat\ChatSession\GetChatReports;
use App\Actions\Chat\ChatSession\IndexChatConversations;
use App\Actions\Chat\ChatSession\GetChatMessages;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatRetractionReasonEnum;
use App\Actions\Chat\ChatSession\RedactChatMessage;
use App\Models\Chat\ChatMessageTranslation;
use App\Actions\Chat\ChatSession\RetractChatMessage;
use App\Actions\Chat\ChatSession\RestoreChatSession;
use App\Actions\Chat\ChatSession\TrashChatSession;
use Illuminate\Support\Facades\Route;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatEvent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatSession;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

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

// CHAT UI ACTIONS TESTS

test('UI Show shop chat dashboard', function () {
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.chat.reports', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Chat/ChatReports');
    });
});

test('customer service edits the shop out of hours email in chat settings, others may not', function () {
    actingAs($this->user);
    $parameters = [$this->organisation->slug, $this->shop->slug];

    get(route('grp.org.shops.show.chat.settings', $parameters).'?tab=out_of_hours')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Org/Chat/ChatSettings')
        ->has('tabs.navigation.out_of_hours')
        ->where('pageHead.actions', [])
        ->where('outOfHours.update_route.name', 'grp.org.shops.show.chat.settings.out_of_hours_message.update'));

    get(route('grp.org.chat.settings', [$this->organisation->slug]))->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('tabs.navigation.out_of_hours')
        ->where('outOfHours', null));

    patch(route('grp.org.shops.show.chat.settings.out_of_hours_message.update', $parameters), ['message' => 'Closed on bank holidays.'])->assertRedirect();
    expect(data_get($this->shop->fresh()->settings, 'chat.out_of_hours_message'))->toBe('Closed on bank holidays.');

    $email = fn () => \App\Actions\Chat\ChatSession\SendOutOfHoursReply::make()->text($this->shop->fresh(), true, null, true, true);

    expect($email())->toStartWith('Thank you for your message.')->toEndWith("\n\nClosed on bank holidays.");

    patch(route('grp.org.shops.show.chat.settings.out_of_hours_message.update', $parameters), ['message' => 'Closed on bank holidays.', 'opening_line' => false])->assertRedirect();
    get(route('grp.org.shops.show.chat.settings', $parameters).'?tab=out_of_hours')->assertInertia(fn (AssertableInertia $page) => $page->where('outOfHours.show_opening_line', false));
    expect($email())->toBe('Closed on bank holidays.')
        ->and(\App\Actions\Chat\ChatSession\SendOutOfHoursReply::make()->text($this->shop->fresh(), true, null, true))->toStartWith('Thank you for your message.');

    \App\Actions\Chat\UpdateShopOutOfHoursMessage::make()->handle($this->shop->fresh(), ['message' => '']);
    expect($email())->toStartWith('Thank you for your message.');
    \App\Actions\Chat\UpdateShopOutOfHoursMessage::make()->handle($this->shop->fresh(), ['message' => 'Closed on bank holidays.']);

    actingAs(User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]));
    patch(route('grp.org.shops.show.chat.settings.out_of_hours_message.update', $parameters), ['message' => 'Hijacked'])->assertForbidden();
    expect(data_get($this->shop->fresh()->settings, 'chat.out_of_hours_message'))->toBe('Closed on bank holidays.');

    \App\Actions\Chat\UpdateShopOutOfHoursMessage::make()->handle($this->shop->fresh(), ['message' => null, 'opening_line' => true]);
    expect(data_get($this->shop->fresh()->settings, 'chat.out_of_hours_opening_line'))->toBeNull();
});

test('customer service sets an email footer per language, used under the agent signature, others may not', function () {
    actingAs($this->user);
    $parameters = [$this->organisation->slug, $this->shop->slug];

    get(route('grp.org.shops.show.chat.settings', $parameters).'?tab=email_footer')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('tabs.navigation.email_footer')
        ->where('emailFooters.update_route.name', 'grp.org.shops.show.chat.settings.email_footers.update'));

    get(route('grp.org.chat.settings', [$this->organisation->slug]))->assertInertia(fn (AssertableInertia $page) => $page
        ->missing('tabs.navigation.email_footer')
        ->where('emailFooters', null));

    patch(route('grp.org.shops.show.chat.settings.email_footers.update', $parameters), ['footers' => [
        ['language' => null, 'text' => 'Ancient Wisdom, Sheffield'],
        ['language' => 'de', 'text' => 'Ancient Wisdom, Sheffield, England'],
        ['language' => 'fr', 'text' => '  '],
    ]])->assertRedirect();

    $footer = fn (?string $language) => \App\Actions\Chat\UpdateShopEmailFooters::footerFor($this->shop->fresh(), $language);

    expect(data_get($this->shop->fresh()->settings, 'chat.email_footers'))->toBe(['default' => 'Ancient Wisdom, Sheffield', 'de' => 'Ancient Wisdom, Sheffield, England'])
        ->and($footer('de'))->toBe('Ancient Wisdom, Sheffield, England')
        ->and($footer('fr'))->toBe('Ancient Wisdom, Sheffield')
        ->and($footer(null))->toBe('Ancient Wisdom, Sheffield');

    $withFooter = fn (string $signature, string $footer) => \App\Actions\Comms\Mailbox\SendChatMessageByGmail::withShopFooter($signature, $footer);

    expect($withFooter("Kind regards,\nAnna", 'Ancient Wisdom'))->toBe("Kind regards,\nAnna\n\nAncient Wisdom")
        ->and($withFooter('<b>Anna</b>', 'Ancient & Wisdom'))->toBe('<b>Anna</b><br><br>Ancient &amp; Wisdom')
        ->and($withFooter('', 'Ancient Wisdom'))->toBe('Ancient Wisdom')
        ->and($withFooter('Anna', ''))->toBe('Anna');

    patch(route('grp.org.shops.show.chat.settings.email_footers.update', $parameters), ['footers' => [
        ['language' => 'de', 'text' => 'one'],
        ['language' => 'de', 'text' => 'two'],
    ]])->assertSessionHasErrors('footers.0.language');

    actingAs(User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]));
    patch(route('grp.org.shops.show.chat.settings.email_footers.update', $parameters), ['footers' => []])->assertForbidden();

    \App\Actions\Chat\UpdateShopEmailFooters::make()->handle($this->shop->fresh(), ['footers' => []]);
    expect(data_get($this->shop->fresh()->settings, 'chat.email_footers'))->toBeNull();
});

test('phone calls open from the chat reports, not the top menu, and a new build reloads open tabs', function () {
    actingAs($this->user);

    get(route('grp.chat.reports'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pageHead.actions.0.route.name', 'grp.chat.phone_calls.index'));

    get(route('grp.org.chat.reports', [$this->organisation->slug]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pageHead.actions.0.route.name', 'grp.org.chat.phone_calls.index'));

    \Illuminate\Support\Facades\Vite::shouldReceive('manifestHash')->with('grp')->andReturn('build-2');
    expect(app(\App\Http\Middleware\HandleInertiaGrpRequests::class)->version(request()))->toBe('build-2');
});

test('UI Index chat sessions for a shop', function () {
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSessions');
    });
});

test('UI Show chat session for a shop', function () {
    actingAs($this->user);

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.show', [$this->organisation->slug, $this->shop->slug, $chatSession->id]));

    $response->assertOk();
});

test('UI Show org chat dashboard', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.reports', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Show org chat conversation detail', function () {
    actingAs($this->user);

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $response = get(route('grp.org.chat.conversations.detail', [$this->organisation->slug, $chatSession->id]));

    $response->assertOk();
});

test('a conversation and the links to its messages open only for people who may view chat on its shop', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    $chatMessage = ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'My address is 1 High Street',
    ]);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $metaChatSession = MetaChatSession::create([
        'ulid'            => (string)Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $this->customer->id,
        'phone_number'    => '+447500000123',
        'status'          => ChatSessionStatusEnum::ACTIVE,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
    ]);

    $metaChatMessage = \App\Models\Chat\MetaChatMessage::create([
        'meta_chat_session_id' => $metaChatSession->id,
        'meta_channel_id'      => $channel->id,
        'message_type'         => ChatMessageTypeEnum::TEXT,
        'sender_type'          => ChatSenderTypeEnum::GUEST,
        'message_text'         => 'My phone is 07500 000123',
    ]);

    $detail     = route('grp.org.chat.conversations.detail', [$this->organisation->slug, $chatSession->id]);
    $shopDetail = route('grp.org.shops.show.chat.conversations.detail', [$this->organisation->slug, $this->shop->slug, $chatSession->id]);

    actingAs(User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]));

    get($detail)->assertForbidden();
    get($shopDetail)->assertForbidden();
    get(route('grp.majordomo.redirect_chat_message', $chatMessage->id))->assertForbidden();
    get(route('grp.majordomo.redirect_whatsapp_message', $metaChatMessage->id))->assertForbidden();

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    actingAs($clerk);

    get($detail)->assertOk();
    get($shopDetail)->assertOk();
    get(route('grp.majordomo.redirect_chat_message', $chatMessage->id))->assertRedirect($detail);
    get(route('grp.majordomo.redirect_whatsapp_message', $metaChatMessage->id))
        ->assertRedirect(route('grp.org.chat.inbox', [$this->organisation->slug, 'channel' => 'whatsapp', 'session' => $metaChatSession->ulid]));

    $metaChatMessage->forceDelete();
    $metaChatSession->forceDelete();
});

test('a conversation is not found under an organisation or shop it does not belong to', function () {
    actingAs($this->user);

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    $otherOrganisation = Organisation::where('code', 'cht2')->first()
        ?? \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action(
            $this->organisation->group,
            array_merge(Organisation::factory()->definition(), ['code' => 'cht2', 'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP])
        );

    $otherShop = \App\Models\Catalogue\Shop::where('organisation_id', $this->organisation->id)->where('id', '!=', $this->shop->id)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    get(route('grp.org.chat.conversations.detail', [$otherOrganisation->slug, $chatSession->id]))->assertNotFound();
    get(route('grp.org.shops.show.chat.conversations.detail', [$this->organisation->slug, $otherShop->slug, $chatSession->id]))->assertNotFound();
});

test('the inbox opens a conversation only for people who may view chat on its shop', function () {
    setPermissionsTeamId($this->organisation->group_id);

    $chatSession = ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);

    $otherShop = \App\Models\Catalogue\Shop::where('organisation_id', $this->organisation->id)->where('id', '!=', $this->shop->id)->first()
        ?? \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    $otherShopClerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $otherShopClerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $otherShop));

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $inbox       = route('grp.org.chat.inbox.conversation', [$this->organisation->slug, $chatSession->ulid]);
    $supervision = route('grp.org.chat.supervision.conversation', [$this->organisation->slug, $chatSession->ulid]);

    actingAs($otherShopClerk);

    get($inbox)->assertForbidden();
    get($supervision)->assertForbidden();

    actingAs($clerk);

    $this->followingRedirects()->get($inbox)->assertOk();
    get($supervision)->assertOk();

    $page = get($supervision)->viewData('page');

    expect($page['props']['initialSession']['ulid'])->toBe($chatSession->ulid)
        ->and($page['props'])->toHaveKeys(['agents', 'breadcrumbs', 'inboxes']);

    $tables = [];
    DB::listen(function ($query) use (&$tables) {
        if (str_contains($query->sql, 'from "chat_events"') || $query->sql === 'select * from "chat_agents" where "chat_agents"."deleted_at" is null') {
            $tables[] = $query->sql;
        }
    });

    $counts = get($supervision, [
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $page['version'],
        'X-Inertia-Partial-Component' => 'Org/Chat/Inbox',
        'X-Inertia-Partial-Data'      => 'inboxes',
    ])->assertOk()->json('props');

    expect($counts['inboxes'])->toEqual($page['props']['inboxes'])
        ->and($counts)->not->toHaveKeys(['initialSession', 'agents'])
        ->and($tables)->toBe([]);
});

test('UI Show group chat dashboard', function () {
    actingAs($this->user);

    $response = get(route('grp.chat.reports'));

    $response->assertOk();
});

test('UI Show chat conversations index for organisation', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.conversations.show', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Edit agent shows the form for an existing agent', function () {
    actingAs($this->user);

    $user  = User::factory()->create(['group_id' => $this->organisation->group_id]);
    $agent = ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => false,
        'is_available'         => false,
        'current_chat_count'   => 0,
    ]);

    $response = get(route('grp.org.chat.agents.edit', [$this->organisation->slug, $agent->id]));

    $response->assertOk();
});

test('UI Create agent shows the create form', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.agents.create', [$this->organisation->slug]));

    $response->assertOk();
});

test('UI Show agent listing for organisation', function () {
    actingAs($this->user);

    $response = get(route('grp.org.chat.agents.show', [$this->organisation->slug]));

    $response->assertOk();
});

it('can render chat sessions index in CRM', function () {
    $this->withoutExceptionHandling();

    setPermissionsTeamId($this->shop->group_id);
    SeedShopPermissions::run($this->shop);
    $crmViewPermission = Permission::where('name', "crm.{$this->shop->id}.view")->first();
    if ($crmViewPermission) {
        $this->user->givePermissionTo($crmViewPermission);
    }
    $this->user->refresh();
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.index', [
        $this->organisation->slug,
        $this->shop->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSessions')
            ->has('data');
    });
});

it('can render chat session detail', function () {
    $this->withoutExceptionHandling();

    $chatSession = ChatSession::where('shop_id', $this->shop->id)->first()
        ?? StoreChatSession::make()->handle([
            'language_id' => 68,
            'priority'    => ChatPriorityEnum::NORMAL->value,
            'shop_id'     => $this->shop->id,
        ]);

    setPermissionsTeamId($this->shop->group_id);
    SeedShopPermissions::run($this->shop);
    $crmViewPermission = Permission::where('name', "crm.{$this->shop->id}.view")->first();
    if ($crmViewPermission) {
        $this->user->givePermissionTo($crmViewPermission);
    }
    $this->user->refresh();
    actingAs($this->user);

    $response = get(route('grp.org.shops.show.crm.chat_sessions.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $chatSession->id,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSession')
            ->has('chatSession')
            ->has('messages');
    });
});

test('a conversation cannot be destroyed, and trashing one is written into its record', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::WAITING->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    // Nothing anywhere offers to destroy a conversation.
    expect(collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains((string) $route->getName(), 'sessions.force_delete'))
        ->isEmpty())->toBeTrue();

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $this->actingAs($clerk);

    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);
    TrashChatSession::make()->handle($session, $agent->id);

    // Out of sight, still there, and the move is on the record with a name against it.
    $trashed = ChatSession::withTrashed()->find($session->id);
    expect($trashed->trashed())->toBeTrue()
        ->and(ChatEvent::where('chat_session_id', $session->id)
            ->where('event_type', ChatEventTypeEnum::TRASH->value)
            ->value('payload')['user_id'] ?? null)->toBe($clerk->id);

    RestoreChatSession::make()->handle($trashed, $agent->id);

    expect(ChatSession::find($session->id))->not->toBeNull()
        ->and(ChatEvent::where('chat_session_id', $session->id)
            ->where('event_type', ChatEventTypeEnum::RESTORE->value)
            ->exists())->toBeTrue();
});

test('an agent takes back their own message: the customer loses it, we keep it', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    ChatAssignment::create([
        'chat_session_id' => $session->id,
        'chat_agent_id'   => $agent->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_at'     => now(),
    ]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::AGENT->value,
        'sender_id'       => $agent->id,
        'message_text'    => 'sent to the wrong person',
    ]);

    $this->actingAs($clerk);
    RetractChatMessage::make()->handle(
        $session,
        $message,
        $agent,
        ChatRetractionReasonEnum::WRONG_CONVERSATION,
        'pasted from the other chat'
    );

    $public = GetChatMessages::make()->handle($session, [])->firstWhere('id', $message->id);
    $staff  = GetChatMessages::make()->handle($session, [], true)->firstWhere('id', $message->id);

    // The customer is told something was withdrawn and why, and never reads the words.
    expect($public)->not->toBeNull()
        ->and($public->message_text)->toBeNull()
        ->and($staff->message_text)->toBe('sent to the wrong person')
        ->and(ChatMessage::withTrashed()->find($message->id)->metadata['retraction_reason'])
        ->toBe(ChatRetractionReasonEnum::WRONG_CONVERSATION->value);

    // The note we keep for ourselves is never put on the wire, to either side.
    $rendered = (new \App\Http\Resources\CRM\Livechat\ChatMessageResource($staff))->resolve();
    expect($rendered['retraction_reason'])->toBe(ChatRetractionReasonEnum::WRONG_CONVERSATION->label())
        ->and(json_encode($rendered))->not->toContain('pasted from the other chat');

    // Somebody else's message is not theirs to take back.
    $other = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'the customer wrote this',
    ]);

    expect(fn () => RetractChatMessage::make()->handle($session, $other, $agent, ChatRetractionReasonEnum::EXPLAINING))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('an agent strikes a card number out of a message everywhere it was stored', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'my card is 4111111111111111 please charge it',
        'original_text'   => 'mi tarjeta es 4111111111111111 por favor',
    ]);

    ChatMessageTranslation::create([
        'chat_message_id'   => $message->id,
        'target_language_id' => 68,
        'translated_text'   => 'my card is 4111111111111111 please',
    ]);

    $this->actingAs($clerk);
    RedactChatMessage::make()->handle($session, $message->fresh(), $agent, '4111111111111111');

    $redacted = $message->fresh();
    $mask = str_repeat(RedactChatMessage::MASK, 16);

    // Gone from the message, from what it was translated from, and from the translation.
    expect($redacted->message_text)->toBe("my card is $mask please charge it")
        ->and($redacted->original_text)->toBe("mi tarjeta es $mask por favor")
        ->and($redacted->translations()->first()->translated_text)->toBe("my card is $mask please")
        ->and($redacted->metadata['redacted_by_user_id'])->toBe($clerk->id);

    // The record says it happened and never repeats what was taken out.
    $event = ChatEvent::where('chat_session_id', $session->id)
        ->where('event_type', ChatEventTypeEnum::REDACT->value)
        ->first();

    expect($event->payload['occurrences'])->toBe(3)
        ->and(json_encode($event->payload))->not->toContain('4111111111111111');

    // One careless letter would be struck out of the whole message, with no way back.
    expect(fn () => RedactChatMessage::make()->handle($session, $redacted, $agent, 'my'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Redaction only removes: text that is not there cannot be used to rewrite the message.
    expect(fn () => RedactChatMessage::make()->handle($session, $redacted, $agent, 'never written'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('striking a card number out of an email also takes it out of the email body and its summary', function () {
    setPermissionsTeamId($this->user->group_id);
    \Illuminate\Support\Facades\Queue::fake();

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    $email = fn (string $html) => ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'my card is 4111111111111111 please charge it',
        'html_body'       => $html,
        'metadata'        => ['ai_summary' => 'Customer gives card 4111 1111 1111 1111 and asks to be charged.'],
    ]);

    $this->actingAs($clerk);
    $mask = str_repeat(RedactChatMessage::MASK, 16);

    $verbatim = $email('<p class="card">my card is <b>4111111111111111</b> please charge it</p>');
    RedactChatMessage::make()->handle($session, $verbatim, $agent, '4111111111111111');
    $verbatim = $verbatim->fresh();

    expect($verbatim->html_body)->toBe("<p class=\"card\">my card is <b>$mask</b> please charge it</p>")
        ->and($verbatim->metadata)->not->toHaveKey('ai_summary');
    \App\Actions\Chat\ChatSession\SummarizeLongEmail::assertPushed(1);

    // Split by a tag, the fragment cannot be masked in place, so the markup goes and the masked text shows.
    $split = $email('<p>my card is 41111111<span>11111111</span> please charge it</p>');
    RedactChatMessage::make()->handle($session, $split, $agent, '4111111111111111');

    expect($split->fresh()->html_body)->toBeNull()
        ->and($split->fresh()->message_text)->toBe("my card is $mask please charge it");

    $spaced = $email('<p>my card is 4111&nbsp;1111 please charge it</p>');
    $spaced->update(['message_text' => 'my card is 4111 1111 please charge it']);
    RedactChatMessage::make()->handle($session, $spaced, $agent, '4111 1111');

    expect($spaced->fresh()->html_body)->toBeNull();
});

test('an agent removes a photograph of a card from a message and from the archive', function (bool $hasArchiveCopy) {
    $archiveSchema = $hasArchiveCopy ? 'chat_redaction_archived' : 'chat_redaction_unarchived';
    config()->set(
        'database.connections.archive',
        array_merge(config('database.connections.'.config('database.default')), ['search_path' => $archiveSchema])
    );
    DB::purge('archive');
    DB::statement('create schema if not exists '.$archiveSchema);

    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.\Illuminate\Support\Str::random(8),
    ]);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'language_id' => $clerk->language_id]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'here is a picture of my card',
    ]);

    // Nothing attached: there is nothing to remove, and we say so rather than pretending.
    $this->actingAs($clerk);
    expect(fn () => RedactChatMessage::make()->handleAttachment($session, $message, $agent))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $cardPath = tempnam(sys_get_temp_dir(), 'chat').'.txt';
    file_put_contents($cardPath, 'a photograph of a card');
    $media = \App\Actions\Helpers\Media\StoreMediaFromFile::run($message, [
        'path'         => $cardPath,
        'originalName' => 'card.txt',
        'extension'    => 'txt',
        'checksum'     => md5_file($cardPath),
    ], 'chat_attachments', 'file');
    $message->update(['media_id' => $media->id]);

    $mediaId  = $media->id;
    $diskPath = $media->getPath();

    expect(is_file($diskPath))->toBeTrue();

    $archiveTable = \App\Actions\Chat\ChatSession\ArchiveChatMedia::ARCHIVE_TABLE;
    if ($hasArchiveCopy) {
        \Illuminate\Support\Facades\Schema::connection('archive')->create($archiveTable, function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedBigInteger('media_id')->primary();
            $table->binary('contents');
            $table->string('checksum', 32);
            $table->timestampTz('archived_at');
        });
        DB::connection('archive')->table($archiveTable)->insert([
            'media_id' => $mediaId,
            'contents' => 'a photograph of a card',
            'checksum' => md5('a photograph of a card'),
            'archived_at' => now(),
        ]);
        expect(DB::connection('archive')->table($archiveTable)->where('media_id', $mediaId)->exists())->toBeTrue();
    }

    RedactChatMessage::make()->handleAttachment($session, $message->fresh(), $agent);

    // Off the disk, out of the table, and the message says a file was taken out of it.
    $redacted = $message->fresh();
    expect(\App\Models\Helpers\Media::find($mediaId))->toBeNull()
        ->and(is_file($diskPath))->toBeFalse()
        ->and($redacted->media_id)->toBeNull()
        ->and($redacted->metadata['attachment_redacted_at'])->not->toBeNull()
        ->and(ChatEvent::where('chat_session_id', $session->id)
            ->where('event_type', ChatEventTypeEnum::REDACT->value)
            ->value('payload')['files'] ?? null)->toBe(1);
    if ($hasArchiveCopy) {
        expect(DB::connection('archive')->table($archiveTable)->where('media_id', $mediaId)->exists())->toBeFalse();
    }
})->with([
    'archive not initialized' => [false],
    'file also stored in archive' => [true],
]);

test('the agents a chat can be handed to come from permissions, not the old shop table', function () {
    setPermissionsTeamId($this->user->group_id);

    $worker = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $worker->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $manager = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $manager->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_SUPERVISOR->value, $this->shop));

    $online = fn (User $user) => ChatAgent::create([
        'user_id'              => $user->id,
        'max_concurrent_chats' => 5,
        'language_id'          => 68,
        'is_online'            => true,
        'is_available'         => true,
        'current_chat_count'   => 0,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

    $workerAgent  = $online($worker);
    $managerAgent = $online($manager);

    // Neither has anything but a position: the old shop_has_chat_agents table is gone.
    $listed = collect(\App\Actions\Chat\ChatSession\GetChatAgents::run())->keyBy('agent_id');

    expect($listed->has($workerAgent->id))->toBeTrue()
        ->and($listed[$workerAgent->id]['shop_names'])->toContain($this->shop->name)
        ->and($listed->has($managerAgent->id))->toBeTrue();
});

test('the inbox is the same view whatever scope it is opened from', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    // No chat agent profile: the position is the whole setup.
    $inboxesFrom = function (string $url) use ($agent) {
        $props = $this->actingAs($agent)->get($url)
            ->assertOk()
            ->viewData('page')['props'];

        return [
            collect($props['inboxes'])->pluck('slug')->sort()->values()->all(),
            collect($props['inboxes'])->firstWhere('slug', $this->shop->slug),
        ];
    };

    [$fromOrg, $shopInbox]   = $inboxesFrom(route('grp.org.chat.inbox', [$this->organisation->slug]));
    [$fromShop, $shopInbox2] = $inboxesFrom(route('grp.org.shops.show.chat.inbox', [$this->organisation->slug, $this->shop->slug]));

    expect($fromOrg)->toContain($this->shop->slug)
        // Chat is not scoped: an agent covers shops across organisations, so the address the
        // inbox was opened from must not change what it holds.
        ->and($fromShop)->toBe($fromOrg)
        ->and($shopInbox['is_read_only'])->toBeFalse()
        ->and($shopInbox2['is_read_only'])->toBeFalse();
});

test('overseeing chat has its own page, scoped to the address, showing everybody\'s conversations', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agentProfile = ChatAgent::create(['user_id' => $agent->id, 'max_concurrent_chats' => 10]);

    // The inbox is an agent's rota: nobody else is shown it.
    $this->actingAs($supervisor)
        ->get(route('grp.org.chat.inbox', [$this->organisation->slug]))
        ->assertRedirect(route('grp.org.chat.supervision', [$this->organisation->slug]));

    $this->actingAs($supervisor)
        ->get(route('grp.org.shops.show.chat.inbox', [$this->organisation->slug, $this->shop->slug]))
        ->assertRedirect(route('grp.org.shops.show.chat.supervision', [$this->organisation->slug, $this->shop->slug]));

    $props = $this->actingAs($supervisor)
        ->get(route('grp.org.chat.supervision', [$this->organisation->slug]))
        ->assertOk()
        ->viewData('page')['props'];

    $shopOrganisations = \App\Models\Catalogue\Shop::whereIn('id', collect($props['inboxes'])->pluck('id'))->pluck('organisation_id')->unique()->all();

    expect($props['supervisor'])->toBeTrue()
        ->and($shopOrganisations)->toBe([$this->organisation->id])
        ->and(collect($props['inboxes'])->firstWhere('slug', $this->shop->slug)['is_read_only'])->toBeFalse()
        ->and(collect($props['agents'])->pluck('id')->all())->toContain($agentProfile->id);

    $held = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::ACTIVE,
    ]);
    $loose = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::ACTIVE,
    ]);

    foreach ([$held, $loose] as $session) {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);
    }

    $assignment = ChatAssignment::create([
        'chat_session_id' => $held->id,
        'chat_agent_id'   => $agentProfile->id,
        'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    // Counted with a conversation actually held: with nothing to count the query never ran
    // far enough to fail, and the page fell over the first time anybody was busy.
    $agents = $this->actingAs($supervisor)
        ->get(route('grp.org.chat.supervision', [$this->organisation->slug]))
        ->assertOk()
        ->viewData('page')['props']['agents'];

    expect(collect($agents)->firstWhere('id', $agentProfile->id)['open'])->toBe(1);

    $colleague = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $colleague->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $websiteGuestsSeenBy = fn (User $user) => collect(collect($this->actingAs($user)
        ->get(route('grp.org.chat.inbox', [$this->organisation->slug]))
        ->assertOk()
        ->viewData('page')['props']['inboxes'])
        ->firstWhere('slug', $this->shop->slug)['channels'])
        ->firstWhere('key', 'website')['guest'];

    $finished = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::CLOSED,
    ]);
    ChatMessage::create([
        'chat_session_id' => $finished->id,
        'message_text'    => 'thanks',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);
    $finishedAssignment = ChatAssignment::create([
        'chat_session_id' => $finished->id,
        'chat_agent_id'   => $agentProfile->id,
        'status'          => ChatAssignmentStatusEnum::RESOLVED->value,
        'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
        'assigned_at'     => now(),
    ]);

    expect($websiteGuestsSeenBy($agent)['closed_mine'])->toBe(1)
        ->and($websiteGuestsSeenBy($colleague)['closed_mine'])->toBe(0)
        ->and($websiteGuestsSeenBy($colleague)['closed_colleagues'])->toBe($websiteGuestsSeenBy($agent)['closed_colleagues'] + 1);

    $finishedAssignment->forceDelete();
    $finished->messages()->forceDelete();
    $finished->forceDelete();

    expect($websiteGuestsSeenBy($agent)['mine'])->toBe(1)
        ->and($websiteGuestsSeenBy($colleague)['mine'])->toBe(0)
        ->and($websiteGuestsSeenBy($colleague)['colleagues'])->toBe($websiteGuestsSeenBy($agent)['colleagues'] + 1);

    $ofAgent = collect(GetChatSessions::make()->handle([
        'shop_id'   => $this->shop->id,
        'agent_ids' => [$agentProfile->id],
    ])->items())->pluck('id')->all();

    expect($ofAgent)->toContain($held->id)->not->toContain($loose->id);

    $tabs = fn (bool $isAgent, bool $isSupervisor) => collect(data_get(
        (new class () {
            use \App\Actions\Chat\WithChatNavigation;

            public function tabs(bool $isAgent, bool $isSupervisor): array
            {
                return $this->getChatNavigation('grp.org.chat.', ['aw'], $isAgent, $isSupervisor);
            }
        })->tabs($isAgent, $isSupervisor),
        'topMenu.subSections'
    ))->pluck('route.name')->all();

    expect($tabs(false, true))->toBe(['grp.org.chat.supervision', 'grp.org.chat.reports', 'grp.org.chat.settings'])
        ->and($tabs(true, false))->toBe(['grp.org.chat.inbox', 'grp.org.chat.reports', 'grp.org.chat.settings'])
        ->and($tabs(true, true))->toBe(['grp.org.chat.inbox', 'grp.org.chat.supervision', 'grp.org.chat.reports', 'grp.org.chat.settings']);

    $assignment->forceDelete();
    foreach ([$held, $loose] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
    $agentProfile->forceDelete();
});

test('supervision lists unanswered conversations oldest first, with their owner, and a supervisor can assign them', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agentProfile = ChatAgent::create([
        'user_id'              => $agent->id,
        'max_concurrent_chats' => 10,
        'is_available'         => true,
        'presence_status'      => ChatAgentPresenceStatusEnum::ONLINE,
        'last_heartbeat_at'    => now(),
    ]);

    $conversation = function (ChatChannelEnum $channel, \Illuminate\Support\Carbon $customerWrote, ?\Illuminate\Support\Carbon $weReplied = null): ChatSession {
        $session = ChatSession::create([
            'shop_id'                 => $this->shop->id,
            'ulid'                    => (string) Str::ulid(),
            'channel'                 => $channel,
            'status'                  => ChatSessionStatusEnum::WAITING,
            'last_visitor_message_at' => $customerWrote,
            'last_agent_message_at'   => $weReplied,
            'metadata'                => $channel === ChatChannelEnum::EMAIL ? ['email_subject' => 'Missing parcel'] : [],
        ]);
        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);
        $message->forceFill(['created_at' => $customerWrote])->save();

        return $session;
    };

    $oldEmail  = $conversation(ChatChannelEnum::EMAIL, now()->subYears(20));
    $newChat   = $conversation(ChatChannelEnum::WEBSITE, now()->subYears(20)->addMinutes(5));
    $answered  = $conversation(ChatChannelEnum::EMAIL, now()->subYears(20), now()->subYears(20)->addHour());

    $supervision = fn () => $this->actingAs($supervisor)
        ->get(route('grp.org.chat.supervision.dashboard', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Chat/Supervision'))
        ->viewData('page')['props'];

    $props = $supervision();
    $queue = collect($props['queue']);

    expect($queue->pluck('ulid')->take(2)->all())->toBe([(string) $oldEmail->ulid, (string) $newChat->ulid])
        ->and($queue->pluck('ulid')->all())->not->toContain((string) $answered->ulid)
        ->and($queue->first()['subject'])->toBe('Missing parcel')
        ->and($queue->first()['owner'])->toBeNull()
        ->and($props['stats']['unanswered_emails'])->toBeGreaterThanOrEqual(1)
        ->and($props['stats']['unassigned'])->toBeGreaterThanOrEqual(2)
        ->and(collect($props['agents'])->firstWhere('id', $agentProfile->id)['shop_ids'])->toContain($this->shop->id);

    $this->actingAs($supervisor)
        ->patchJson(route('grp.org.chat.agents.assign', [$this->organisation->slug, $oldEmail->ulid]), ['agent_id' => $agentProfile->id])
        ->assertOk();

    expect(collect($supervision()['queue'])->firstWhere('ulid', (string) $oldEmail->ulid)['owner']['id'])->toBe($agentProfile->id);

    $this->actingAs($supervisor)
        ->get(route('grp.org.chat.supervision', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Chat/Inbox')->where('supervisor', true));

    foreach ([$oldEmail, $newChat, $answered] as $session) {
        $session->assignments()->forceDelete();
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
    $agentProfile->forceDelete();
});

test('a conversation row shows what the customer wrote, not the translation the agent reads', function () {
    $session = ChatSession::create([
        'shop_id'  => $this->shop->id,
        'ulid'     => (string) Str::ulid(),
        'channel'  => ChatChannelEnum::EMAIL,
        'status'   => ChatSessionStatusEnum::WAITING,
        'metadata' => ['email_subject' => 'Chýbajúce vonné kužele'],
    ]);
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'One pack of incense cones is missing',
        'original_text'   => 'V balíku chýba jedno balenie vonných kužeľov',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);

    $session->load(['messages' => fn ($q) => $q->latest()->limit(1)]);
    $row = (new \App\Http\Resources\CRM\Livechat\ChatSessionListResource($session))->resolve();

    expect($row['last_message']['message'])->toStartWith('V balíku chýba');

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('the merged list says how many conversations it holds, so the mail tabs can count them', function () {
    $filters = [
        'shop_ids'    => [$this->shop->id],
        'pairs'       => ['email:guest'],
        'statuses'    => [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value],
        'reply_state' => 'unanswered',
        'limit'       => 1,
    ];
    $before = \App\Actions\Chat\GetCrossChannelSessions::make()->handle($filters)['total'];

    $session = ChatSession::create([
        'shop_id'                 => $this->shop->id,
        'ulid'                    => (string) Str::ulid(),
        'channel'                 => ChatChannelEnum::EMAIL,
        'status'                  => ChatSessionStatusEnum::WAITING,
        'last_visitor_message_at' => now(),
    ]);
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'Where is my parcel?',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);

    $after = \App\Actions\Chat\GetCrossChannelSessions::make()->handle($filters);

    expect($after['total'])->toBe($before + 1)
        ->and($after['rows'])->toHaveCount(1);

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('a conversation is found by the address it came from and by the number of one of the customer\'s orders', function () {
    $conversation = function (array $attributes): ChatSession {
        $session = ChatSession::create(array_merge([
            'shop_id' => $this->shop->id,
            'ulid'    => (string) Str::ulid(),
            'channel' => ChatChannelEnum::EMAIL,
            'status'  => ChatSessionStatusEnum::CLOSED,
        ], $attributes));
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);

        return $session;
    };
    $found = fn (string $search) => collect(GetChatSessions::make()->handle([
        'shop_id'       => $this->shop->id,
        'statuses'      => [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value, ChatSessionStatusEnum::CLOSED->value],
        'closed_period' => 'all',
        'search'        => $search,
        'limit'         => 50,
    ])->items())->pluck('id');

    $address = 'courier-'.Str::lower(Str::random(8)).'@example.test';
    $byAddress = $conversation(['metadata' => ['email_from' => $address, 'email_subject' => 'Parcel 77']]);

    $webUser   = createWebUser(createCustomer($this->shop));
    $reference = 'TST'.random_int(100000, 999999);
    \Illuminate\Support\Facades\DB::table('orders')->insert([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $webUser->customer_id,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => \App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'slug'            => 'ord-'.uniqid(),
        'reference'       => $reference,
        'state'           => 'dispatched',
        'status'          => \App\Enums\Ordering\Order\OrderStatusEnum::SETTLED,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => now(),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
    $byOrder = $conversation(['web_user_id' => $webUser->id]);

    expect($found(Str::upper($address)))->toContain($byAddress->id)
        ->and($found('#'.Str::lower($reference)))->toContain($byOrder->id)
        ->and($found('#'.Str::lower($reference)))->not->toContain($byAddress->id);

    \Illuminate\Support\Facades\DB::table('orders')->where('reference', $reference)->delete();
    foreach ([$byAddress, $byOrder] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
});

test('folder counts follow the channel that is open, and courier mail is counted over the shops a supervisor picked', function () {
    setPermissionsTeamId($this->user->group_id);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));
    $profile = ChatAgent::create(['user_id' => $supervisor->id, 'max_concurrent_chats' => 5]);

    $counts = fn (?string $channel) => \App\Actions\Chat\ChatSession\GetAgentChatNotifications::make()->handle($profile, [$this->shop->id], $channel);
    $before = ['email' => $counts('email')['carriers'], 'website' => $counts('website')['carriers']];

    $courier = ChatSession::create([
        'shop_id'    => $this->shop->id,
        'ulid'       => (string) Str::ulid(),
        'channel'    => ChatChannelEnum::EMAIL,
        'status'     => ChatSessionStatusEnum::WAITING,
        'is_carrier' => true,
    ]);

    expect($counts('email')['carriers'])->toBe($before['email'] + 1)
        ->and($counts('website')['carriers'])->toBe($before['website'] + 1);

    $courier->forceDelete();
    $profile->forceDelete();
});

test('the chat app counts website chats and whatsapp together, and leaves email out', function () {
    setPermissionsTeamId($this->user->group_id);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));
    $profile = ChatAgent::create(['user_id' => $supervisor->id, 'max_concurrent_chats' => 5]);

    $counts = fn (?string $channel) => \App\Actions\Chat\ChatSession\GetAgentChatNotifications::make()->handle($profile, [$this->shop->id], $channel);

    foreach (['unclaimed', 'spam', 'promised'] as $folder) {
        expect($counts('chat')[$folder])->toBe($counts('website')[$folder] + $counts('whatsapp')[$folder])
            ->and($counts(null)[$folder])->toBe($counts('chat')[$folder] + $counts('email')[$folder]);
    }

    $this->actingAs($supervisor)
        ->getJson('/app/api/chats/users/'.$supervisor->id.'/agent-notifications?'.http_build_query(['shop_ids' => [$this->shop->id], 'channel' => 'chat']))
        ->assertOk()
        ->assertJsonPath('data.unclaimed', $counts('chat')['unclaimed']);

    $profile->forceDelete();
});

test('unread holds conversations with a customer message nobody opened, replied those where we wrote last', function () {
    $conversation = function (bool $read, bool $weWroteLast): ChatSession {
        $session = ChatSession::create([
            'shop_id'                 => $this->shop->id,
            'ulid'                    => (string) Str::ulid(),
            'channel'                 => ChatChannelEnum::WEBSITE,
            'status'                  => ChatSessionStatusEnum::ACTIVE,
            'last_visitor_message_at' => now()->subHour(),
            'last_agent_message_at'   => $weWroteLast ? now() : null,
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
            'is_read'         => $read,
        ]);

        return $session;
    };
    $ids = fn (array $filters) => collect(GetChatSessions::make()->handle(array_merge([
        'shop_id'  => $this->shop->id,
        'statuses' => [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value],
        'limit'    => 50,
    ], $filters))->items())->pluck('id');

    $unreadUnanswered = $conversation(read: false, weWroteLast: false);
    $readReplied      = $conversation(read: true, weWroteLast: true);

    expect($ids(['unread' => true]))->toContain($unreadUnanswered->id)->not->toContain($readReplied->id)
        ->and($ids(['reply_state' => 'waiting_on_customer']))->toContain($readReplied->id)->not->toContain($unreadUnanswered->id);

    foreach ([$unreadUnanswered, $readReplied] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
});

test('a supervisor reads spam over the shops they picked, not only the shops they work as an agent', function () {
    setPermissionsTeamId($this->user->group_id);

    $supervisor = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $this->organisation));

    $spam = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::WAITING,
        'is_spam' => true,
    ]);
    ChatMessage::create([
        'chat_session_id' => $spam->id,
        'message_text'    => 'Cheap followers',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);

    $this->actingAs($supervisor)
        ->getJson('/app/api/chats/sessions?'.http_build_query(['shop_ids' => [$this->shop->id], 'is_spam' => 1, 'limit' => 50]))
        ->assertOk()
        ->assertJsonFragment(['ulid' => $spam->ulid]);

    expect(collect(GetChatSessions::make()->handle(['is_spam' => true, 'limit' => 50])->items())->pluck('id'))->not->toContain($spam->id);

    $spam->messages()->forceDelete();
    $spam->forceDelete();
});

test('a closed conversation shows the last thing said in it, not the notice that it was closed', function () {
    $session = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::CLOSED,
    ]);
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'Thanks, that sorted it',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);
    $this->travel(1)->minutes();
    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'Chat session has been closed by system',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::SYSTEM,
    ]);

    $listed = collect(GetChatSessions::make()->handle([
        'shop_id'       => $this->shop->id,
        'statuses'      => [ChatSessionStatusEnum::CLOSED->value],
        'closed_period' => 'all',
        'limit'         => 50,
    ])->items())->firstWhere('id', $session->id);

    expect(\App\Http\Resources\CRM\Livechat\ChatSessionListResource::make($listed)->resolve()['last_message']['message'])->toBe('Thanks, that sorted it');

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('the chat list puts a cancel or change of address first, even when a website chat and whatsapp are listed together', function () {
    $conversation = function (array $metadata): ChatSession {
        $session = ChatSession::create([
            'shop_id'                 => $this->shop->id,
            'ulid'                    => (string) Str::ulid(),
            'channel'                 => ChatChannelEnum::WEBSITE,
            'status'                  => ChatSessionStatusEnum::WAITING,
            'last_visitor_message_at' => now(),
            'metadata'                => $metadata,
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);

        return $session;
    };

    $this->travel(-1)->hours();
    $waitingLonger = $conversation([]);
    $this->travelBack();
    $cancelling = $conversation([
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::KEY    => 'cancel_order',
        \App\Actions\Chat\ChatSession\FlagUrgentChatRequest::AT_KEY => now()->toIso8601String(),
    ]);

    $order = \App\Actions\Chat\GetCrossChannelSessions::make()->handle([
        'shop_ids' => [$this->shop->id],
        'pairs'    => ['website:guest', 'whatsapp:guest'],
        'statuses' => [ChatSessionStatusEnum::WAITING->value],
        'limit'    => 50,
    ])['rows']->pluck('session.id')->values();

    expect($order->search($cancelling->id))->toBeLessThan($order->search($waitingLonger->id));

    foreach ([$waitingLonger, $cancelling] as $session) {
        $session->messages()->forceDelete();
        $session->forceDelete();
    }
});

test('the calls list in the inbox holds only the shops the person may look at, and can be narrowed to customers or guests', function () {
    setPermissionsTeamId($this->user->group_id);

    $clerk = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $clerk->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));
    $agent = ChatAgent::create(['user_id' => $clerk->id, 'max_concurrent_chats' => 5]);

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $call = fn (string $contactType) => \App\Models\Chat\ChatPhoneCall::create([
        'group_id'      => $clerk->group_id,
        'shop_id'       => $this->shop->id,
        'chat_agent_id' => $agent->id,
        'user_id'       => $clerk->id,
        'status'        => \App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum::COMPLETED,
        'contact_type'  => $contactType,
        'contact_name'  => 'Caller '.Str::random(6),
        'started_at'    => now()->subMinutes(5),
        'ended_at'      => now(),
    ]);
    $customerCall = $call('customer');
    $guestCall    = $call('guest');

    $ids = fn (User $viewer, ?string $type = null, string $view = 'all') => collect(\App\Actions\Chat\PhoneCall\Json\GetChatPhoneCallsForInbox::make()->handle($viewer, [$this->shop->id], $type, $view)->items())->pluck('id');
    $onTheLine = \App\Models\Chat\ChatPhoneCall::create([
        'group_id'      => $clerk->group_id,
        'shop_id'       => $this->shop->id,
        'chat_agent_id' => $agent->id,
        'user_id'       => $clerk->id,
        'status'        => \App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum::IN_PROGRESS,
        'started_at'    => now(),
    ]);

    expect($ids($clerk))->toContain($customerCall->id)->toContain($guestCall->id)
        ->and($ids($clerk, 'guest'))->toContain($guestCall->id)->not->toContain($customerCall->id)
        ->and($ids($outsider))->toBeEmpty()
        ->and($ids($clerk, null, 'in_progress'))->toContain($onTheLine->id)->not->toContain($customerCall->id)
        ->and($ids($clerk, null, 'today'))->toContain($onTheLine->id)->toContain($customerCall->id);

    $onTheLine->forceDelete();
    $customerCall->forceDelete();
    $guestCall->forceDelete();
    $agent->forceDelete();
});

test('a list of conversations only ever holds shops the person asking may look at', function () {
    setPermissionsTeamId($this->user->group_id);

    $session = ChatSession::create([
        'shop_id' => $this->shop->id,
        'ulid'    => (string) Str::ulid(),
        'channel' => ChatChannelEnum::WEBSITE,
        'status'  => ChatSessionStatusEnum::WAITING,
    ]);

    ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_text'    => 'hello',
        'message_type'    => ChatMessageTypeEnum::TEXT,
        'sender_type'     => ChatSenderTypeEnum::GUEST,
    ]);

    // The queue is worked oldest first, so what this test looks for has to be old enough to be
    // on the first page of everything the rest of the file has left lying about.
    $session->update(['created_at' => now()->subYear()]);
    $session->messages()->update(['created_at' => now()->subYear()]);

    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    $ulidsSeenBy = fn (User $user, string $route, array $params) => collect(
        $this->actingAs($user, 'sanctum')
            ->getJson(route($route, $params))
            ->assertOk()
            ->json('data.sessions')
    )->pluck('ulid')->all();

    foreach (['grp.api.chats.sessions.index', 'grp.api.chats.all.sessions.index'] as $route) {
        // Naming the shop, or naming a colleague as "me", used to be all it took.
        expect($ulidsSeenBy($outsider, $route, ['shop_id' => $this->shop->id]))->toBe([])
            ->and($ulidsSeenBy($outsider, $route, []))->toBe([])
            ->and($ulidsSeenBy($outsider, $route, ['shop_id' => $this->shop->id, 'assigned_to_me' => $agent->id, 'statuses' => ['waiting']]))->toBe([])
            ->and($ulidsSeenBy($agent, $route, ['shop_id' => $this->shop->id]))->toContain($session->ulid);
    }

    $session->messages()->forceDelete();
    $session->forceDelete();
});

test('email does not sit under the website tab', function () {
    setPermissionsTeamId($this->user->group_id);

    $this->shop->update(['state' => \App\Enums\Catalogue\Shop\ShopStateEnum::OPEN]);

    $agent = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $agent->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    // A session only counts once somebody has written in it: an opened widget nobody typed
    // into is not a conversation, and the rail says so too.
    $created = [];

    $session = function (ChatChannelEnum $channel) use (&$created) {
        $session = ChatSession::create([
            'shop_id' => $this->shop->id,
            'ulid'    => (string) Str::ulid(),
            'channel' => $channel,
            'status'  => ChatSessionStatusEnum::WAITING,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'hello',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);

        $created[] = $session->id;

        return $session;
    };

    try {
        $session(ChatChannelEnum::WEBSITE);
        $emailSession = $session(ChatChannelEnum::EMAIL);
        $session(ChatChannelEnum::EMAIL)->update(['status' => ChatSessionStatusEnum::ACTIVE, 'is_rubbish' => true]);

        $props = $this->actingAs($agent)
            ->get(route('grp.org.shops.show.chat.inbox', [$this->organisation->slug, $this->shop->slug]))
            ->assertOk()
            ->viewData('page')['props'];

        $channels = collect($props['inboxes'])->firstWhere('slug', $this->shop->slug)['channels'];

        // A bounce notice under a tab marked Website reads as somebody waiting on the other end.
        expect(collect($channels)->pluck('key')->all())->toBe(['website', 'email', 'whatsapp']);

        // The columns are the same three for every shop so the rail reads down as one table; the
        // ones nothing arrives on are held open and unpickable rather than dropped.
        expect(collect($channels)->firstWhere('key', 'email')['available'])->toBeTrue();

        // Put aside keeps its status, and the list leaves it out, so the rail must as well or it
        // promises an active email nobody can find.
        expect(collect($channels)->firstWhere('key', 'email')['guest']['active'])->toBe(0);
        expect(collect($channels)->firstWhere('key', 'whatsapp')['available'])->toBeFalse();

        $website = collect(GetChatSessions::make()->handle([
            'shop_id' => $this->shop->id,
            'pairs'   => ['website:customer', 'website:guest'],
        ])->items());

        expect($website->pluck('id')->all())->not->toContain($emailSession->id);
    } finally {
        // One database serves the whole file, so anything left behind lands in the counts another
        // test makes of the same shop.
        ChatSession::whereIn('id', $created)->each(function (ChatSession $session) {
            $session->messages()->forceDelete();
            $session->forceDelete();
        });
    }
});

test('GetChatReports counts only conversations the visitor wrote in and measures the first reply', function () {
    $this->freezeTime();
    [, , $reportShop] = createOwnShop(__FILE__.':chat-reports');

    $session = fn (string $channel, int $shopId) => ChatSession::create([
        'ulid'             => (string)Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $shopId,
        'channel'          => $channel,
        'created_at'       => now()->subHours(2),
        'updated_at'       => now(),
    ]);

    $message = fn (ChatSession $chatSession, string $senderType, $minutesAgo) => ChatMessage::create([
        'chat_session_id' => $chatSession->id,
        'message_type'    => ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => $senderType,
        'sender_id'       => null,
        'message_text'    => 'x',
        'is_read'         => false,
        'created_at'      => now()->subMinutes($minutesAgo),
        'updated_at'      => now(),
    ]);

    $answered = $session('website', $reportShop->id);
    $message($answered, ChatSenderTypeEnum::GUEST->value, 100);
    $message($answered, ChatSenderTypeEnum::AGENT->value, 90);

    $answered->update(['topic' => ChatTopicEnum::ORDER_STATUS->value]);

    $emailed = $session('email', $reportShop->id);
    $message($emailed, ChatSenderTypeEnum::USER->value, 60);

    $widgetOnlyOpened = $session('website', $reportShop->id);

    $otherShopConversation = $session('website', $this->shop->id);
    $message($otherShopConversation, ChatSenderTypeEnum::GUEST->value, 30);

    $result = GetChatReports::make()->handle(collect([$reportShop->id]), '1w');

    expect($result['conversations'])->toBe(2)
        ->and($result['answered'])->toBe(1)
        ->and($result['unanswered'])->toBe(1)
        ->and($result['median_reply_minutes'])->toBe(10.0)
        ->and(collect($result['by_channel'])->firstWhere('channel', 'email')['conversations'])->toBe(1)
        ->and(collect($result['by_channel'])->firstWhere('channel', 'whatsapp')['conversations'])->toBe(0)
        ->and($result['by_topic'])->toHaveCount(1)
        ->and($result['by_topic'][0])->toMatchArray(['topic' => 'order_status', 'conversations' => 1, 'share' => 100.0, 'website' => 1, 'unanswered' => 0])
        ->and($result['unclassified'])->toBe(1)
        ->and($widgetOnlyOpened->exists)->toBeTrue();

    $agent = ChatAgent::firstOrCreate(['user_id' => $this->user->id], ['is_online' => true, 'max_concurrent_chats' => 100, 'current_chat_count' => 0]);
    foreach ([4, 5] as $rating) {
        $rated = $session('website', $reportShop->id);
        $message($rated, ChatSenderTypeEnum::GUEST->value, 50);
        $message($rated, ChatSenderTypeEnum::AGENT->value, 40)->update(['sender_id' => $agent->id]);
        $message($rated, ChatSenderTypeEnum::AGENT->value, 30)->update(['sender_id' => $agent->id]);
        $rated->update(['rating' => $rating]);
    }

    $result = GetChatReports::make()->handle(collect([$reportShop->id]), '1w');

    expect($result['agents'][0])->toMatchArray(['rating' => 4.5, 'ratings' => 2])
        ->and($result['agents'][0])->not->toHaveKey('rated_sessions')
        ->and($result['agents_total'])->toMatchArray(['rating' => 4.5, 'ratings' => 2]);

    $listed = function (array $filter) use ($reportShop) {
        request()->query->replace(['filter' => $filter]);

        return IndexChatConversations::make()->handle($reportShop)->getCollection()->pluck('rating')->sort()->values()->all();
    };

    expect($listed(['rated' => 1, 'replied' => $this->user->username.','.$result['window']]))->toBe([4.0, 5.0])
        ->and($listed(['rated' => 1, 'replied' => 'nobody,'.$result['window']]))->toBe([])
        ->and($listed(['rated' => 1, 'created_between' => now()->subDay()->toIso8601ZuluString().','.now()->toIso8601ZuluString()]))->toBe([4.0, 5.0]);
});

test('the closed list only holds what was closed today', function () {
    $closedSession = function (\Carbon\Carbon $closedAt) {
        $session = ChatSession::create([
            'ulid'             => (string) Str::ulid(),
            'status'           => ChatSessionStatusEnum::CLOSED,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $this->shop->id,
            'closed_at'        => $closedAt,
            'created_at'       => $closedAt,
            'updated_at'       => $closedAt,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'thanks',
            'created_at'      => $closedAt,
            'updated_at'      => $closedAt,
        ]);

        return $session;
    };

    $today     = $closedSession(now());
    $lastMonth = $closedSession(now()->subMonth());

    $mixed = collect(GetChatSessions::make()->handle([
        'statuses' => [ChatSessionStatusEnum::ACTIVE->value, ChatSessionStatusEnum::CLOSED->value],
        'shop_id'  => $this->shop->id,
        'limit'    => 1000,
    ])->items())->pluck('ulid');

    expect($mixed)->toContain($today->ulid)
        ->and($mixed)->not->toContain($lastMonth->ulid);
});

test('the closed list reaches back as far as the period asked for, newest first', function () {
    $closedSession = function (\Carbon\Carbon $closedAt) {
        $session = ChatSession::create([
            'ulid'             => (string) Str::ulid(),
            'status'           => ChatSessionStatusEnum::CLOSED,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $this->shop->id,
            'closed_at'        => $closedAt,
            'created_at'       => $closedAt,
            'updated_at'       => $closedAt,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'thanks',
            'created_at'      => $closedAt,
            'updated_at'      => $closedAt,
        ]);

        return $session;
    };

    $today     = $closedSession(now());
    $lastMonth = $closedSession(now()->subMonth());

    $ulids = collect(GetChatSessions::make()->handle([
        'statuses'      => [ChatSessionStatusEnum::CLOSED->value],
        'closed_period' => 'all',
        'shop_id'       => $this->shop->id,
    ])->items())->pluck('ulid');

    $thisWeek = collect(GetChatSessions::make()->handle([
        'statuses'      => [ChatSessionStatusEnum::CLOSED->value],
        'closed_period' => '1w',
        'shop_id'       => $this->shop->id,
    ])->items())->pluck('ulid');

    expect($ulids)->toContain($lastMonth->ulid)
        ->and($thisWeek)->toContain($today->ulid)
        ->and($thisWeek)->not->toContain($lastMonth->ulid)
        ->and($ulids->search($today->ulid))->toBeLessThan($ulids->search($lastMonth->ulid));
});

test('the email tabs split open conversations into unanswered and waiting on the customer', function () {
    $emailSession = function (\Carbon\Carbon $customerWrote, ?\Carbon\Carbon $weReplied, array $metadata = []) {
        $session = ChatSession::create([
            'ulid'                    => (string) Str::ulid(),
            'status'                  => ChatSessionStatusEnum::ACTIVE,
            'channel'                 => ChatChannelEnum::EMAIL,
            'guest_identifier'        => 'guest_'.Str::random(5),
            'language_id'             => 68,
            'priority'                => ChatPriorityEnum::NORMAL,
            'shop_id'                 => $this->shop->id,
            'last_visitor_message_at' => $customerWrote,
            'last_agent_message_at'   => $weReplied,
            'metadata'                => $metadata,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'hello',
        ]);

        return $session;
    };

    $neverAnswered = $emailSession(now()->subHour(), null);
    $wroteAgain    = $emailSession(now()->subMinutes(5), now()->subHour());
    $weSpokeLast   = $emailSession(now()->subHour(), now()->subMinutes(5));
    $setAside      = $emailSession(now()->subMinutes(5), now()->subHour(), ['waiting_for_customer' => ['until' => now()->addDay()->toIso8601String()]]);

    $listed = fn (string $replyState) => collect(GetChatSessions::make()->handle([
        'statuses'    => [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value],
        'reply_state' => $replyState,
        'shop_id'     => $this->shop->id,
        'limit'       => 1000,
    ])->items())->pluck('ulid');

    $unanswered = $listed('unanswered');
    $waiting    = $listed('waiting_on_customer');

    expect($unanswered)->toContain($neverAnswered->ulid, $wroteAgain->ulid)
        ->and($unanswered)->not->toContain($weSpokeLast->ulid)
        ->and($unanswered)->not->toContain($setAside->ulid)
        ->and($waiting)->toContain($weSpokeLast->ulid, $setAside->ulid)
        ->and($waiting)->not->toContain($neverAnswered->ulid)
        ->and($waiting)->not->toContain($wroteAgain->ulid);
});

test('all mail reads newest first, the queue oldest first', function () {
    $sessionAt = function (\Carbon\Carbon $at) {
        $session = ChatSession::create([
            'ulid'             => (string) Str::ulid(),
            'status'           => ChatSessionStatusEnum::ACTIVE,
            'channel'          => ChatChannelEnum::EMAIL,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $this->shop->id,
            'created_at'       => $at,
            'updated_at'       => $at,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'hello',
            'created_at'      => $at,
            'updated_at'      => $at,
        ]);

        return $session;
    };

    $older = $sessionAt(now()->subDays(2));
    $newer = $sessionAt(now()->subHour());

    $listed = fn (array $order) => collect(GetChatSessions::make()->handle([
        'statuses' => [ChatSessionStatusEnum::ACTIVE->value],
        'shop_id'  => $this->shop->id,
        'limit'    => 1000,
    ] + $order)->items())->pluck('ulid');

    $queue   = $listed([]);
    $allMail = $listed(['order' => 'newest']);

    expect($queue->search($older->ulid))->toBeLessThan($queue->search($newer->ulid))
        ->and($allMail->search($newer->ulid))->toBeLessThan($allMail->search($older->ulid));
});

test('GetChatSessions limits the list to the shops asked for', function () {
    $otherShop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, \App\Models\Catalogue\Shop::factory()->definition());

    $sessionOn = function (int $shopId) {
        $session = ChatSession::create([
            'ulid'             => (string)Str::ulid(),
            'status'           => ChatSessionStatusEnum::ACTIVE,
            'guest_identifier' => 'guest_'.Str::random(5),
            'language_id'      => 68,
            'priority'         => ChatPriorityEnum::NORMAL,
            'shop_id'          => $shopId,
            'ai_model_version' => 'default',
            'created_at'       => now()->subYear(),
            'updated_at'       => now()->subYear(),
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::GUEST->value,
            'message_text'    => 'hello',
            'created_at'      => now()->subYear(),
            'updated_at'      => now()->subYear(),
        ]);

        return $session;
    };

    $mine  = $sessionOn($this->shop->id);
    $other = $sessionOn($otherShop->id);

    $both = collect(GetChatSessions::make()->handle([
        'shop_ids' => [$this->shop->id, $otherShop->id],
    ])->items())->pluck('ulid');

    $mine->refresh();

    $one = collect(GetChatSessions::make()->handle([
        'shop_ids' => [$otherShop->id],
    ])->items())->pluck('ulid');

    expect($both)->toContain($other->ulid)
        ->and($one)->toContain($other->ulid)
        ->and($one)->not->toContain($mine->ulid);
});

test('an agent unsubscribes a customer from every newsletter and reminder in one click, and when is kept', function () {
    $customer = createOwnCustomer($this->shop, 'unsubscribe-all');
    $comms    = $customer->comms ?? $customer->comms()->create([]);
    $comms->update(['is_subscribed_to_newsletter' => true, 'is_subscribed_to_marketing' => true, 'is_subscribed_to_abandoned_cart' => true]);

    actingAs($this->user);
    $this->postJson(route('grp.models.customer.unsubscribe_marketing', ['customer' => $customer->id]))
        ->assertOk()
        ->assertJsonPath('subscriptions.channels.newsletter.subscribed', false)
        ->assertJsonPath('subscriptions.channels.marketing.subscribed', false)
        ->assertJsonPath('subscriptions.channels.newsletter.unsubscribed_at', now()->toDateString());

    $comms->refresh();
    expect($comms->is_subscribed_to_abandoned_cart)->toBeFalse()
        ->and($comms->newsletter_unsubscribed_at)->not->toBeNull();
});

test('the chat customer panel says what erasing a customer would keep, and offers it only to whoever may do it', function () {
    $customer = createOwnCustomer($this->shop, 'gdpr-panel');

    $erasure = \App\Actions\Chat\ChatSession\GetChatCustomerProfile::make()->contactAndLastOrders($customer)['erasure'];

    expect($erasure['orders'])->toBe(0)
        ->and($erasure['invoices'])->toBe(0)
        ->and($erasure['confirmation'])->toBe($customer->reference)
        ->and($erasure['route'])->toBeNull();
});

test('an email is found by an order or consignment number in its subject or body', function () {
    \Illuminate\Support\Facades\Http::fake();
    config(['scout.driver' => 'collection']);

    $bySubject = noiseTestEmailSession($this->shop, 'lotion@example.com', 'GB589232', 'Please see photo', ['email_subject' => 'GB589232']);
    $byBody    = noiseTestEmailSession($this->shop, 'parcel@example.com', 'Delivery', 'Consignment 1Z999AA10123456784 never arrived');
    $unrelated = noiseTestEmailSession($this->shop, 'other@example.com', 'Hello', 'Where is my quokka order');

    $found = fn (string $search) => collect(GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'search' => $search])->items())
        ->pluck('id')->all();

    expect($found('GB589232'))->toBe([$bySubject->id])
        ->and($found('1z999aa10123456784'))->toBe([$byBody->id])
        ->and($found('where is my quokka'))->toBe([$unrelated->id]);
});

test('a whatsapp conversation is found by an order number in its messages', function () {
    config(['scout.driver' => 'collection']);

    $byOrder   = noiseTestWhatsappSession($this->shop, '+447500000010', 'Where is my order GB589232 please');
    $byKeyword = noiseTestWhatsappSession($this->shop, '+447500000011', 'Consignment 1Z999AA10123456784 never arrived');
    $unrelated = noiseTestWhatsappSession($this->shop, '+447500000012', 'Hello quokka');

    $found = fn (string $search) => collect(GetMetaChatSessions::make()->handle(['shop_id' => $this->shop->id, 'search' => $search])->items())
        ->pluck('id')->all();

    expect($found('GB589232'))->toBe([$byOrder->id])
        ->and($found('589232'))->toBe([$byOrder->id])
        ->and($found('1z999aa10123456784'))->toBe([$byKeyword->id])
        ->and($found('hello quokka'))->toBe([$unrelated->id]);
});

test('the chat search scope returns whatsapp messages', function () {
    // ponytail: neither test Scout driver reproduces Typesense's hits/document shape, so
    // rawDocuments() is stubbed and the real handle() mapping runs against a genuine message.
    $session = noiseTestWhatsappSession($this->shop, '+447500000013', 'Where is order GB588634 please');
    $message = $session->messages()->first();

    $action = \Mockery::mock(\App\Actions\Search\SearchChat::class)->makePartial();
    $action->shouldAllowMockingProtectedMethods();
    $action->shouldReceive('rawDocuments')->twice()->andReturn([], [$message->toSearchableArray()]);

    $results = $action->handle('588634', ['shop_ids' => [$this->shop->id]]);

    expect($results['results']['chat_messages'])->toBe([])
        ->and(collect($results['results']['whatsapp_messages'])->pluck('id'))->toContain($message->id);
});

test('the chat search scope returns nothing and never queries the engine without shop_ids', function () {
    $action = \Mockery::mock(\App\Actions\Search\SearchChat::class)->makePartial();
    $action->shouldAllowMockingProtectedMethods();
    $action->shouldReceive('rawDocuments')->never();

    $withoutOption = $action->handle('588634');
    $withEmpty     = $action->handle('588634', ['shop_ids' => []]);

    expect($withoutOption['results'])->toBe(['chat_messages' => [], 'whatsapp_messages' => []])
        ->and($withEmpty['results'])->toBe(['chat_messages' => [], 'whatsapp_messages' => []]);
});

test('a whatsapp message redirects to its conversation in the customer inbox', function () {
    $session = noiseTestWhatsappSession($this->shop, '+447500000014', 'Where is order GB588634 please');
    $message = $session->messages()->first();

    $response = $this->actingAs($this->user)->get(
        route('grp.majordomo.redirect_whatsapp_message', $message->id)
    );

    $response->assertRedirect(
        route('grp.org.chat.inbox', [$this->organisation->slug, 'channel' => 'whatsapp', 'session' => $session->ulid])
    );
});

test('ctrl k chat search excludes shops the user has no chat permission on', function () {
    $outsider = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    \App\Actions\Search\SearchChat::shouldRun()
        ->withArgs(fn (string $query, array $options) => !in_array($this->shop->id, $options['shop_ids'] ?? [], true))
        ->once()
        ->andReturn(['scope' => 'chat', 'results' => ['chat_messages' => [], 'whatsapp_messages' => []]]);

    actingAs($outsider);

    get(route('grp.search.index', ['q' => '588634', 'route_src' => 'grp.chat.reports']))->assertOk();
});

test('ctrl k chat search includes shops the user may view chat on', function () {
    $viewer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

    \App\Actions\Search\SearchChat::shouldRun()
        ->withArgs(fn (string $query, array $options) => in_array($this->shop->id, $options['shop_ids'] ?? [], true))
        ->once()
        ->andReturn(['scope' => 'chat', 'results' => ['chat_messages' => [], 'whatsapp_messages' => []]]);

    actingAs($viewer);

    get(route('grp.search.index', ['q' => '588634', 'route_src' => 'grp.chat.reports']))->assertOk();
});

test('GetChatCustomerTimeline puts every channel, the orders and what is still owed in one line', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $webUser  = StoreWebUser::make()->action($customer, WebUser::factory()->definition());

    $earlierWebsite = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::CLOSED,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'web_user_id'      => $webUser->id,
        'topic'            => ChatTopicEnum::MISSING_OR_DAMAGED->value,
        'metadata'         => ['ai_summary' => ['summary' => 'Two candles missing', 'status' => 'resolved']],
        'created_at'       => now()->subDays(10),
        'last_visitor_message_at' => now()->subDays(10),
    ]);

    $channel = MetaChannel::firstOrCreate(['code' => 'whatsapp'], ['name' => 'WhatsApp']);

    $whatsapp = MetaChatSession::create([
        'ulid'            => (string) Str::ulid(),
        'meta_channel_id' => $channel->id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'phone_number'    => '+628123456789',
        'status'          => ChatSessionStatusEnum::CLOSED,
        'language_id'     => 68,
        'priority'        => ChatPriorityEnum::NORMAL,
        'created_at'      => now()->subDays(2),
        'last_visitor_message_at' => now()->subDays(2),
    ]);

    $current = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'status'           => ChatSessionStatusEnum::ACTIVE,
        'guest_identifier' => 'guest_'.Str::random(5),
        'language_id'      => 68,
        'priority'         => ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'web_user_id'      => $webUser->id,
    ]);

    $unpaidInvoice = \App\Actions\Accounting\Invoice\StoreInvoice::make()
        ->action($customer, \App\Models\Accounting\Invoice::factory()->definition());
    $unpaidInvoice->update(['pay_status' => \App\Enums\Accounting\Invoice\InvoicePayStatusEnum::UNPAID]);

    $events = collect(GetChatCustomerTimeline::make()->handle($current)['events']);

    $conversations = $events->where('type', 'conversation');

    expect($conversations->pluck('metadata.ulid')->all())->toBe([$whatsapp->ulid, $earlierWebsite->ulid])
        ->and($conversations->pluck('metadata.channel')->all())->toBe(['whatsapp', 'website'])
        ->and($conversations->last()['comment'])->toBe('Two candles missing')
        ->and($events->pluck('metadata.ulid'))->not->toContain($current->ulid)
        ->and($events->pluck('datetime')->filter()->values()->all())
        ->toBe($events->pluck('datetime')->filter()->sortDesc()->values()->all());

    expect($events->where('type', 'invoice_open')->pluck('metadata.reference'))
        ->toContain($unpaidInvoice->reference);
});

test('every agent on the shop sees the couriers and colleagues folders, whoever picked the conversation up', function () {
    setPermissionsTeamId($this->user->group_id);

    $clerk = function () {
        $user = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
        $user->assignRole(RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        return [$user, ChatAgent::create(['user_id' => $user->id, 'max_concurrent_chats' => 10])];
    };

    [$holder, $holderProfile] = $clerk();
    [$colleague]              = $clerk();

    $heldFolder = function (string $flag) use ($holderProfile) {
        $session = ChatSession::create([
            'shop_id' => $this->shop->id,
            'ulid'    => (string) Str::ulid(),
            'channel' => ChatChannelEnum::EMAIL,
            'status'  => ChatSessionStatusEnum::ACTIVE,
            $flag     => true,
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'message_text'    => 'Parcel 1307 is missing a box',
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::GUEST,
        ]);
        ChatAssignment::create([
            'chat_session_id' => $session->id,
            'chat_agent_id'   => $holderProfile->id,
            'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
            'assigned_at'     => now(),
        ]);

        return $session;
    };

    $courier      = $heldFolder('is_carrier');
    $staffMessage = $heldFolder('is_colleague');

    $folderSeenBy = fn (User $user, string $folder) => collect(GetChatSessions::make()->handle([
        $folder          => true,
        'statuses'       => ['active'],
        'assigned_to_me' => $user->id,
    ])->items())->pluck('id')->all();

    expect($folderSeenBy($colleague, 'carrier'))->toContain($courier->id)
        ->and($folderSeenBy($holder, 'carrier'))->toContain($courier->id)
        ->and($folderSeenBy($colleague, 'colleague'))->toContain($staffMessage->id)->not->toContain($courier->id);
});

test('pictures with the same name get the next free number without walking every taken one', function () {
    $session = ChatSession::create([
        'ulid'             => (string) Str::ulid(),
        'shop_id'          => $this->shop->id,
        'language_id'      => 68,
        'status'           => ChatSessionStatusEnum::ACTIVE->value,
        'priority'         => ChatPriorityEnum::NORMAL->value,
        'guest_identifier' => 'guest-'.Str::random(8),
    ]);
    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => ChatMessageTypeEnum::IMAGE->value,
        'sender_type'     => ChatSenderTypeEnum::GUEST->value,
    ]);
    $name = 'pasted'.strtolower(Str::random(10));

    $slugs = collect(range(1, 6))->map(fn () => chatFileOnMessage($message, "$name.png")->slug)->all();

    expect($slugs)->toBe([$name, "$name-1", "$name-2", "$name-3", "$name-4", "$name-5"]);

    \App\Models\Helpers\Media::where('slug', "$name-2")->forceDelete();

    $refilled = chatFileOnMessage($message, "$name.png")->slug;
    $next     = chatFileOnMessage($message, "$name.png")->slug;

    expect($refilled)->toBe("$name-2")
        ->and($next)->toBe("$name-6")
        ->and(\App\Models\Helpers\Media::whereIn('slug', [$refilled, $next])->count())->toBe(2);

    // As many pictures called "image" as production holds: the package walked 2,209 lookups to
    // reach image-2209, the same number now comes back in a couple of dozen.
    $taken = array_flip(['image', ...array_map(fn ($number) => "image-$number", range(1, 2208))]);
    $media = new class () extends \App\Models\Helpers\Media {
        public array $taken = [];

        public int $lookups = 0;

        public function nextFreeSlug(string $slug): string
        {
            $this->slugOptions = $this->getSlugOptions();

            return $this->makeSlugUnique($slug);
        }

        protected function otherRecordExistsWithSlug(string $slug): bool
        {
            $this->lookups++;

            return isset($this->taken[$slug]);
        }
    };
    $media->taken = $taken;

    expect($media->nextFreeSlug('image'))->toBe('image-2209')
        ->and($media->lookups)->toBeLessThan(30)
        ->and($media->nextFreeSlug('avatar'))->toBe('avatar');
});

test('the inbox speed measured in the browser is stored for the person who waited', function () {
    $before = DB::table('chat_inbox_timings')->max('id') ?? 0;

    actingAs($this->user)->postJson(route('grp.chat.inbox_timings.store'), [
        'samples' => [
            ['measure' => 'inbox_open', 'duration_ms' => 1834, 'is_full_load' => true],
            ['measure' => 'conversation_open', 'duration_ms' => 412, 'channel' => 'email'],
            ['measure' => 'reply_sent', 'duration_ms' => 7311, 'channel' => 'website', 'is_full_load' => true],
            ['measure' => 'freeze', 'duration_ms' => 260],
        ],
    ])->assertNoContent();

    $stored = DB::table('chat_inbox_timings')->where('id', '>', $before)->orderBy('id')->get();

    expect($stored->pluck('measure')->all())->toBe(['inbox_open', 'conversation_open', 'reply_sent', 'freeze'])
        ->and($stored->pluck('duration_ms')->all())->toBe([1834, 412, 7311, 260])
        ->and($stored->pluck('channel')->all())->toBe([null, 'email', 'website', null])
        ->and($stored->pluck('is_full_load')->all())->toBe([true, null, null, null])
        ->and($stored->pluck('user_id')->unique()->all())->toBe([$this->user->id]);

    actingAs($this->user)->postJson(route('grp.chat.inbox_timings.store'), [
        'samples' => [['measure' => 'page_load', 'duration_ms' => 10]],
    ])->assertUnprocessable();

    expect(DB::table('chat_inbox_timings')->where('id', '>', $before)->count())->toBe(4);
});
