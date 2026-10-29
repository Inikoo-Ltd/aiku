<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Chat\ChatSession\StartCustomerEmailChat;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatEmailContact;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

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
        createWebsite($this->shop);
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
        $this->shop         = $web->shop;
    }

    $this->otherShop = StoreShop::make()->action($this->organisation, Shop::factory()->definition());

    $otherOrganisation = Organisation::where('code', 'other')->first();
    if (!$otherOrganisation) {
        $otherOrganisation = StoreOrganisation::make()->action(
            $this->organisation->group,
            array_merge(Organisation::factory()->definition(), ['code' => 'other', 'type' => OrganisationTypeEnum::SHOP])
        );
    }
    $this->otherOrganisation = $otherOrganisation;

    $this->token = Str::lower(Str::random(10));
    $this->staff = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
});

function emailContactFor(Organisation $organisation, ?Shop $shop, string $name, string $email, ?string $company = null): ChatEmailContact
{
    return ChatEmailContact::create([
        'organisation_id' => $organisation->id,
        'shop_id'         => $shop?->id,
        'name'            => $name,
        'email'           => $email,
        'company'         => $company,
    ]);
}

test('the search offers the shop\'s contacts and the organisation\'s shared ones, never another shop\'s or organisation\'s', function () {
    $token = $this->token;

    $ownShop     = emailContactFor($this->organisation, $this->shop, "DPD $token", "dpd-$token@carrier.example.com");
    $orgWide     = emailContactFor($this->organisation, null, "Accounts $token", "accounts-$token@example.com");
    $otherShop   = emailContactFor($this->organisation, $this->otherShop, "GLS $token", "gls-$token@carrier.example.com");
    $otherOrgOne = emailContactFor($this->otherOrganisation, null, "Elsewhere $token", "elsewhere-$token@example.com");

    actingAsUserWithOnlyPermissions($this->staff, ["chat.{$this->shop->id}"]);

    $ids = getJson(route('grp.org.chat.agents.email_contacts.search', [$this->organisation->slug, $this->shop->id, 'query' => $token]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$ownShop->id, $orgWide->id])
        ->and($ids)->not->toContain($otherShop->id)
        ->and($ids)->not->toContain($otherOrgOne->id);

    $byCompany = emailContactFor($this->organisation, $this->shop, 'Somebody', "somebody-$token@example.com", "Freight $token Ltd");
    $ids       = getJson(route('grp.org.chat.agents.email_contacts.search', [$this->organisation->slug, $this->shop->id, 'query' => "freight $token"]))
        ->assertOk()
        ->json('data.*.id');
    expect($ids)->toBe([$byCompany->id]);

    $ids = getJson(route('grp.org.chat.agents.email_contacts.search', [$this->organisation->slug, $this->shop->id, 'query' => "dpd-$token@carrier"]))
        ->assertOk()
        ->json('data.*.id');
    expect($ids)->toBe([$ownShop->id]);
});

test('a saved contact needs a name and a valid address, and an address is saved once per shop and once for every shop', function () {
    $token = $this->token;
    actingAsUserWithOnlyPermissions($this->staff, ["chat.{$this->shop->id}"]);
    $storeRoute = route('grp.org.chat.agents.email_contacts.store', [$this->organisation->slug, $this->shop->id]);

    postJson($storeRoute, ['email' => "dpd-$token@carrier.example.com"])->assertUnprocessable()->assertJsonValidationErrors('name');
    postJson($storeRoute, ['name' => 'DPD', 'email' => 'not-an-address'])->assertUnprocessable()->assertJsonValidationErrors('email');

    postJson($storeRoute, ['name' => ' DPD ', 'email' => "DPD-$token@Carrier.example.com", 'company' => 'DPD UK', 'note' => 'Collections'])
        ->assertSuccessful()
        ->assertJsonPath('data.email', "dpd-$token@carrier.example.com")
        ->assertJsonPath('data.all_shops', false);

    $contact = ChatEmailContact::where('email', "dpd-$token@carrier.example.com")->sole();
    expect($contact->shop_id)->toBe($this->shop->id)
        ->and($contact->organisation_id)->toBe($this->organisation->id)
        ->and($contact->name)->toBe('DPD')
        ->and($contact->created_by_user_id)->toBe($this->staff->id);

    postJson($storeRoute, ['name' => 'DPD again', 'email' => "dpd-$token@carrier.example.com"])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    postJson($storeRoute, ['name' => 'DPD group', 'email' => "dpd-$token@carrier.example.com", 'all_shops' => true])
        ->assertSuccessful()->assertJsonPath('data.all_shops', true);
    postJson($storeRoute, ['name' => 'DPD group again', 'email' => "dpd-$token@carrier.example.com", 'all_shops' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    postJson(route('grp.org.chat.agents.email_contacts.store', [$this->organisation->slug, $this->otherShop->id]), ['name' => 'DPD', 'email' => "dpd-$token@carrier.example.com"])
        ->assertForbidden();

    $other = emailContactFor($this->organisation, $this->shop, 'GLS', "gls-$token@carrier.example.com");
    patchJson(route('grp.org.chat.agents.email_contacts.update', [$this->organisation->slug, $other->id]), ['email' => "dpd-$token@carrier.example.com"])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    patchJson(route('grp.org.chat.agents.email_contacts.update', [$this->organisation->slug, $other->id]), ['name' => 'GLS Parcels', 'email' => "GLS-$token@carrier.example.com"])
        ->assertSuccessful()->assertJsonPath('data.name', 'GLS Parcels');
});

test('only staff who work the shop\'s chat manage its contacts, and shopkeepers may only look them up', function () {
    $token   = $this->token;
    $contact = emailContactFor($this->organisation, $this->shop, "DPD $token", "dpd-$token@carrier.example.com");
    $shared  = emailContactFor($this->organisation, null, "Accounts $token", "accounts-$token@example.com");

    $searchRoute = route('grp.org.chat.agents.email_contacts.search', [$this->organisation->slug, $this->shop->id, 'query' => $token]);
    $storeRoute  = route('grp.org.chat.agents.email_contacts.store', [$this->organisation->slug, $this->shop->id]);
    $updateRoute = fn (ChatEmailContact $contact) => route('grp.org.chat.agents.email_contacts.update', [$this->organisation->slug, $contact->id]);
    $deleteRoute = fn (ChatEmailContact $contact) => route('grp.org.chat.agents.email_contacts.delete', [$this->organisation->slug, $contact->id]);

    actingAsUserWithOnlyPermissions($this->staff, ['goods.view']);
    getJson($searchRoute)->assertForbidden();
    postJson($storeRoute, ['name' => 'GLS', 'email' => "gls-$token@carrier.example.com"])->assertForbidden();
    patchJson($updateRoute($contact), ['name' => 'Changed'])->assertForbidden();
    deleteJson($deleteRoute($contact))->assertForbidden();

    actingAsUserWithOnlyPermissions($this->staff, ["orders.{$this->shop->id}.edit"]);
    getJson($searchRoute)->assertOk();
    postJson($storeRoute, ['name' => 'GLS', 'email' => "gls-$token@carrier.example.com"])->assertForbidden();

    actingAsUserWithOnlyPermissions($this->staff, ["chat.{$this->otherShop->id}"]);
    getJson($searchRoute)->assertForbidden();
    patchJson($updateRoute($contact), ['name' => 'Changed'])->assertForbidden();
    deleteJson($deleteRoute($contact))->assertForbidden();
    patchJson($updateRoute($shared), ['note' => 'Invoices only'])->assertSuccessful();

    actingAsUserWithOnlyPermissions($this->staff, ["chat.{$this->shop->id}"]);
    patchJson($updateRoute($contact), ['name' => 'DPD Collections'])->assertSuccessful();
    deleteJson($deleteRoute($contact))->assertSuccessful();
    expect(ChatEmailContact::find($contact->id))->toBeNull()
        ->and($shared->refresh()->note)->toBe('Invoices only');

    $guest = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
    actingAs($guest);
    getJson($searchRoute)->assertForbidden();
});

test('a new email to a plain address can save it as a contact, separately from saving a prospect', function () {
    Bus::fake([\App\Actions\Chat\ChatSession\ProcessChatMessageSideEffects::class, TranslateChatMessage::class, \App\Actions\Comms\Mailbox\SendChatMessageByGmail::class]);
    actingAs($this->user);

    $settings          = $this->shop->settings ?? [];
    $settings['gmail'] = [
        'email'         => 'care@shop.test',
        'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'),
        'history_id'    => '1',
    ];
    $this->shop->update(['settings' => $settings]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/*'      => \Illuminate\Support\Facades\Http::response(['id' => 'sent', 'threadId' => 't']),
    ]);

    $address = "collections-{$this->token}@dpd.example.com";

    StartCustomerEmailChat::make()->action($this->shop, [
        'email'           => $address,
        'subject'         => 'Missed collection',
        'message'         => 'Please collect today',
        'save_as_contact' => true,
        'contact_name'    => 'DPD collections',
        'company_name'    => 'DPD',
    ]);

    $contact = ChatEmailContact::where('email', $address)->sole();
    expect($contact->shop_id)->toBe($this->shop->id)
        ->and($contact->name)->toBe('DPD collections')
        ->and($contact->company)->toBe('DPD')
        ->and($contact->created_by_user_id)->toBe($this->user->id)
        ->and(\App\Models\CRM\Prospect::where('shop_id', $this->shop->id)->where('email', $address)->exists())->toBeFalse();

    StartCustomerEmailChat::make()->action($this->shop, [
        'email'           => $address,
        'subject'         => 'Again',
        'message'         => 'Still waiting',
        'save_as_contact' => true,
    ]);
    expect(ChatEmailContact::where('email', $address)->count())->toBe(1);
});
