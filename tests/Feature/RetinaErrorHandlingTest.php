<?php

/*
 * Created: Tue, 22 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\TopUp;
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
    $this->customer->update(['status' => CustomerStatusEnum::APPROVED]);
    $this->webUser = createWebUser($this->customer);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Retina')]
    );

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('handle')
        ->with('localhost')
        ->andReturn($this->website);
});

test('an unknown order keeps a signed in customer inside retina', function () {
    actingAs($this->webUser, 'retina');
    app()->detectEnvironment(fn () => 'production');

    try {
        $response = $this->get('/app/orders/faq');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect($response->headers->get('location'))->toBeNull();

    $response->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/Error'));
});

function topUpFor(Customer $customer, string $reference): TopUp
{
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $paymentAccount = $customer->shop->paymentAccountShops()->where('type', PaymentAccountTypeEnum::ACCOUNT)->first()->paymentAccount;
    $payment        = StorePayment::make()->action(customer: $customer, paymentAccount: $paymentAccount, modelData: Payment::factory()->definition());

    return StoreTopUp::make()->action($payment, ['amount' => 10, 'reference' => $reference]);
}

test('a customer can download their own top up receipt', function () {
    $topUp = topUpFor($this->customer, 'RTU-OWN');

    actingAs($this->webUser, 'retina');

    $response = $this->get(route('retina.top_up.single_top_up_pdf.export', $topUp->slug))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('a customer cannot download another customer top up receipt', function () {
    $otherCustomer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());
    $topUp         = topUpFor($otherCustomer, 'RTU-OTHER');

    actingAs($this->webUser, 'retina');

    $this->get(route('retina.top_up.single_top_up_pdf.export', $topUp->slug))
        ->assertNotFound();
});
