<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Nov 2025 13:05:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use Illuminate\Support\Arr;
use App\Actions\Chat\ChatSession\AssignChatToAgent;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

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

describe('staff messaging', function () {
    beforeEach(function () {
        $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        if ($this->otherUser->group_id !== $this->user->group_id) {
            $this->otherUser->update(['group_id' => $this->user->group_id]);
        }
    });

    test('dm is created once and reused', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $first  = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $second = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->otherUser, ['user_ids' => [$this->user->id]]);

        expect($first->id)->toBe($second->id)
            ->and($first->type)->toBe('dm')
            ->and($first->participants()->count())->toBe(2);
    });

    test('message is sent, counted unread for the other participant, then read', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => '<b>hello</b> there']);

        Event::assertDispatched(\App\Events\StaffMessageSent::class);
        expect($conversation->messages()->first()->body)->toBe('hello there');

        $mine   = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id);
        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->otherUser)->firstWhere('id', $conversation->id);
        expect((int) $mine->unread_count)->toBe(0)->and((int) $theirs->unread_count)->toBe(1);

        \App\Actions\Chat\Staff\MarkStaffConversationRead::run($conversation, $this->otherUser);
        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->otherUser)->firstWhere('id', $conversation->id);
        expect((int) $theirs->unread_count)->toBe(0);
    });

    test('a message sent in the same second the conversation was read still counts unread', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other        = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00.100000');
        \App\Actions\Chat\Staff\MarkStaffConversationRead::run($conversation, $other);
        \Illuminate\Support\Carbon::setTestNow('2026-09-14 10:00:00.200000');
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'same second']);
        \Illuminate\Support\Carbon::setTestNow();

        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($other)->firstWhere('id', $conversation->id);
        expect((int) $theirs->unread_count)->toBe(1);
    });

    test('the conversations list loads avatars and contexts once, not per conversation', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'list me']);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $rows = \App\Http\Resources\Chat\StaffConversationResource::collection(
            \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)
        )->resolve();
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        expect(collect($rows)->pluck('ulid'))->toContain($conversation->ulid)
            ->and($queries->filter(fn (string $sql) => str_contains($sql, 'from "media"'))->count())->toBeLessThanOrEqual(1)
            ->and($queries->filter(fn (string $sql) => preg_match('/from "(delivery_notes|orders|picking_sessions)"/', $sql))->count())->toBeLessThanOrEqual(3);
    });

    test('reaction toggles on and off', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'react']);

        \App\Actions\Chat\Staff\ToggleStaffMessageReaction::run($message, $this->otherUser, '👍');
        expect($message->reactions()->count())->toBe(1);
        \App\Actions\Chat\Staff\ToggleStaffMessageReaction::run($message, $this->otherUser, '👍');
        expect($message->reactions()->count())->toBe(0);
    });

    test('image only message stores media', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        \Illuminate\Support\Facades\Storage::fake('public');
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        $response = actingAs($this->user)
            ->post(route('grp.chat.staff.conversations.messages.store', $conversation), [
                'image' => \Illuminate\Http\UploadedFile::fake()->image('screenshot.png', 40, 40),
            ]);
        $response->assertSuccessful();
        expect($response->json('data.image'))->not->toBeNull()
            ->and($response->json('data.body'))->toBe('');
    });

    test('non participant is rejected by the message endpoint', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $stranger     = User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($stranger)
            ->postJson(route('grp.chat.staff.conversations.messages.store', $conversation), ['body' => 'intrude'])
            ->assertForbidden();
    });

    test('empty conversation is listed only for its creator until a message is sent', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $newcomer     = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$newcomer->id]]);

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->not->toBeNull()
            ->and(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($newcomer)->firstWhere('id', $conversation->id))->toBeNull();

        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'now you see me']);

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($newcomer)->firstWhere('id', $conversation->id))->not->toBeNull();
    });
});

describe('staff messaging mentions', function () {
    test('mention resolves to participant and flags unread for them', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $nickname = 'adamm'.Str::lower(Str::random(6));
        $other    = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id, 'nickname' => $nickname]);

        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => "hey @$nickname check this"]);

        expect($message->mentions)->toBe([$other->id]);

        $theirs = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($other)->firstWhere('id', $conversation->id);
        $mine   = \App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id);
        expect($theirs->has_mention)->toBeTruthy()
            ->and($mine->has_mention)->toBeFalsy();
    });
});

describe('staff messaging page', function () {
    beforeEach(function () {
        $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
    });

    test('index renders the messaging page', function () {
        actingAs($this->user)->get(route('grp.chat.staff.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging'));
    });

    test('show renders the messaging page for a participant', function () {
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);

        actingAs($this->user)->get(route('grp.chat.staff.show', $conversation))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Chat/StaffMessaging'));
    });
});

describe('staff messaging context shortcuts', function () {
    test('ask CRM on an order opens one group conversation with the shop CRM role holders', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        $product = Product::where("shop_id", $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);

        $crmUser = User::factory()->create(['group_id' => $this->user->group_id]);
        setPermissionsTeamId($this->user->group_id);
        $crmUser->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id'))->toContain($crmUser->id);

        $first  = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $order, 'crm');
        $second = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $order, 'crm');

        expect($first->id)->toBe($second->id)
            ->and($first->type)->toBe('group')
            ->and($first->context_type)->toBe('Order')
            ->and($first->context_id)->toBe($order->id)
            ->and($first->participants()->pluck('users.id'))->toContain($crmUser->id, $this->user->id);

        $resource = (new \App\Http\Resources\Chat\StaffConversationResource($first->load('participants')))->resolve();
        expect($resource['context_label'])->toBe($order->reference)
            ->and($resource['context_url'])->toContain($order->slug);
    });
});

describe('staff messaging audience mapping', function () {
    test('org crm list is ignored, shop crm list wins', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $other   = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);

        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, now()->timestamp);
        $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->not->toBe([$other->id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$other->id]);
    });

    test('org warehouse list is the fallback when shop has none', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $other   = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);

        $shopSettings = $this->shop->settings ?? [];
        \Illuminate\Support\Arr::set($shopSettings, 'staff_chat.warehouse_user_ids', []);
        $this->shop->update(['settings' => $shopSettings]);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$other->id, now()->timestamp);
        $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['staff_chat' => ['warehouse_user_ids' => [$other->id]]])]);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('warehouse', $order)->pluck('id')->all())->toBe([$other->id]);
    });
});

describe('staff messaging routing', function () {
    test('shop primary wins over org', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id]]])]);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$a->id, now()->timestamp);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$a->id]);
    });

    test('inactive primary falls back to active backup', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);
        $b       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id], 'crm_backup_user_ids' => [$b->id]]])]);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$a->id);
        \Illuminate\Support\Facades\Cache::put('staff-last-active:'.$b->id, now()->timestamp);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->all())->toBe([$b->id]);
    });

    test('nobody active returns primary and backup together', function () {
        $product = Product::where('shop_id', $this->shop->id)->first() ?? createProduct($this->shop)[1];
        $order   = createOrder($this->customer, $product);
        $a       = User::factory()->create(['group_id' => $this->user->group_id]);
        $b       = User::factory()->create(['group_id' => $this->user->group_id]);

        $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], ['staff_chat' => ['crm_user_ids' => [$a->id], 'crm_backup_user_ids' => [$b->id]]])]);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$a->id);
        \Illuminate\Support\Facades\Cache::forget('staff-last-active:'.$b->id);

        expect(\App\Actions\Chat\Staff\GetStaffAudience::run('crm', $order)->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    });
});

describe('staff messaging picking session', function () {
    test('ask CRM on a picking session opens one group conversation with the shop CRM role holders', function () {
        $pickingSession = \App\Models\Inventory\PickingSession::create([
            'group_id'        => $this->warehouse->group_id,
            'organisation_id' => $this->warehouse->organisation_id,
            'warehouse_id'    => $this->warehouse->id,
            'user_id'         => $this->user->id,
            'reference'       => 'staff-chat-'.uniqid(),
        ]);

        $first  = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $pickingSession, 'crm');
        $second = \App\Actions\Chat\Staff\OpenStaffContextConversation::run($this->user, $pickingSession, 'crm');

        expect($first->id)->toBe($second->id)
            ->and($first->context_type)->toBe('PickingSession');

        $resource = (new \App\Http\Resources\Chat\StaffConversationResource($first->load('participants')))->resolve();
        expect($resource['context_url'])->toContain($pickingSession->slug);
    });
});

describe('staff messaging team', function () {
    test('team membership toggles and coworkers endpoint flags it', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        expect(\App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other))->toBeTrue()
            ->and($this->user->teamMembers()->count())->toBe(1);

        $coworkers = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');
        $row = collect($coworkers)->firstWhere('id', $other->id);
        expect($row['in_team'])->toBeTrue()
            ->and($row['organisation_ids'])->toBeArray();

        expect(\App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other))->toBeFalse()
            ->and($this->user->teamMembers()->count())->toBe(0);
    });

    test('the coworkers poll shares the group list between viewers but keeps team and closeness per viewer', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        \Illuminate\Support\Facades\Cache::forget('staff-coworkers:'.$this->user->group_id);
        \App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other);

        actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $mine = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');
        $queryLog          = collect(\Illuminate\Support\Facades\DB::getQueryLog());
        $queriesOnWarmPoll = $queryLog
            ->filter(fn (array $query) => preg_match('/"(users|user_has_models|employees|media|user_has_team_members)"/', $query['query']))
            ->count();
        expect($queryLog->count())->toBeLessThanOrEqual(6);
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $theirs = actingAs($other)->getJson(route('grp.chat.staff.coworkers.index'))->assertOk()->json('data');

        expect(collect($mine)->firstWhere('id', $other->id)['in_team'])->toBeTrue()
            ->and(collect($mine)->pluck('id'))->not->toContain($this->user->id)
            ->and(collect($theirs)->pluck('id'))->not->toContain($other->id)
            ->and(collect($theirs)->firstWhere('id', $this->user->id)['in_team'])->toBeFalse()
            ->and($queriesOnWarmPoll)->toBeLessThanOrEqual(2);

        $searched = actingAs($this->user)->getJson(route('grp.chat.staff.coworkers.index', ['q' => $other->chatName()]))->assertOk()->json('data');
        expect(collect($searched)->pluck('id'))->toContain($other->id);

        \App\Actions\Chat\Staff\ToggleStaffTeamMember::run($this->user, $other);
    });

    test('json-only grp routes exist and skip building the layout', function () {
        $routeNames = (new ReflectionClassConstant(\App\Http\Middleware\HandleInertiaGrpRequests::class, 'JSON_ONLY_ROUTES'))->getValue();

        foreach ($routeNames as $routeName) {
            expect(\Illuminate\Support\Facades\Route::has($routeName))->toBeTrue("$routeName is not a registered route");

            $request = \Illuminate\Http\Request::create('/');
            $request->setRouteResolver(fn () => \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName));

            expect(app(\App\Http\Middleware\HandleInertiaGrpRequests::class)->share($request))->toBe([]);
        }
    });

    test('a json call to any grp route skips the layout, an inertia visit still gets it', function () {
        $sendRoute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('grp.org.chat.agents.messages.send');
        $share     = function (array $headers) use ($sendRoute): array {
            $request = \Illuminate\Http\Request::create('/', 'POST', server: $headers);
            $request->setRouteResolver(fn () => $sendRoute);
            $request->setLaravelSession(app('session.store'));

            return app(\App\Http\Middleware\HandleInertiaGrpRequests::class)->share($request);
        };

        expect($share(['HTTP_ACCEPT' => 'application/json, text/plain, */*']))->toBe([])
            ->and($share(['HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_INERTIA' => 'true']))->toHaveKeys(['auth', 'flash', 'ziggy']);
    });
});

describe('staff messaging archive', function () {
    test('closing a conversation hides it until a new message arrives', function () {
        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $other, ['body' => 'hi']);

        \App\Actions\Chat\Staff\ArchiveStaffConversation::run($conversation, $this->user);
        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->toBeNull();

        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $other, ['body' => 'are you there?']);
        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->not->toBeNull();
    });

    test('the key a browser encrypts its saved chats with is personal, stable and never cached', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        $response = actingAs($this->user)->getJson(route('grp.chat.staff.cache_key'))->assertOk();
        $key      = $response->json('key');

        expect(strlen(base64_decode($key)))->toBe(32)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and(actingAs($this->user)->getJson(route('grp.chat.staff.cache_key'))->json('key'))->toBe($key)
            ->and(actingAs($other)->getJson(route('grp.chat.staff.cache_key'))->json('key'))->not->toBe($key);
    });

    test('reading a conversation tells the other participants when, for their read ticks', function () {
        Event::fake([\App\Events\StaffMessageSent::class, \App\Events\StaffConversationRead::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'can you check the pallet?']);

        actingAs($other)->postJson(route('grp.chat.staff.conversations.read', $conversation))->assertOk();

        Event::assertDispatched(\App\Events\StaffConversationRead::class, function (\App\Events\StaffConversationRead $event) use ($conversation, $other) {
            $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

            return $event->conversation->is($conversation)
                && $event->broadcastWith()['user_id'] === $other->id
                && $channels === ['private-grp.personal.'.$this->user->id];
        });

        $participants = collect((new \App\Http\Resources\Chat\StaffConversationResource($conversation->fresh()->load('participants')))->resolve()['participants']);

        expect($participants->firstWhere('id', $other->id)['last_read_at'])->not->toBeNull();
    });

    test('a task chat cannot be archived while the task is open and lives on the task page', function () {
        $task         = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Restock the blue mugs', 'department' => 'warehouse']);
        $conversation = $task->conversation;

        $conversationTask = (new \App\Http\Resources\Chat\StaffConversationResource($conversation))->resolve()['task'];
        expect($conversationTask['is_open'])->toBeTrue()
            ->and($conversationTask['status'])->toBe('todo')
            ->and($conversationTask['status_label'])->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::labels()['todo'])
            ->and($conversationTask['status_icon']['icon'])->not->toBeEmpty();

        actingAs($this->user)
            ->get(route('grp.chat.staff.show', $conversation))
            ->assertRedirect(route('grp.tasks.show', $task->reference));

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.archive', $conversation))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation');

        $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
        expect((new \App\Http\Resources\Chat\StaffConversationResource($conversation->fresh()))->resolve()['task']['status'])->toBe('done');

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.archive', $conversation))
            ->assertOk();

        expect(\App\Actions\Chat\Staff\Json\GetStaffConversations::run($this->user)->firstWhere('id', $conversation->id))->toBeNull();
    });

    test('a participant watches and unwatches a task chat, an outsider cannot', function () {
        $task         = \App\Actions\Tasks\StoreStaffTask::run($this->user, ['subject' => 'Check the courier labels', 'description' => 'Labels on the blue shelf are smudged', 'department' => 'warehouse']);
        $conversation = $task->conversation;

        $conversationTask = (new \App\Http\Resources\Chat\StaffConversationResource($conversation->fresh()))->resolve()['task'];
        expect($conversationTask['description'])->toBe('Labels on the blue shelf are smudged')
            ->and($conversationTask)->toHaveKeys(['model_label', 'model_url']);

        $isWatching = fn () => (bool) $conversation->participants()->where('users.id', $this->user->id)->first()?->pivot->is_watching;

        expect($isWatching())->toBeFalse();

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.watch', $conversation), ['is_watching' => true])
            ->assertOk()
            ->assertJson(['is_watching' => true]);

        expect($isWatching())->toBeTrue();

        $listed = actingAs($this->user)->getJson(route('grp.chat.staff.conversations.index'))->assertOk()->json('data');
        expect(collect($listed)->firstWhere('ulid', $conversation->ulid)['is_watching'] ?? null)->toBeTrue();

        actingAs($this->user)
            ->postJson(route('grp.chat.staff.conversations.watch', $conversation), ['is_watching' => false])
            ->assertOk()
            ->assertJson(['is_watching' => false]);

        expect($isWatching())->toBeFalse();

        $outsider = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->organisation->group, array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();

        actingAs($outsider)
            ->postJson(route('grp.chat.staff.conversations.watch', $conversation), ['is_watching' => true])
            ->assertForbidden();

        $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
    });
});

describe('staff messaging gifs', function () {
    test('search returns mapped klipy results', function () {
        \Illuminate\Support\Facades\Http::fake([
            'api.klipy.com/*' => \Illuminate\Support\Facades\Http::response([
                'result' => true,
                'data'   => [
                    'data'     => [
                        [
                            'id'    => '1',
                            'file'  => [
                                'sm' => ['gif' => ['url' => 'https://static.klipy.com/a/sm.gif', 'width' => 100, 'height' => 80]],
                                'md' => ['gif' => ['url' => 'https://static.klipy.com/a/md.gif', 'width' => 200, 'height' => 160]],
                            ],
                        ],
                    ],
                    'has_next'     => true,
                    'current_page' => 1,
                ],
            ]),
        ]);
        config(['services.klipy.key' => 'k']);

        actingAs($this->user)
            ->getJson(route('grp.chat.staff.gifs.search', ['q' => 'cat', 'page' => 1]))
            ->assertOk()
            ->assertJsonPath('data.0.url', 'https://static.klipy.com/a/md.gif')
            ->assertJsonPath('next', 2);
    });
});

describe('staff messaging nickname', function () {
    test('user sets a nickname and it appears in profile update and messages', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'Raulito'])
            ->assertOk();

        expect($this->user->fresh()->nickname)->toBe('Raulito');

        Event::fake([\App\Events\StaffMessageSent::class]);
        fakeActionJobs(\App\Actions\Chat\Staff\TranslateStaffMessage::class);
        $other        = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);
        $conversation = \App\Actions\Chat\Staff\StoreStaffConversation::run($this->user, ['user_ids' => [$other->id]]);
        $message      = \App\Actions\Chat\Staff\SendStaffMessage::run($conversation, $this->user, ['body' => 'hi']);

        $resource = (new \App\Http\Resources\Chat\StaffMessageResource($message->load(['user', 'translations', 'reactions', 'conversation'])))->resolve();
        expect($resource['user_name'])->toBe('Raulito');
    });

    test('nickname must be unique', function () {
        $other = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
            ?? User::factory()->create(['group_id' => $this->user->group_id]);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'SharedNick'])
            ->assertOk();

        actingAs($other)
            ->patchJson(route('grp.models.profile.update'), ['nickname' => 'SharedNick'])
            ->assertStatus(422);
    });
});

describe('staff messaging chat theme', function () {
    test('user can set chat theme', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'nord'])
            ->assertOk();

        expect(Arr::get($this->user->fresh()->settings, 'chat_theme'))->toBe('nord');
    });

    test('changing chat theme busts the cached layout props', function () {
        config()->set('ui.cache.layout', true);

        $props = new App\Actions\UI\Grp\GetFirstLoadProps()->handle($this->user);
        expect(Arr::get($props, 'layout.chat_theme'))->not->toBe('gruvbox');

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'gruvbox'])
            ->assertOk();

        $props = new App\Actions\UI\Grp\GetFirstLoadProps()->handle($this->user->fresh());
        expect(Arr::get($props, 'layout.chat_theme'))->toBe('gruvbox');
    });

    test('invalid chat theme is rejected', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['chat_theme' => 'not-a-theme'])
            ->assertStatus(422);
    });

    test('each person chooses their own alert sounds, which reach the layout', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_sounds' => ['chat' => 'submarine', 'whatsapp' => 'fart', 'email' => 'voice', 'colleague' => 'silent', 'waiting' => 'silent', 'ticket' => 'genie', 'ticket_new' => 'bells', 'ticket_answered' => 'knock', 'ticket_qa' => 'voice', 'pager' => 'bells']])
            ->assertOk();

        $expected = ['chat' => 'submarine', 'whatsapp' => 'fart', 'email' => 'voice', 'colleague' => 'silent', 'waiting' => 'silent', 'ticket' => 'genie', 'ticket_new' => 'bells', 'ticket_answered' => 'knock', 'ticket_qa' => 'voice'];

        expect(Arr::get($this->user->fresh()->settings, 'alert_sounds'))->toEqual($expected)
            ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['alert_sounds'])->toEqual($expected);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_sounds' => ['chat' => 'air-horn']])
            ->assertStatus(422);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_preview_seconds' => 10])
            ->assertOk();

        expect(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['alert_preview_seconds'])->toBe(10);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_preview_seconds' => 99])
            ->assertStatus(422);
    });

    test('each alerting event is switched on or off by way, and an unknown event or way is not kept', function () {
        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_events' => [
                'customer_message' => ['popup' => false, 'desktop' => true, 'sound' => '0'],
                'waiting'          => ['desktop' => false, 'popup' => false],
                'pager'            => ['sound' => false],
            ]])
            ->assertOk();

        $expected = [
            'customer_message' => ['popup' => false, 'desktop' => true, 'sound' => false],
            'waiting'          => ['desktop' => false],
        ];

        expect(Arr::get($this->user->fresh()->settings, 'alert_events'))->toEqual($expected)
            ->and(\App\Actions\SysAdmin\User\UI\GetLoggedUser::run($this->user->fresh())['settings']['alert_events'])->toEqual($expected);

        actingAs($this->user)
            ->patchJson(route('grp.models.profile.update'), ['alert_events' => ['colleague' => ['sound' => 'loud']]])
            ->assertStatus(422);
    });
});

describe('staff chat audience seeding', function () {
    test('seed fills an empty shop list from role holders and leaves a curated one alone', function () {
        $roleHolder = User::factory()->create(['group_id' => $this->user->group_id]);
        setPermissionsTeamId($this->user->group_id);
        $roleHolder->assignRole(\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_CLERK->value, $this->shop));

        $settings = $this->shop->settings ?? [];
        \Illuminate\Support\Arr::set($settings, 'staff_chat.crm_user_ids', []);
        $this->shop->update(['settings' => $settings]);

        \App\Actions\Chat\Staff\SeedStaffChatAudiences::run();

        expect(\Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'staff_chat.crm_user_ids'))->toContain($roleHolder->id);

        $curated  = User::factory()->create(['group_id' => $this->user->group_id]);
        $settings = $this->shop->fresh()->settings ?? [];
        \Illuminate\Support\Arr::set($settings, 'staff_chat.crm_user_ids', [$curated->id]);
        $this->shop->update(['settings' => $settings]);

        \App\Actions\Chat\Staff\SeedStaffChatAudiences::run();

        expect(\Illuminate\Support\Arr::get($this->shop->fresh()->settings, 'staff_chat.crm_user_ids'))->toBe([$curated->id]);
    });
});

test('a staff chat on an order opens only for staff who can see that order, INI-076', function () {
    list(, $product) = createProduct($this->shop);
    $order           = createOrder($this->customer, $product);
    $payload         = ['context_type' => 'Order', 'context_id' => $order->id, 'audience' => 'crm'];

    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["human-resources.{$this->organisation->id}.view"]);
    $this->postJson(route('grp.chat.staff.context.open'), $payload)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["orders.{$this->shop->id}.view"]);
    $this->postJson(route('grp.chat.staff.context.open'), $payload)->assertSuccessful();
});

test('a staff member cannot start a conversation with only themselves', function () {
    actingAs($this->user);
    $before = \App\Models\Chat\StaffConversation::count();

    $this->postJson(route('grp.chat.staff.conversations.store'), ['user_ids' => [$this->user->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_ids');

    expect(\App\Models\Chat\StaffConversation::count())->toBe($before);
});

test('rail clicks are counted per day and button', function () {
    $key    = 'rail_clicks:'.now()->toDateString();
    $before = (int) \Illuminate\Support\Facades\Redis::hget($key, 'online');

    actingAs($this->user)
        ->postJson(route('grp.models.profile.rail-click'), ['button' => 'online'])
        ->assertNoContent();

    expect((int) \Illuminate\Support\Facades\Redis::hget($key, 'online'))->toBe($before + 1);

    actingAs($this->user)
        ->postJson(route('grp.models.profile.rail-click'), ['button' => 'bad key!'])
        ->assertUnprocessable();
});
