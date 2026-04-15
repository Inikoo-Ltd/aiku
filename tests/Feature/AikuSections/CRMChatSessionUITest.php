<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\CRM\ChatSession\StoreChatSession;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\SysAdmin\Permission;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Config; // @phpstan-ignore-line
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $web = Website::where('shop_id', $this->shop->id)->first();
    if (!$web) {
        $web = createWebsite($this->shop);
    }
    $this->web = $web;

    $chatSession = ChatSession::where('shop_id', $this->shop->id)->first();
    if (!$chatSession) {
        $chatSession = StoreChatSession::make()->handle([
            'language_id' => 68,
            'priority'    => ChatPriorityEnum::NORMAL->value,
            'shop_id'     => $this->shop->id,
        ]);
    }

    $this->chatSession = $chatSession;

    setPermissionsTeamId($this->shop->group_id);
    SeedShopPermissions::run($this->shop);

    $crmViewPermission = Permission::where('name', "crm.{$this->shop->id}.view")->first();
    if ($crmViewPermission) {
        $this->user->givePermissionTo($crmViewPermission);
    }

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    $this->user->refresh();
    actingAs($this->user);
});

it('can render chat sessions index in CRM', function () {
    $this->withoutExceptionHandling();

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

    $response = get(route('grp.org.shops.show.crm.chat_sessions.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->chatSession->id,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Shop/CRM/ChatSession')
            ->has('chatSession')
            ->has('messages');
    });
});
