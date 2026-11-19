<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Comms\WhatsappCampaign\GetWhatsappRecipientsQuery;
use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Actions\Comms\WhatsappSubscriber\SyncCustomerWhatsappSubscriber;
use App\Actions\CRM\Customer\AnonymiseCustomer;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Retina\CRM\WhatsappOptIn\GetRetinaWhatsappOptInData;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UpdateWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
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
    $this->shop->update(['settings' => array_replace_recursive($this->shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '1000000001']])]);
    $this->website      = createWebsite($this->shop);
    if ($this->website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($this->website);
    }
    $this->customer = createCustomer($this->shop);
    $this->customer->update(['status' => CustomerStatusEnum::APPROVED, 'phone' => '+44 1632 960001', 'marketing_whatsapp_phone' => null]);
    $this->customer->comms->update(['is_subscribed_to_whatsapp_newsletter' => false, 'is_subscribed_to_whatsapp_notifications' => false]);
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
        ->and($subscriber->consent['utm'])->toEqual(['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'whatsapp_optin']);
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

test('order updates can be chosen without the newsletter', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone'         => '+447700900123',
        'notifications' => true,
        'consent'       => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $comms = $this->customer->comms->fresh();

    expect($comms->is_subscribed_to_whatsapp_notifications)->toBeTrue()
        ->and($comms->whatsapp_notifications_subscribed_at)->not->toBeNull()
        ->and($comms->whatsapp_notifications_subscribed_origin)->toBe('retina_dashboard')
        ->and($comms->is_subscribed_to_whatsapp_newsletter)->toBeFalse()
        ->and($this->customer->fresh()->marketing_whatsapp_phone)->toBe('+447700900123')
        ->and(WhatsappSubscriber::where('parent_type', 'Customer')->where('parent_id', $this->customer->id)->exists())->toBeFalse();

    $data = GetRetinaWhatsappOptInData::run($this->customer->fresh(), $this->website);

    expect($data['is_subscribed'])->toBeTrue()
        ->and($data['is_subscribed_to_notifications'])->toBeTrue()
        ->and($data['is_subscribed_to_newsletter'])->toBeFalse();
});

test('unticking one box while editing turns off only that subscription', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone' => '+447700900123', 'notifications' => true, 'consent' => true,
    ])->assertSessionHasNoErrors();

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone' => '+447700900123', 'notifications' => true, 'consent' => false,
    ])->assertSessionHasNoErrors();

    $comms = $this->customer->comms->fresh();

    expect($comms->is_subscribed_to_whatsapp_notifications)->toBeTrue()
        ->and($comms->is_subscribed_to_whatsapp_newsletter)->toBeFalse()
        ->and($comms->whatsapp_newsletter_unsubscribed_at)->not->toBeNull()
        ->and(WhatsappSubscriber::where('parent_type', 'Customer')->where('parent_id', $this->customer->id)->exists())->toBeFalse();
});

test('staff see the WhatsApp subscriptions of a customer, the Retina email management list does not', function () {
    $staffSubscriptions = CustomerResource::make($this->customer->fresh())->getArray()['email_subscriptions']['subscriptions'];

    expect($staffSubscriptions['whatsapp_notifications']['field'])->toBe('is_subscribed_to_whatsapp_notifications')
        ->and($staffSubscriptions['whatsapp_notifications']['is_subscribed'])->toBeFalse()
        ->and($staffSubscriptions['whatsapp_newsletter']['field'])->toBe('is_subscribed_to_whatsapp_newsletter');

    actingAs($this->webUser, 'retina');

    $this->get(route('retina.sysadmin.email.edit'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('EditModel')
            ->has('formData.blueprint.0.fields.email_subscriptions.customer.email_subscriptions.subscriptions.newsletters')
            ->missing('formData.blueprint.0.fields.email_subscriptions.customer.email_subscriptions.subscriptions.whatsapp_notifications')
            ->missing('formData.blueprint.0.fields.email_subscriptions.customer.email_subscriptions.subscriptions.whatsapp_newsletter'));
});

test('the checkout offers WhatsApp order and membership updates once the shop has a WhatsApp number, before any outbox is active', function () {
    $originalSettings = $this->shop->settings;
    $this->customer->update(['marketing_whatsapp_phone' => '+447700900123']);
    $sender = SendCustomerNotificationWhatsapp::make();

    $this->shop->update(['settings' => array_merge((array) $originalSettings, ['whatsapp' => ['phone_number_id' => '']])]);
    expect($sender->canOfferOptIn($this->customer->fresh()))->toBeFalse();

    $this->shop->update(['settings' => array_merge((array) $originalSettings, ['whatsapp' => ['phone_number_id' => '123456789']])]);
    expect($sender->canOfferOptIn($this->customer->fresh()))->toBeTrue();

    $this->shop->update(['settings' => $originalSettings]);
});

test('opting in with neither box ticked is refused', function () {
    actingAs($this->webUser, 'retina');

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), [
        'phone' => '+447700900123', 'notifications' => false, 'consent' => false,
    ])->assertSessionHasErrors('consent');
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

test('the card links the privacy policy set on the website, or the default one', function () {
    $original = $this->website->settings;

    UpdateWebsite::make()->action($this->website, ['privacy_policy_url' => null]);
    expect(GetRetinaWhatsappOptInData::run($this->customer, $this->website->refresh())['privacy_policy_url'])
        ->toBe(Website::DEFAULT_PRIVACY_POLICY_URL)
        ->toBe('https://aw-advantage.com/whatsapp-messaging-terms-of-service-privacy-policy-data-deletion');

    UpdateWebsite::make()->action($this->website, ['privacy_policy_url' => 'https://www.example.com/privacy']);
    expect(GetRetinaWhatsappOptInData::run($this->customer, $this->website->refresh())['privacy_policy_url'])
        ->toBe('https://www.example.com/privacy');

    UpdateWebsite::make()->action($this->website, ['privacy_policy_url' => '']);
    expect($this->website->refresh()->getPrivacyPolicyUrl())->toBe(Website::DEFAULT_PRIVACY_POLICY_URL);

    $this->website->update(['settings' => $original]);
});

test('saving the privacy policy link keeps the other website settings', function () {
    $original = $this->website->settings;

    UpdateWebsite::make()->action($this->website, ['welcome_message' => '<p>Welcome back</p>', 'return_policy' => 'Thirty days']);
    UpdateWebsite::make()->action($this->website->refresh(), ['privacy_policy_url' => 'https://www.example.com/privacy']);

    $settings = $this->website->refresh()->settings;

    expect($settings['privacy_policy_url'])->toBe('https://www.example.com/privacy')
        ->and($settings['welcome_message'])->toBe('<p>Welcome back</p>')
        ->and($settings['return_policy'])->toBe('Thirty days')
        ->and(collect($original ?? [])->except(['privacy_policy_url', 'welcome_message', 'return_policy'])->all())
        ->toBe(collect($settings)->except(['privacy_policy_url', 'welcome_message', 'return_policy'])->all());

    $this->website->update(['settings' => $original]);
});

test('the privacy policy link must be a web address', function () {
    expect(fn () => UpdateWebsite::make()->action($this->website, ['privacy_policy_url' => 'javascript:alert(1)']))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($this->website->refresh()->getPrivacyPolicyUrl())->not->toContain('javascript');
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

test('the consent wording shown on the dashboard is the wording stored with the subscription', function () {
    actingAs($this->webUser, 'retina');

    $shownConsentText = GetRetinaWhatsappOptInData::run($this->customer, $this->website)['consent_text'];

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), ['phone' => '+447700900123', 'consent' => true])
        ->assertSessionHasNoErrors();

    $subscriber = WhatsappSubscriber::where('parent_type', 'Customer')->where('parent_id', $this->customer->id)->first();

    expect($shownConsentText)->toContain($this->shop->name)
        ->toContain('occasional offers')
        ->and($subscriber->consent['text'])->toBe($shownConsentText);
});

test('Not interested hides the dashboard WhatsApp card for good, while Email management still offers the opt-in', function () {
    $this->customer->comms->update(['whatsapp_newsletter_opt_in_dismissed_at' => null]);
    actingAs($this->webUser, 'retina');

    expect(GetRetinaWhatsappOptInData::run($this->customer->fresh(), $this->website, dismissible: true)['dismiss_route']['name'])->toBe('retina.models.whatsapp_newsletter.dismiss')
        ->and(GetRetinaWhatsappOptInData::run($this->customer->fresh(), $this->website)['dismiss_route'])->toBeNull();

    $this->post(route('retina.models.whatsapp_newsletter.dismiss'))->assertRedirect();

    expect($this->customer->comms->fresh()->whatsapp_newsletter_opt_in_dismissed_at)->not->toBeNull()
        ->and(GetRetinaWhatsappOptInData::run($this->customer->fresh(), $this->website, dismissible: true))->toBeNull();

    $this->get('/app/dashboard?utm_campaign=whatsapp_optin&utm_source=newsletter')
        ->assertRedirect(route('retina.sysadmin.email.edit', ['utm_campaign' => 'whatsapp_optin', 'utm_source' => 'newsletter']));
    $this->get('/app/dashboard')->assertOk();

    $this->get(route('retina.sysadmin.email.edit'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('EditModel')
            ->where('formData.blueprint.1.fields.whatsapp_optin.optin.is_subscribed', false)
            ->where('formData.blueprint.1.fields.whatsapp_optin.optin.dismiss_route', null));

    $this->post(route('retina.models.whatsapp_newsletter.subscribe'), ['phone' => '+44 7700 900123', 'consent' => true])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->customer->comms->fresh()->whatsapp_newsletter_opt_in_dismissed_at)->toBeNull()
        ->and(GetRetinaWhatsappOptInData::run($this->customer->fresh(), $this->website, dismissible: true)['is_subscribed'])->toBeTrue();
});

test('the WhatsApp opt-in is offered only by wholesale and dropshipping shops with a connected WhatsApp number', function () {
    $connected = ['whatsapp' => ['phone_number_id' => '1000000003']];
    $shopOf    = fn (ShopTypeEnum $type, array $settings = []) => (new App\Models\Catalogue\Shop())->forceFill(['type' => $type, 'settings' => $settings]);

    expect(GetRetinaWhatsappOptInData::isOfferedIn($shopOf(ShopTypeEnum::B2B, $connected)))->toBeTrue()
        ->and(GetRetinaWhatsappOptInData::isOfferedIn($shopOf(ShopTypeEnum::DROPSHIPPING, $connected)))->toBeTrue()
        ->and(GetRetinaWhatsappOptInData::isOfferedIn($shopOf(ShopTypeEnum::DROPSHIPPING)))->toBeFalse()
        ->and(GetRetinaWhatsappOptInData::isOfferedIn($shopOf(ShopTypeEnum::B2B)))->toBeFalse()
        ->and(GetRetinaWhatsappOptInData::isOfferedIn($shopOf(ShopTypeEnum::FULFILMENT, $connected)))->toBeFalse()
        ->and(GetRetinaWhatsappOptInData::isOfferedIn(null))->toBeFalse()
        ->and(App\Enums\Comms\Mailshot\MailshotReadyMadeLinkEnum::WHATSAPP_OPT_IN->isAvailableIn($shopOf(ShopTypeEnum::DROPSHIPPING, $connected)))->toBeTrue()
        ->and(App\Enums\Comms\Mailshot\MailshotReadyMadeLinkEnum::WHATSAPP_OPT_IN->isAvailableIn($shopOf(ShopTypeEnum::DROPSHIPPING)))->toBeFalse();
});

test('the dropshipping dashboard carries the WhatsApp card and a dismissed customer is sent to email management', function () {
    $shopData = App\Models\Catalogue\Shop::factory()->definition();
    data_set($shopData, 'type', ShopTypeEnum::DROPSHIPPING);
    $shop = App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, $shopData);
    $shop->update(['settings' => array_replace_recursive($shop->settings ?? [], ['whatsapp' => ['phone_number_id' => '1000000002']])]);

    $website = createWebsite($shop);
    if ($website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($website);
    }

    $customer = StoreCustomer::make()->action($shop, Customer::factory()->definition());
    $customer->update(['status' => CustomerStatusEnum::APPROVED]);
    $webUser = createWebUser($customer);

    DetectWebsiteFromDomain::clearFake();
    DetectWebsiteFromDomain::mock()
        ->shouldReceive('handle')
        ->with('localhost')
        ->andReturn($website);

    actingAs($webUser, 'retina');

    $this->get(route('retina.dashboard.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/RetinaDropshippingDashboard')
            ->where('whatsapp_optin.is_subscribed', false)
            ->where('whatsapp_optin.anchor_id', 'whatsapp-updates')
            ->where('whatsapp_optin.dismiss_route.name', 'retina.models.whatsapp_newsletter.dismiss'));

    $webUser->customer->comms->update(['whatsapp_newsletter_opt_in_dismissed_at' => now(), 'is_subscribed_to_whatsapp_newsletter' => false]);

    $this->get('/app/dashboard?utm_campaign=whatsapp_optin&utm_source=newsletter')
        ->assertRedirect(route('retina.sysadmin.email.edit', ['utm_campaign' => 'whatsapp_optin', 'utm_source' => 'newsletter']));
});

test('the WhatsApp subscription and the sign-up checkbox follow the shop having a WhatsApp number', function () {
    $subscriptionsOf = fn () => App\Http\Resources\CRM\CustomerResource::make($this->customer->fresh())->toArray(request())['email_subscriptions']['subscriptions'];

    expect($subscriptionsOf())->toHaveKey('whatsapp_newsletter')
        ->and(GetRetinaWhatsappOptInData::isOfferedAtRegistration($this->shop))->toBeTrue();

    $this->shop->update(['settings' => array_replace_recursive($this->shop->settings, ['whatsapp' => ['phone_number_id' => null]])]);
    $this->customer->shop->refresh();

    expect($subscriptionsOf())->not->toHaveKey('whatsapp_newsletter')
        ->and(GetRetinaWhatsappOptInData::isOfferedAtRegistration($this->shop->refresh()))->toBeFalse()
        ->and(GetRetinaWhatsappOptInData::isOfferedAtRegistration((new App\Models\Catalogue\Shop())->forceFill(['type' => ShopTypeEnum::FULFILMENT, 'settings' => []])))->toBeTrue();
});
