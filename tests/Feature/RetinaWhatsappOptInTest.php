<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Comms\WhatsappCampaign\GetWhatsappRecipientsQuery;
use App\Actions\Comms\WhatsappSubscriber\SyncCustomerWhatsappSubscriber;
use App\Actions\CRM\Customer\AnonymiseCustomer;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Retina\CRM\WhatsappOptIn\GetRetinaWhatsappOptInData;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->shop         = createShop()[2];
    $this->website      = createWebsite($this->shop);
    if ($this->website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($this->website);
    }
    $this->customer = createCustomer($this->shop);
    $this->customer->update(['status' => CustomerStatusEnum::APPROVED, 'phone' => '+44 1632 960001', 'marketing_whatsapp_phone' => null]);
    $this->customer->comms->update(['is_subscribed_to_whatsapp_newsletter' => false]);
    WhatsappSubscriber::withTrashed()->where('parent_type', 'Customer')->where('parent_id', $this->customer->id)->forceDelete();
    $this->webUser = createWebUser($this->customer);

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Retina')]);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('handle')
        ->with('localhost')
        ->andReturn($this->website);
});

test('a logged out campaign link returns to the dashboard with its attribution after login', function () {
    $this->get('/app/dashboard?utm_source=newsletter&utm_medium=email&utm_campaign=whatsapp_optin')
        ->assertRedirect(route('retina.login.show', [
            'ref' => '/app/dashboard?utm_campaign=whatsapp_optin&utm_medium=email&utm_source=newsletter',
        ]));
});

test('a plain logged out dashboard visit keeps the usual login redirect', function () {
    $this->get('/app/dashboard')->assertRedirect(route('retina.login.show'));
});

test('a logged out visit to another page is not carried through login', function () {
    $this->get('/app/orders?utm_campaign=x')->assertRedirect(route('retina.login.show'));
});

test('a customer subscribes with a chosen WhatsApp number and consent', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone'        => '+44 7700 900123',
        'consent'      => true,
        'utm_source'   => 'newsletter',
        'utm_medium'   => 'email',
        'utm_campaign' => 'whatsapp_optin',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->customer->refresh();
    $subscriber = WhatsappSubscriber::where('parent_type', 'Customer')->where('parent_id', $this->customer->id)->first();

    expect($this->customer->comms->is_subscribed_to_whatsapp_newsletter)->toBeTrue()
        ->and($this->customer->phone)->toBe('+44 1632 960001')
        ->and($this->customer->marketing_whatsapp_phone)->toBe('+447700900123')
        ->and($subscriber->consented_at)->not->toBeNull()
        ->and($subscriber->consent['phone'])->toBe('+447700900123')
        ->and($subscriber->consent['source'])->toBe('retina_dashboard')
        ->and($subscriber->consent['text'])->toContain('WhatsApp')
        ->and($subscriber->consent['utm'])->toBe(['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'whatsapp_optin']);
});

test('a formatted number from another country is accepted', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone'   => '0062 (812) 3456-7890',
        'consent' => true,
    ])->assertSessionHasNoErrors();

    expect($this->customer->fresh()->marketing_whatsapp_phone)->toBe('+6281234567890');
});

test('opting in needs the consent tick', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone' => '+447700900123',
    ])->assertSessionHasErrors('consent');

    expect($this->customer->comms->fresh()->is_subscribed_to_whatsapp_newsletter)->toBeFalse();
});

test('a number without its country code is rejected', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone'   => '07700 900123',
        'consent' => true,
    ])->assertSessionHasErrors('phone');

    expect($this->customer->comms->fresh()->is_subscribed_to_whatsapp_newsletter)->toBeFalse();
});

test('the card knows the subscribed number so only the tick is needed next time', function () {
    $data = GetRetinaWhatsappOptInData::run($this->customer, $this->website);

    expect($data['is_subscribed'])->toBeFalse()
        ->and($data['whatsapp_phone'])->toBeNull()
        ->and($data['suggested_phone'])->toBe('+441632960001');

    actingAs($this->webUser, 'retina');
    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), ['phone' => '+447700900123', 'consent' => true]);

    $data = GetRetinaWhatsappOptInData::run($this->customer->fresh(), $this->website);

    expect($data['is_subscribed'])->toBeTrue()
        ->and($data['whatsapp_phone'])->toBe('+447700900123')
        ->and($data['suggested_phone'])->toBeNull();
});

test('a national account phone is not suggested', function () {
    expect(GetRetinaWhatsappOptInData::make()->getSuggestedPhone('07700 900123'))->toBeNull()
        ->and(GetRetinaWhatsappOptInData::make()->getSuggestedPhone('0044 7700 900123'))->toBe('+447700900123');
});

test('campaigns reach the chosen WhatsApp number, not the account phone', function () {
    actingAs($this->webUser, 'retina');
    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), ['phone' => '+447700900123', 'consent' => true]);

    $keys = GetWhatsappRecipientsQuery::run($this->shop, ['subscriber' => true], [])
        ->where('customer_id', $this->customer->id)
        ->pluck('recipient_key')
        ->all();

    expect($keys)->toBe(['447700900123']);
});

test('anonymising a customer clears the WhatsApp marketing phone and consent', function () {
    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $customer->update(['marketing_whatsapp_phone' => '+447700900124']);
    $customer->comms->update(['is_subscribed_to_whatsapp_newsletter' => true]);
    SyncCustomerWhatsappSubscriber::run($customer);
    WhatsappSubscriber::where('parent_type', 'Customer')->where('parent_id', $customer->id)
        ->update(['consented_at' => now(), 'consent' => json_encode(['phone' => '+447700900124'])]);

    AnonymiseCustomer::make()->action($customer, 'Customer asked to be forgotten');

    $subscriber = WhatsappSubscriber::withTrashed()->where('parent_type', 'Customer')->where('parent_id', $customer->id)->first();

    expect($customer->fresh()->marketing_whatsapp_phone)->toBeNull()
        ->and($subscriber->consent)->toBeNull()
        ->and($subscriber->consented_at)->toBeNull()
        ->and($subscriber->trashed())->toBeTrue();
});

test('the B2B dashboard carries the WhatsApp card data', function () {
    if ($this->shop->type !== ShopTypeEnum::B2B) {
        $this->markTestSkipped('The test shop is not B2B');
    }

    actingAs($this->webUser, 'retina');

    $this->get(route('retina.dashboard.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/RetinaB2BDashboard')
            ->where('whatsapp_optin.is_subscribed', false)
            ->where('whatsapp_optin.subscribe_route.name', 'retina.models.whatsapp_newsletter.subscribe'));
});
