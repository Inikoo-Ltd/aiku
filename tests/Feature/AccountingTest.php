<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Mar 2024 21:34:44 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\CreditTransaction\DeleteCreditTransaction;
use App\Actions\Accounting\CreditTransaction\UpdateCreditTransaction;
use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Accounting\Invoice\ISDocInvoice;
use App\Actions\Accounting\Invoice\OmegaInvoice;
use App\Actions\Accounting\Invoice\OmegaManyInvoice;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Accounting\Invoice\UI\ForceDeleteRefund;
use App\Actions\Accounting\InvoiceCategory\HydrateInvoiceCategories;
use App\Actions\Accounting\InvoiceCategory\StoreInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\UpdateInvoiceCategory;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount;
use App\Actions\Accounting\OrgPaymentServiceProvider\UpdateOrgPaymentServiceProvider;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccount\UpdatePaymentAccount;
use App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop;
use App\Actions\Accounting\PaymentAccountShop\UpdatePaymentAccountShop;
use App\Actions\Accounting\PaymentServiceProvider\DeletePaymentServiceProvider;
use App\Actions\Accounting\PaymentServiceProvider\UpdatePaymentServiceProvider;
use App\Actions\Accounting\TopUp\SetTopUpStatusToSuccess;
use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\Accounting\TopUp\UpdateTopUp;
use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Accounting\TopUp;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('base');

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->adminGuest   = createAdminGuest($this->organisation->group);


    $tradeUnits       = createTradeUnits($this->group);
    $this->tradeUnit1 = $tradeUnits[0];
    $this->tradeUnit2 = $tradeUnits[1];

    $this->adminGuest->refresh();

    $shop = Shop::where('type', '!=', ShopTypeEnum::FULFILMENT)->first();
    if (!$shop) {
        $shop = StoreShop::run(
            $this->organisation,
            Shop::factory()->definition()
        );
    }
    $this->shop = $shop;

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});

test('payment service providers seeder works', function () {
    expect(PaymentServiceProvider::count())->toBe(11)->
    and(
        $this->group->accountingStats->number_payment_service_providers
    )->toBe(11);
});

test('add payment service provider to organisation', function () {
    expect($this->organisation->accountingStats->number_org_payment_service_providers)->toBe(1)
        ->and($this->organisation->accountingStats->number_org_payment_service_providers_type_account)->toBe(1)
        ->and($this->group->accountingStats->number_payment_service_providers)->toBe(11);

    $modelData = PaymentServiceProvider::factory()->definition();
    data_set($modelData, 'type', PaymentServiceProviderTypeEnum::CASH->value);


    $paymentServiceProvider    = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first();
    $orgPaymentServiceProvider = StoreOrgPaymentServiceProvider::make()->action(
        paymentServiceProvider: $paymentServiceProvider,
        organisation: $this->organisation,
        modelData: $modelData
    );
    $this->organisation->refresh();
    expect($orgPaymentServiceProvider)->toBeInstanceOf(OrgPaymentServiceProvider::class)
        ->and($this->organisation->accountingStats->number_org_payment_service_providers)->toBe(2)
        ->and($this->organisation->accountingStats->number_org_payment_service_providers_type_cash)->toBe(1);

    return $orgPaymentServiceProvider;
});


test('update payment service provider name', function () {
    $paymentServiceProvider = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first();

    $paymentServiceProvider = UpdatePaymentServiceProvider::make()->action(
        $paymentServiceProvider,
        ['name' => 'new name']
    );
    expect($paymentServiceProvider->name)->toBe('new name');
});


test('create other org payment service provider', function () {
    $modelData = PaymentServiceProvider::factory()->definition();
    data_set($modelData, 'type', PaymentServiceProviderTypeEnum::BANK->value);
    data_set($modelData, 'code', 'test123');


    $paymentServiceProvider    = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first();
    $orgPaymentServiceProvider = StoreOrgPaymentServiceProvider::make()->action(
        paymentServiceProvider: $paymentServiceProvider,
        organisation: $this->organisation,
        modelData: $modelData
    );
    $this->organisation->refresh();
    expect($orgPaymentServiceProvider)->toBeInstanceOf(OrgPaymentServiceProvider::class)
        ->and($this->organisation->accountingStats->number_org_payment_service_providers)->toBe(3);

    return $orgPaymentServiceProvider;
});

test('update org payment service provider', function (OrgPaymentServiceProvider $orgPaymentServiceProvider) {
    $orgPaymentServiceProvider = UpdateOrgPaymentServiceProvider::make()->action(
        $orgPaymentServiceProvider,
        [
            'code' => 'new_code'
        ]
    );
    expect($orgPaymentServiceProvider)->toBeInstanceOf(OrgPaymentServiceProvider::class)
        ->and($orgPaymentServiceProvider->code)->toBe('new_code');

    return $orgPaymentServiceProvider;
})->depends('create other org payment service provider');

test('create other org payment service provider account', function () {
    /** @var Organisation $organisation */
    $organisation = $this->organisation;

    expect($organisation->accountingStats->number_payment_accounts)->toBe(1);

    $paymentServiceProvider = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first();
    $paymentAccount         = StoreOrgPaymentServiceProviderAccount::make()->action(
        $organisation,
        $paymentServiceProvider,
        [
            'code' => 'Acc2',
            'name' => 'Account 2'
        ]
    );

    $this->organisation->refresh();

    expect($paymentAccount)->toBeInstanceOf(PaymentAccount::class)
        ->and($organisation->accountingStats->number_payment_accounts)->toBe(2);

    return $paymentAccount;
});


test('create payment account', function ($orgPaymentServiceProvider) {
    $modelData = PaymentAccount::factory()->definition();
    data_set($modelData, 'type', PaymentAccountTypeEnum::BANK->value);

    $paymentAccount = StorePaymentAccount::make()->action(
        $orgPaymentServiceProvider,
        $modelData
    );
    $orgPaymentServiceProvider->refresh();
    expect($paymentAccount)->toBeInstanceOf(PaymentAccount::class)
        ->and($orgPaymentServiceProvider->stats->number_payment_accounts)->toBe(1)
        ->and($orgPaymentServiceProvider->stats->number_payment_accounts_type_bank)->toBe(1);

    return $paymentAccount;
})->depends('add payment service provider to organisation');

test('update payment account shop', function (PaymentAccount $paymentAccount) {
    $shop               = $this->shop;
    $paymentAccountShop = StorePaymentAccountShop::make()->action(
        $paymentAccount,
        $shop,
        [
            'currency_id' => $shop->currency_id,
            'state'       => PaymentAccountShopStateEnum::ACTIVE
        ]
    );

    $paymentAccountShop->refresh();
    expect($paymentAccountShop)->toBeInstanceOf(PaymentAccountShop::class);

    $paymentAccountShop = UpdatePaymentAccountShop::make()->action(
        $paymentAccountShop,
        [
            'state'            => PaymentAccountShopStateEnum::INACTIVE,
            'show_in_checkout' => true,
        ]
    );

    expect($paymentAccountShop)->toBeInstanceOf(PaymentAccountShop::class)
        ->and($paymentAccountShop->show_in_checkout)->toBeTrue();

    return $paymentAccount;
})->depends('create payment account');

test('update payment account', function ($paymentAccount) {
    $paymentAccount = UpdatePaymentAccount::make()->action(
        $paymentAccount,
        ['name' => 'Pika Ltd']
    );
    expect($paymentAccount->name)->toBe('Pika Ltd');
})->depends('create payment account');


test(
    'create payment',
    function ($paymentAccount) {
        GetCurrencyExchange::shouldRun()
            ->andReturn(2);

        $shop     = $this->shop;
        $customer = StoreCustomer::make()->action(
            $shop,
            Customer::factory()->definition()
        );

        $modelData = Payment::factory()->definition();
        $payment   = StorePayment::make()->action(
            customer: $customer,
            paymentAccount: $paymentAccount,
            modelData: $modelData
        );

        $this->organisation->refresh();

        expect($payment)->toBeInstanceOf(Payment::class)
            ->and($payment->shop_id)->toBe($shop->id)
            ->and($payment->currency_id)->toBe($shop->currency_id)
            ->and($payment->group_id)->toBe($this->group->id)
            ->and($payment->organisation_id)->toBe($this->organisation->id)
            ->and($this->group->accountingStats->number_payments)->toBe(1)
            ->and($this->group->accountingStats->number_payments_type_payment)->toBe(1)
            ->and($this->group->accountingStats->number_payments_state_in_process)->toBe(1)
            ->and($this->organisation->accountingStats->number_payments)->toBe(1)
            ->and($this->organisation->accountingStats->number_payments_type_payment)->toBe(1)
            ->and($this->organisation->accountingStats->number_payments_state_in_process)->toBe(1);

        return $payment;
    }
)->depends('create payment account');

test('UI create payment', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.accounting.payments.create',
            [$this->organisation->slug]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 1)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New payment')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI show payment', function (Payment $payment) {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.accounting.payments.show',
            [$this->organisation->slug, $payment->id]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($payment) {
        $page
            ->component('Org/Accounting/Payment')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $payment->reference)
                    ->etc()
            )
            ->has('tabs');
    });
})->depends('create payment');

test('UI edit payment', function (Payment $payment) {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.accounting.payments.edit',
            [$this->organisation->slug, $payment->id]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($payment) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $payment->reference)
                    ->etc()
            )
            ->has('formData');
    });
})->depends('create payment');

test(
    'update payment',
    function (Payment $payment) {
        $modelData      = [
            'reference' => 'TST1010'
        ];
        $updatedPayment = UpdatePayment::make()->action($payment, $modelData);

        expect($updatedPayment)->toBeInstanceOf(Payment::class)
            ->and($updatedPayment->reference)->toBe('TST1010');

        return $updatedPayment;
    }
)->depends('create payment');

test('create and set success 1st top up', function ($payment) {
    $topUp = StoreTopUp::make()->action($payment, [
        'amount'    => 100,
        'reference' => 'ASA01'
    ]);

    $topUp->refresh();

    expect($topUp)->toBeInstanceOf(TopUp::class)
        ->and($topUp->amount)->toBe('100.00');

    SetTopUpStatusToSuccess::make()->action($topUp);

    $topUp->refresh();

    expect($topUp->creditTransaction->amount)->toBe('100.00')
        ->and($topUp->creditTransaction->running_amount)->toBe('100.00')
        ->and($topUp->creditTransaction->type)->toBe(CreditTransactionTypeEnum::TOP_UP);

    return $topUp;
})->depends('create payment');

test('check customer balance and stats', function ($topUp) {
    $customer = $topUp->customer;

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->balance)->toBe('100.00')
        ->and($customer->stats->number_top_ups)->toBe(1)
        ->and($customer->stats->number_top_ups_status_in_process)->toBe(0)
        ->and($customer->stats->number_top_ups_status_success)->toBe(1)
        ->and($customer->stats->number_top_ups_status_fail)->toBe(0)
        ->and($customer->stats->number_credit_transactions)->toBe(1);
})->depends('create and set success 1st top up');

test('check shop stats', function (TopUp $topUp) {
    $shop = $topUp->shop;

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->accountingStats->number_top_ups)->toBe(1)
        ->and($shop->accountingStats->number_top_ups_status_in_process)->toBe(0)
        ->and($shop->accountingStats->number_top_ups_status_success)->toBe(1)
        ->and($shop->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($shop->accountingStats->number_credit_transactions)->toBe(1);
})->depends('create and set success 1st top up');

test('check organisation stats', function ($topUp) {
    $organisation = $topUp->organisation;

    expect($organisation)->toBeInstanceOf(Organisation::class)
        ->and($organisation->accountingStats->number_top_ups)->toBe(1)
        ->and($organisation->accountingStats->number_top_ups_status_in_process)->toBe(0)
        ->and($organisation->accountingStats->number_top_ups_status_success)->toBe(1)
        ->and($organisation->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($organisation->accountingStats->number_credit_transactions)->toBe(1);
})->depends('create and set success 1st top up');

test('check Group stats', function ($topUp) {
    $group = $topUp->group;

    expect($group)->toBeInstanceOf(Group::class)
        ->and($group->accountingStats->number_top_ups)->toBe(1)
        ->and($group->accountingStats->number_top_ups_status_in_process)->toBe(0)
        ->and($group->accountingStats->number_top_ups_status_success)->toBe(1)
        ->and($group->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($group->accountingStats->number_credit_transactions)->toBe(1);
})->depends('create and set success 1st top up');

test('create and set success 2nd top up', function ($payment) {
    $topUp = StoreTopUp::make()->action($payment, [
        'amount'    => 150,
        'reference' => 'ASA02'
    ]);

    $topUp->refresh();

    expect($topUp)->toBeInstanceOf(TopUp::class)
        ->and($topUp->amount)->toBe('150.00');

    SetTopUpStatusToSuccess::make()->action($topUp);

    $topUp->refresh();

    expect($topUp->creditTransaction->amount)->toBe('150.00')
        ->and($topUp->creditTransaction->running_amount)->toBe('250.00')
        ->and($topUp->creditTransaction->type)->toBe(CreditTransactionTypeEnum::TOP_UP);

    return $topUp;
})->depends('create payment');

test('check customer balance and stats 2nd time', function ($topUp) {
    $customer = $topUp->customer;

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->balance)->toBe('250.00')
        ->and($customer->stats->number_top_ups)->toBe(2)
        ->and($customer->stats->number_top_ups_status_in_process)->toBe(0)
        ->and($customer->stats->number_top_ups_status_success)->toBe(2)
        ->and($customer->stats->number_top_ups_status_fail)->toBe(0)
        ->and($customer->stats->number_credit_transactions)->toBe(2);
})->depends('create and set success 2nd top up');

test('check shop stats 2nd time', function (TopUp $topUp) {
    $shop = $topUp->shop;

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->accountingStats->number_top_ups)->toBe(2)
        ->and($shop->accountingStats->number_top_ups_status_in_process)->toBe(0)
        ->and($shop->accountingStats->number_top_ups_status_success)->toBe(2)
        ->and($shop->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($shop->accountingStats->number_credit_transactions)->toBe(2);
})->depends('create and set success 2nd top up');

test('check organisation stats 2nd time', function ($topUp) {
    $organisation = $topUp->organisation;

    expect($organisation)->toBeInstanceOf(Organisation::class)
        ->and($organisation->accountingStats->number_top_ups)->toBe(2)
        ->and($organisation->accountingStats->number_top_ups_status_in_process)->toBe(0)
        ->and($organisation->accountingStats->number_top_ups_status_success)->toBe(2)
        ->and($organisation->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($organisation->accountingStats->number_credit_transactions)->toBe(2);
})->depends('create and set success 2nd top up');

test('check Group stats 2nd time', function ($topUp) {
    $group = $topUp->group;

    expect($group)->toBeInstanceOf(Group::class)
        ->and($group->accountingStats->number_top_ups)->toBe(2)
        ->and($group->accountingStats->number_top_ups_status_in_process)->toBe(0)
        ->and($group->accountingStats->number_top_ups_status_success)->toBe(2)
        ->and($group->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($group->accountingStats->number_credit_transactions)->toBe(2);
})->depends('create and set success 2nd top up');

test('create 3rd top up', function ($payment) {
    $topUp = StoreTopUp::make()->action($payment, [
        'amount'    => 200,
        'reference' => 'ASA03'
    ]);

    $topUp->refresh();

    expect($topUp)->toBeInstanceOf(TopUp::class)
        ->and($topUp->amount)->toBe('200.00');

    return $topUp;
})->depends('create payment');

test('check customer balance 3rd time', function ($topUp) {
    $customer = $topUp->customer;

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->balance)->toBe('250.00')
        ->and($customer->stats->number_top_ups)->toBe(3)
        ->and($customer->stats->number_top_ups_status_in_process)->toBe(1)
        ->and($customer->stats->number_top_ups_status_success)->toBe(2)
        ->and($customer->stats->number_top_ups_status_fail)->toBe(0)
        ->and($customer->stats->number_credit_transactions)->toBe(2);
})->depends('create 3rd top up');

test('check shop stats 3rd time', function (TopUp $topUp) {
    $shop = $topUp->shop;

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->accountingStats->number_top_ups)->toBe(3)
        ->and($shop->accountingStats->number_top_ups_status_in_process)->toBe(1)
        ->and($shop->accountingStats->number_top_ups_status_success)->toBe(2)
        ->and($shop->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($shop->accountingStats->number_credit_transactions)->toBe(2);
})->depends('create 3rd top up');

test('check organisation stats 3rd time', function ($topUp) {
    $organisation = $topUp->organisation;

    expect($organisation)->toBeInstanceOf(Organisation::class)
        ->and($organisation->accountingStats->number_top_ups)->toBe(3)
        ->and($organisation->accountingStats->number_top_ups_status_in_process)->toBe(1)
        ->and($organisation->accountingStats->number_top_ups_status_success)->toBe(2)
        ->and($organisation->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($organisation->accountingStats->number_credit_transactions)->toBe(2);
})->depends('create 3rd top up');

test('check Group stats 3rd time', function ($topUp) {
    $group = $topUp->group;

    expect($group)->toBeInstanceOf(Group::class)
        ->and($group->accountingStats->number_top_ups)->toBe(3)
        ->and($group->accountingStats->number_top_ups_status_in_process)->toBe(1)
        ->and($group->accountingStats->number_top_ups_status_success)->toBe(2)
        ->and($group->accountingStats->number_top_ups_status_fail)->toBe(0)
        ->and($group->accountingStats->number_credit_transactions)->toBe(2);

    return $topUp;
})->depends('create 3rd top up');

test('update top up', function (TopUp $topUp) {
    $modelData    = [
        'amount' => 100000
    ];
    $updatedTopUp = UpdateTopUp::make()->action($topUp, $modelData);

    expect($updatedTopUp)->toBeInstanceOf(TopUp::class)
        ->and($updatedTopUp->amount)->toBe('100000.00');

    return $updatedTopUp;
})->depends('check Group stats 3rd time');

test('update credit transaction', function (TopUp $topUp) {
    $creditTransaction        = $topUp->customer->creditTransactions->first();
    $modelData                = [
        'amount' => 120000
    ];
    $updatedCreditTransaction = UpdateCreditTransaction::make()->action($creditTransaction, $modelData);

    expect($updatedCreditTransaction)->toBeInstanceOf(CreditTransaction::class)
        ->and($updatedCreditTransaction->amount)->toBe('120000.00');

    return $updatedCreditTransaction;
})->depends('check Group stats 3rd time');

test('delete credit transaction', function (CreditTransaction $creditTransaction) {
    $deletedCreditTransaction = DeleteCreditTransaction::make()->action($creditTransaction);

    expect(CreditTransaction::find($deletedCreditTransaction->id))->toBeNull();

    return $deletedCreditTransaction;
})->depends('update credit transaction');

test('UI index invoice categories', function () {
    $response = get(route('grp.org.accounting.invoice-categories.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/InvoiceCategories')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoice Categories')
                    ->etc()
            )
            ->has('data');
    });
});


test('store invoice category', function () {
    $invoiceCategory = StoreInvoiceCategory::make()->action($this->organisation, [
        'name'        => 'Test Inv Cate',
        'state'       => InvoiceCategoryStateEnum::ACTIVE,
        'type'        => InvoiceCategoryTypeEnum::SHOP_FALLBACK,
        'currency_id' => $this->organisation->currency_id,
        'priority'    => 1
    ]);

    $invoiceCategory->refresh();

    expect($invoiceCategory)->toBeInstanceOf(InvoiceCategory::class)
        ->and($invoiceCategory->name)->toBe('Test Inv Cate');

    return $invoiceCategory;
});

test('UI show invoice category', function (InvoiceCategory $invoiceCategory) {
    $response = get(route('grp.org.accounting.invoice-categories.show', [$this->organisation->slug, $invoiceCategory->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($invoiceCategory) {
        $page
            ->component('Org/Accounting/InvoiceCategory')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $invoiceCategory->name)
                    ->etc()
            );
    });
})->depends('store invoice category');

test('UI show invoice in invoice category', function (InvoiceCategory $invoiceCategory) {
    $response = get(route('grp.org.accounting.invoice-categories.show.invoices.index', [$this->organisation->slug, $invoiceCategory->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($invoiceCategory) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $invoiceCategory->name)
                    ->etc()
            );
    });
})->depends('store invoice category');


test('UI Edit invoice categories', function (InvoiceCategory $invoiceCategory) {
    $response = get(route('grp.org.accounting.invoice-categories.edit', [$this->organisation->slug, $invoiceCategory->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData')
            ->has('pageHead')
            ->has('breadcrumbs', 3);
    });
})->depends('store invoice category');

test('update invoice category', function (InvoiceCategory $invoiceCategory) {
    $invoiceCategory = UpdateInvoiceCategory::make()->action($invoiceCategory, [
        'name'  => 'Test Up Inv Cate',
        'state' => InvoiceCategoryStateEnum::CLOSED
    ]);

    $invoiceCategory->refresh();

    expect($invoiceCategory)->toBeInstanceOf(InvoiceCategory::class)
        ->and($invoiceCategory->name)->toBe('Test Up Inv Cate')
        ->and($invoiceCategory->state)->toBe(InvoiceCategoryStateEnum::CLOSED);

    return $invoiceCategory;
})->depends('store invoice category');

test('UI show accounting dashboard', function () {
    $response = get(route('grp.org.accounting.dashboard', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/AccountingDashboard')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Accounting')
                    ->etc()
            )
            ->has('flatTreeMaps');
    });
});

test('UI show list payment service providers', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.accounting.org_payment_service_providers.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/SelectPaymentServiceProviders')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Payment Service Providers')
                    ->etc()
            )
            ->has('data')
            ->has('paymentAccountTypes');
    });
});

test('UI show organisation payment service provider', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();

    $response = get(route('grp.org.accounting.org_payment_service_providers.show', [$this->organisation->slug, $orgPaymentServiceProvider->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($orgPaymentServiceProvider) {
        $page
            ->component('Org/Accounting/OrgPaymentServiceProvider')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $orgPaymentServiceProvider->slug)
                    ->etc()
            )
            ->has('navigation')
            ->has('tabs');
    });
});

test('UI show organisation payment service provider (payment accounts tab)', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();

    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/accounting/providers/'.$orgPaymentServiceProvider->slug.'?tab=payment_accounts');

    $response->assertInertia(function (AssertableInertia $page) use ($orgPaymentServiceProvider) {
        $page
            ->component('Org/Accounting/OrgPaymentServiceProvider')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $orgPaymentServiceProvider->slug)
                    ->etc()
            )
            ->has('navigation')
            ->has('tabs');
    });
});

test('UI show organisation payment service provider (payments tab)', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $response                  = get('http://app.aiku.test/org/'.$this->organisation->slug.'/accounting/providers/'.$orgPaymentServiceProvider->slug.'?tab=payments');

    $response->assertInertia(function (AssertableInertia $page) use ($orgPaymentServiceProvider) {
        $page
            ->component('Org/Accounting/OrgPaymentServiceProvider')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $orgPaymentServiceProvider->slug)
                    ->etc()
            )
            ->has('navigation')
            ->has('tabs');
    });
});

test('UI index payment account shops', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $paymentAccount            = $orgPaymentServiceProvider->paymentAccounts->first();
    $response                  = get(
        route(
            'grp.org.accounting.payment-accounts.show.shops.index',
            [$this->organisation->slug, $paymentAccount->slug]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($orgPaymentServiceProvider) {
        $page
            ->component('Org/Accounting/PaymentAccountShops')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->where('title', 'Payment Account Shops')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show organisation payment service provider (history tab)', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $response                  = get('http://app.aiku.test/org/'.$this->organisation->slug.'/accounting/providers/'.$orgPaymentServiceProvider->slug.'?tab=history');

    $response->assertInertia(function (AssertableInertia $page) use ($orgPaymentServiceProvider) {
        $page
            ->component('Org/Accounting/OrgPaymentServiceProvider')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $orgPaymentServiceProvider->slug)
                    ->etc()
            )
            ->has('navigation')
            ->has('tabs');
    });
});

test('UI show list payment accounts in organisation payment service provider', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $response                  = get(
        route(
            'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index',
            [$this->organisation->slug, $orgPaymentServiceProvider->slug]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($orgPaymentServiceProvider) {
        $page
            ->component('Org/Accounting/PaymentAccounts')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Payment Accounts')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show payment account in organisation payment service provider', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $paymentAccount            = $orgPaymentServiceProvider->paymentAccounts->first();
    $response                  = get(route('grp.org.accounting.org_payment_service_providers.show.payment-accounts.show', [
        $this->organisation->slug,
        $orgPaymentServiceProvider->slug,
        $paymentAccount->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($paymentAccount) {
        $page
            ->component('Org/Accounting/PaymentAccount')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $paymentAccount->name)
                    ->has('actions')
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show payment account in organisation payment service provider (stats tab)', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $paymentAccount            = $orgPaymentServiceProvider->paymentAccounts->first();
    $response                  = get('http://app.aiku.test/org/'.$this->organisation->slug.'/accounting/providers/'.$orgPaymentServiceProvider->slug.'/accounts/'.$paymentAccount->slug.'?tab=stats');

    $response->assertInertia(function (AssertableInertia $page) use ($paymentAccount) {
        $page
            ->component('Org/Accounting/PaymentAccount')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $paymentAccount->name)
                    ->has('actions')
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show payment account in organisation payment service provider (history tab)', function () {
    $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
    $paymentAccount            = $orgPaymentServiceProvider->paymentAccounts->first();
    $response                  = get('http://app.aiku.test/org/'.$this->organisation->slug.'/accounting/providers/'.$orgPaymentServiceProvider->slug.'/accounts/'.$paymentAccount->slug.'?tab=history');

    $response->assertInertia(function (AssertableInertia $page) use ($paymentAccount) {
        $page
            ->component('Org/Accounting/PaymentAccount')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $paymentAccount->name)
                    ->has('actions')
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show list payment accounts', function () {
    $response = get(route('grp.org.accounting.payment-accounts.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/PaymentAccounts')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Payment Accounts')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI create payment account', function () {
    $response = get(route('grp.org.accounting.payment-accounts.create', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 1)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New payment account')
                    ->has('actions')
                    ->etc()
            )
            ->has('formData.blueprint.0.fields', 6);
    });
});

test('UI edit payment account', function () {
    $paymentAccount = $this->organisation->paymentAccounts->first();
    $response       = get(route('grp.org.accounting.payment-accounts.edit', [$this->organisation->slug, $paymentAccount->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($paymentAccount) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $paymentAccount->code)
                    ->etc()
            )
            ->has('formData.blueprint.0.fields', 2);
    });
});

test('UI show list payments', function () {
    $response = get(route('grp.org.accounting.payments.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Payments')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Payments')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list invoices in group', function () {
    $response = get(route('grp.overview.accounting.invoices.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list invoices', function () {
    $response = get(route('grp.org.accounting.invoices.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list paid invoices', function () {
    $response = get(route('grp.org.accounting.paid_invoices.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list unpaid invoices', function () {
    $response = get(route('grp.org.accounting.unpaid_invoices.index', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list invoices in shop', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $response = get(route('grp.org.shops.show.dashboard.invoices.index', [$this->organisation->slug, $shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list unpaid invoices in shop', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $response = get(route('grp.org.shops.show.dashboard.invoices.unpaid.index', [$this->organisation->slug, $shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show list invoices in customer', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    $response = get(route('grp.org.shops.show.crm.customers.show.invoices.index', [$this->organisation->slug, $shop->slug, $customer->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($customer) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('tabs')
            ->has('invoices');
    });
});


test('UI show invoice in Organisation', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    $invoice  = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    $response = get(route('grp.org.accounting.invoices.show', [$this->organisation->slug, $invoice->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($invoice) {
        $page->component('Org/Accounting/Invoice')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'navigation',
                fn (AssertableInertia $page) => $page
                    ->has('previous')
                    ->has('next')
            )
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('model', 'Invoice')
                    ->where('title', $invoice->reference)
                    ->etc()
            )
            ->has(
                'tabs',
                fn (AssertableInertia $page) => $page
                    ->has('current')
                    ->has('navigation')
            )
            ->has('order_summary', 4)
            ->has('invoiceExportOptions')
            ->has(
                'box_stats',
                fn (AssertableInertia $page) => $page
                    ->has('delivery_notes')
                    ->has(
                        'customer',
                        fn (AssertableInertia $page) => $page
                            ->has('slug')
                            ->has('reference')
                            ->has('route')
                            ->has('contact_name')
                            ->has('name')
                            ->has('location')
                            ->has('phone')
                            ->has('fiscal_name')
                    )
                    ->has(
                        'information',
                        fn (AssertableInertia $page) => $page
                            ->has('paid_amount')
                            ->has('pay_amount')
                    )
            )
            ->has('invoice');
    });
});

test('UI show invoice in Shop', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    $invoice  = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    $response = get(route('grp.org.shops.show.dashboard.invoices.show', [$this->organisation->slug, $shop->slug, $invoice->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($invoice) {
        $page->component('Org/Accounting/Invoice')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'navigation',
                fn (AssertableInertia $page) => $page
                    ->has('previous')
                    ->has('next')
            )
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('model', 'Invoice')
                    ->where('title', $invoice->reference)
                    ->etc()
            )
            ->has(
                'tabs',
                fn (AssertableInertia $page) => $page
                    ->has('current')
                    ->has('navigation')
            )
            ->has('order_summary', 4)
            ->has('invoiceExportOptions')
            ->has(
                'box_stats',
                fn (AssertableInertia $page) => $page
                    ->has('delivery_notes')
                    ->has(
                        'customer',
                        fn (AssertableInertia $page) => $page
                            ->has('slug')
                            ->has('reference')
                            ->has('route')
                            ->has('contact_name')
                            ->has('name')
                            ->has('location')
                            ->has('phone')
                            ->has('fiscal_name')
                    )
                    ->has(
                        'information',
                        fn (AssertableInertia $page) => $page
                            ->has('paid_amount')
                            ->has('pay_amount')
                    )
            )
            ->has('invoice');
    });
});

test('Delete invoice', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    $invoice  = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    expect($customer->stats->number_invoices)->toBe(3);

    $invoice = DeleteInvoice::make()->action($invoice, [
        'deleted_note' => 'test'
    ]);
    $customer->refresh();
    expect($invoice->trashed())->toBeTrue()
        ->and($customer->stats->number_invoices)->toBe(2);

    return $invoice;
});

test('UI index invoices deleted', function (Invoice $invoice) {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.shops.show.dashboard.invoices.deleted.index',
            [$this->organisation->slug, $invoice->shop->slug]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/DeletedInvoices')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->where('title', 'Deleted Invoices')
                    ->etc()
            )
            ->has('data');
    });
})->depends('Delete invoice');

test('UI show invoices deleted', function (Invoice $invoice) {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.accounting.deleted_invoices.show',
            [$this->organisation->slug, $invoice->slug]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($invoice) {
        $page
            ->component('Org/Accounting/InvoiceDeleted')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $invoice->reference)
                    ->etc()
            )
            ->has('exportPdfRoute')
            ->has('invoice')
            ->has('tabs')
            ->has('order_summary')
            ->has('box_stats');
    });
})->depends('Delete invoice');


test('Store invoice refund', function () {
    $this->withoutExceptionHandling();
    $shop     = $this->shop;
    $customer = createCustomer($shop);


    $tradeUnits = [
        [
            'id'       => $this->tradeUnit1->id,
            'quantity' => 1,
        ]
    ];

    $productData        = array_merge(
        Product::factory()->definition(),
        [
            'trade_units' => $tradeUnits,
            'price'       => 100,
            'unit'        => 'unit'
        ]
    );
    $product            = StoreProduct::make()->action($shop, $productData);
    $invoice            = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    $invoiceTransaction = StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
        'date'            => now(),
        'tax_category_id' => $invoice->tax_category_id,
        'quantity'        => 10,
        'gross_amount'    => 1000,
        'net_amount'      => 1000,
    ]);
    expect($invoiceTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and($invoice)->toBeInstanceOf(Invoice::class);

    $refund = StoreRefund::make()->action($invoice, []);
    expect($refund)->toBeInstanceOf(Invoice::class)
        ->and($refund->type)->toBe(InvoiceTypeEnum::REFUND);

    return $refund;
});

test('Store invoice refund transaction', function (Invoice $refund) {
    $this->withoutExceptionHandling();

    $originalInvoice = $refund->originalInvoice;

    $transaction = $originalInvoice->invoiceTransactions()->first();

    $refundTransaction = StoreRefundInvoiceTransaction::make()->action($refund, $transaction, [
        'net_amount' => $transaction->net_amount,
    ]);

    $refund->refresh();
    expect($refundTransaction)->toBeInstanceOf(InvoiceTransaction::class);

    return $refund;
})->depends('Store invoice refund');

test('Delete Refund', function (Invoice $refund) {
    $this->withoutExceptionHandling();
    $customer = $refund->customer;
    expect($customer->stats->number_invoices_type_refund)->toBe(1);

    ForceDeleteRefund::make()->handle($refund);
    $customer->refresh();
    expect($customer->stats->number_invoices_type_refund)->toBe(0);
})->depends('Store invoice refund');

test('UI index customer balances', function () {
    $response = get(route('grp.org.accounting.balances.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Accounting/CustomerBalances')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Customer Balances')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI get section route accounting balance index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.accounting.balances.index', [
        'organisation' => $this->organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_ACCOUNTING->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->slug);
});

test('UI get section route group overview hub (accounting)', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.overview.hub', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_OVERVIEW->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->group->slug);
});


test('delete payment service provider', function () {
    /** @var Group $group */
    $group = $this->group;
    expect($group->paymentServiceProviders()->count())->toBe(11);
    $paymentServiceProvider = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first();
    DeletePaymentServiceProvider::make()->action($paymentServiceProvider);
    $group->refresh();
    expect($group->paymentServiceProviders()->count())->toBe(10);
});

test('hydrate invoice categories', function () {
    $this->artisan('hydrate:invoice_categories')->assertExitCode(0);
    HydrateInvoiceCategories::run(InvoiceCategory::first());
});


test('export isdoc invoice', function () {
    $invoice = Invoice::first();
    $invoice->update([
        'uuid' => Str::uuid(),
    ]);
    $result = ISDocInvoice::run($invoice);
    expect($result)->toStartWith('<?xml');
});

test('export omega invoice', function () {
    $invoice = Invoice::first();
    $result  = OmegaInvoice::run($invoice);
    expect($result)->toStartWith('R00');
});

test('export omega invoice (many)', function () {
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    $invoice  = StoreInvoice::make()->action($customer, Invoice::factory()->definition());

    $result = OmegaManyInvoice::run($invoice->organisation, [
        'filter' => $invoice->date->format('Ymd').'-'.$invoice->date->format('Ymd'),
        'bucket' => 'all',
        'type'   => 'invoice',
    ], $invoice->shop);

    expect($result)->toBeInstanceOf(StreamedResponse::class);
});

/*
|--------------------------------------------------------------------------
| Enums: exercise every case through every public method
|--------------------------------------------------------------------------
*/

test('accounting enums: cases and instance/static methods', function () {
    $enums = [
        \App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum::class,
        \App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum::class,
        \App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum::class,
        \App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum::class,
        \App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum::class,
        \App\Enums\Accounting\Invoice\InvoicePayDetailedStatusEnum::class,
        \App\Enums\Accounting\Invoice\InvoicePayStatusEnum::class,
        \App\Enums\Accounting\Invoice\InvoiceTypeEnum::class,
        \App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum::class,
        \App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum::class,
        \App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum::class,
        \App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum::class,
        \App\Enums\Accounting\Payment\PaymentClassEnum::class,
        \App\Enums\Accounting\Payment\PaymentStateEnum::class,
        \App\Enums\Accounting\Payment\PaymentStatusEnum::class,
        \App\Enums\Accounting\Payment\PaymentSubsequentStatusEnum::class,
        \App\Enums\Accounting\Payment\PaymentTypeEnum::class,
        \App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum::class,
        \App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum::class,
        \App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum::class,
        \App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum::class,
        \App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum::class,
        \App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderStateEnum::class,
        \App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum::class,
        \App\Enums\Accounting\TopUp\TopUpStatusEnum::class,
        \App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum::class,
    ];

    $noArgInstanceMethods = ['label', 'stateIcon', 'typeIcon', 'snake'];
    $noArgStaticMethods   = ['labels', 'capitalizedLabels', 'typeIcon', 'stateIcon', 'getOptions',
        'getDecreaseReasons', 'getIncreaseReasons', 'shortLabels', 'values'];

    foreach ($enums as $enum) {
        expect($enum::cases())->not->toBeEmpty();

        foreach ($enum::cases() as $case) {
            expect($case->value)->toBeString();
            foreach ($noArgInstanceMethods as $m) {
                if (method_exists($case, $m) && !(new ReflectionMethod($case, $m))->isStatic()) {
                    expect($case->$m())->not->toBeNull();
                }
            }
        }

        foreach ($noArgStaticMethods as $m) {
            if (method_exists($enum, $m) && (new ReflectionMethod($enum, $m))->isStatic()) {
                expect($enum::$m())->toBeArray();
            }
        }
    }

    expect(\App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum::getStaticLabel(
        \App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum::OTHER->value
    ))->toBeString();

    expect(\App\Enums\Accounting\Payment\PaymentStateEnum::count($this->group))
        ->toHaveKeys(['in_process', 'approving', 'completed', 'cancelled', 'error', 'declined'])
        ->and(\App\Enums\Accounting\Payment\PaymentStateEnum::count($this->organisation))->toBeArray();
});

/*
|--------------------------------------------------------------------------
| Models: relations execute, info methods return expected shapes
|--------------------------------------------------------------------------
*/

test('accounting models: relations resolve on real graph', function () {
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    [, $product] = createProduct($shop);

    $invoice     = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    $transaction = StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
        'date'            => now(),
        'tax_category_id' => $invoice->tax_category_id,
        'quantity'        => 2,
        'gross_amount'    => 200,
        'net_amount'      => 200,
    ]);

    $orgPsp         = $this->organisation->orgPaymentServiceProviders()->first();
    $paymentAccount = StorePaymentAccount::make()->action(
        $orgPsp,
        array_merge(PaymentAccount::factory()->definition(), ['type' => PaymentAccountTypeEnum::BANK->value])
    );
    $paymentAccountShop = StorePaymentAccountShop::make()->action($paymentAccount, $shop, [
        'currency_id' => $shop->currency_id,
        'state'       => PaymentAccountShopStateEnum::ACTIVE,
    ]);

    $exerciseRelations = function (object $model) {
        $relationBases = ['BelongsTo', 'HasMany', 'HasOne', 'MorphTo', 'MorphMany', 'MorphOne', 'BelongsToMany', 'MorphToMany'];
        $ref           = new ReflectionClass($model);
        $called        = 0;
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }
            $rt = $method->getReturnType();
            if (!$rt instanceof ReflectionNamedType || !in_array(class_basename($rt->getName()), $relationBases)) {
                continue;
            }
            expect($model->{$method->getName()}())->toBeInstanceOf(
                \Illuminate\Database\Eloquent\Relations\Relation::class
            );
            $called++;
        }

        return $called;
    };

    foreach ([$invoice, $transaction, $paymentAccount, $paymentAccountShop, $orgPsp, $customer->creditTransactions()->getRelated()] as $model) {
        expect($exerciseRelations($model))->toBeGreaterThan(0);
    }

    foreach ([
        new \App\Models\Accounting\Payment(),
        new \App\Models\Accounting\TopUp(),
        new \App\Models\Accounting\MitSavedCard(),
        new \App\Models\Accounting\OrderPaymentApiPoint(),
        new \App\Models\Accounting\TopUpPaymentApiPoint(),
        new \App\Models\Accounting\PaymentGatewayLog(),
        new \App\Models\Accounting\PaymentServiceProvider(),
        new \App\Models\Accounting\OrgPaymentServiceProviderShop(),
        new \App\Models\Accounting\InvoiceCategory(),
        new \App\Models\Accounting\InvoiceCategoryTimeSeries(),
        new \App\Models\Accounting\InvoiceCategoryTimeSeriesRecord(),
        new \App\Models\Accounting\IntrastatExportTimeSeries(),
        new \App\Models\Accounting\IntrastatExportTimeSeriesRecord(),
        new \App\Models\Accounting\IntrastatImportTimeSeries(),
        new \App\Models\Accounting\IntrastatImportTimeSeriesRecord(),
        new \App\Models\Accounting\InvoiceTransactionHasOrgStock(),
        new \App\Models\Accounting\InvoiceTransactionHasStock(),
        new \App\Models\Accounting\InvoiceTransactionHasTradeUnit(),
        new \App\Models\Accounting\InvoiceStats(),
        new \App\Models\Accounting\OrgPaymentServiceProviderStats(),
        new \App\Models\Accounting\PaymentAccountStats(),
        new \App\Models\Accounting\PaymentAccountShopStats(),
        new \App\Models\Accounting\PaymentServiceProviderStats(),
    ] as $model) {
        $exerciseRelations($model);
    }
});

test('accounting models: info and searchable methods', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $shop     = $this->shop;
    $customer = createCustomer($shop);

    $invoice = StoreInvoice::make()->action($customer, Invoice::factory()->definition());

    expect($invoice->generateTags())->toBe(['accounting'])
        ->and($invoice->getRouteKeyName())->toBe('slug')
        ->and($invoice->searchIndexShouldBeUpdated())->toBeBool()
        ->and($invoice->toSearchableArray())->toHaveKeys(['id', 'reference', 'type', 'date'])
        ->and($invoice->getSlugOptions())->toBeInstanceOf(\Spatie\Sluggable\SlugOptions::class);

    $orgPsp        = $this->organisation->orgPaymentServiceProviders()->first();
    $paymentAccount = $orgPsp->paymentAccounts()->firstOr(
        fn () => StorePaymentAccount::make()->action(
            $orgPsp,
            array_merge(PaymentAccount::factory()->definition(), ['type' => PaymentAccountTypeEnum::BANK->value])
        )
    );
    $modelData = array_merge(Payment::factory()->definition(), [
        'status' => \App\Enums\Accounting\Payment\PaymentStatusEnum::SUCCESS->value,
        'state'  => \App\Enums\Accounting\Payment\PaymentStateEnum::COMPLETED->value,
        'type'   => \App\Enums\Accounting\Payment\PaymentTypeEnum::PAYMENT->value,
    ]);
    $payment   = StorePayment::make()->action(
        customer: $customer,
        paymentAccount: $paymentAccount,
        modelData: $modelData
    );

    expect($payment->generateTags())->toBe(['accounting'])
        ->and($payment->searchIndexShouldBeUpdated())->toBeBool()
        ->and($payment->toSearchableArray())->toHaveKey('id');

    $paymentAccountShop = $paymentAccount->paymentAccountShops()->where('shop_id', $shop->id)->first()
        ?? StorePaymentAccountShop::make()->action($paymentAccount, $shop, [
            'currency_id' => $shop->currency_id,
            'state'       => PaymentAccountShopStateEnum::ACTIVE,
        ]);

    expect($paymentAccountShop->generateTags())->toBeArray()
        ->and($paymentAccountShop->getCredentials())->toBeArray()
        ->and($paymentAccountShop->getCheckoutComCredentials())->toBeArray();
});

/*
|--------------------------------------------------------------------------
| Actions: invoice lifecycle (totals, categorise, updates, transaction)
|--------------------------------------------------------------------------
*/

test('invoice lifecycle: totals, categorise, updates', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $shop     = $this->shop;
    $customer = createCustomer($shop);
    [, $product] = createProduct($shop);

    $invoice     = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    $transaction = StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
        'date'            => now(),
        'tax_category_id' => $invoice->tax_category_id,
        'quantity'        => 3,
        'gross_amount'    => 300,
        'net_amount'      => 300,
    ]);

    $invoice = \App\Actions\Accounting\Invoice\CalculateInvoiceTotals::make()->action($invoice);
    expect((float) $invoice->net_amount)->toBe(300.0);

    \App\Actions\Accounting\Invoice\CalculateInvoiceTotalsTaxOnly::make()->action($invoice);

    $categorised = \App\Actions\Accounting\Invoice\CategoriseInvoice::run($invoice);
    expect($categorised)->toBeInstanceOf(Invoice::class);

    $invoice = \App\Actions\Accounting\Invoice\UpdateInvoice::make()->action($invoice, [
        'footer'      => 'thank you',
        'fiscal_name' => 'Fiscal Co',
    ]);
    expect($invoice->fiscal_name)->toBe('Fiscal Co');

    $newDate = now()->subDays(2);
    $invoice = \App\Actions\Accounting\Invoice\UpdateInvoiceDate::make()->handle($invoice, [
        'date' => $newDate,
    ]);
    expect($invoice->date->toDateString())->toBe($newDate->toDateString());

    $transaction = \App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction::make()->action($transaction, [
        'quantity'   => 5,
        'net_amount' => 500,
    ]);
    expect((float) $transaction->net_amount)->toBe(500.0);

    $deleted = \App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction::make()->action($transaction);
    expect($deleted->exists)->toBeTrue()
        ->and($invoice->fresh()->invoiceTransactions()->count())->toBe(0);
});

test('invoice non-strict update overrides amounts', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $customer = createCustomer($this->shop);
    $invoice  = StoreInvoice::make()->action($customer, Invoice::factory()->definition());

    $invoice = \App\Actions\Accounting\Invoice\UpdateInvoice::make()->action($invoice, [
        'net_amount'   => 111,
        'total_amount' => 111,
        'gross_amount' => 111,
        'tax_amount'   => 0,
    ], strict: false);

    expect((float) $invoice->net_amount)->toBe(111.0);
});

/*
|--------------------------------------------------------------------------
| Actions: credit transactions increase / decrease
|--------------------------------------------------------------------------
*/

test('increase and decrease customer credit', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    $increase = \App\Actions\Accounting\CreditTransaction\IncreaseCreditTransactionCustomer::make()->action($customer, [
        'amount' => 500,
        'reason' => \App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum::COMPENSATE_CUSTOMER->value,
        'type'   => CreditTransactionTypeEnum::COMPENSATION->value,
    ]);
    expect($increase)->toBeInstanceOf(CreditTransaction::class)
        ->and($increase->type)->toBe(CreditTransactionTypeEnum::COMPENSATION);

    $decrease = \App\Actions\Accounting\CreditTransaction\DecreaseCreditTransactionCustomer::make()->action($customer, [
        'amount' => -100,
        'reason' => \App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum::MONEY_BACK->value,
        'type'   => CreditTransactionTypeEnum::MONEY_BACK->value,
    ]);
    expect($decrease)->toBeInstanceOf(CreditTransaction::class)
        ->and($decrease->type)->toBe(CreditTransactionTypeEnum::MONEY_BACK);

    $customer->refresh();
    expect($customer->balance)->toBe('400.00');
});

/*
|--------------------------------------------------------------------------
| Actions: MIT saved card store + update
|--------------------------------------------------------------------------
*/

test('update mit saved card', function () {
    // ponytail: StoreMitSavedCard validation targets a non-existent table
    // ('payment_account_shops' vs real 'payment_account_shop'), so create the
    // card directly to exercise UpdateMitSavedCard. See bug report.
    $shop     = $this->shop;
    $customer = createCustomer($shop);

    $orgPsp         = $this->organisation->orgPaymentServiceProviders()->first();
    $paymentAccount = $orgPsp->paymentAccounts()->firstOr(
        fn () => StorePaymentAccount::make()->action(
            $orgPsp,
            array_merge(PaymentAccount::factory()->definition(), ['type' => PaymentAccountTypeEnum::BANK->value])
        )
    );
    $paymentAccountShop = $paymentAccount->paymentAccountShops()->where('shop_id', $shop->id)->first()
        ?? StorePaymentAccountShop::make()->action($paymentAccount, $shop, [
            'currency_id' => $shop->currency_id,
            'state'       => PaymentAccountShopStateEnum::ACTIVE,
        ]);

    $card = $customer->mitSavedCard()->create([
        'group_id'                => $customer->group_id,
        'organisation_id'         => $customer->organisation_id,
        'shop_id'                 => $customer->shop_id,
        'payment_account_shop_id' => $paymentAccountShop->id,
        'ulid'                    => Str::ulid(),
        'state'                   => \App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum::SUCCESS->value,
        'token'                   => 'tok_123',
        'priority'                => 1,
    ]);

    $card = \App\Actions\Accounting\MitSavedCard\UpdateMitSavedCard::make()->asAction($card, [
        'last_four_digits' => '4242',
        'card_type'        => 'visa',
        'state'            => \App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum::EXPIRED->value,
    ]);
    expect($card->last_four_digits)->toBe('4242')
        ->and($card->card_type)->toBe('visa');
});

/*
|--------------------------------------------------------------------------
| Actions: payment account type-specific updates
|--------------------------------------------------------------------------
*/

test('update payment account by type', function () {
    $shop   = $this->shop;
    $orgPsp = $this->organisation->orgPaymentServiceProviders()->first();
    $account = $orgPsp->paymentAccounts()->firstOr(
        fn () => StorePaymentAccount::make()->action(
            $orgPsp,
            array_merge(PaymentAccount::factory()->definition(), ['type' => PaymentAccountTypeEnum::BANK->value])
        )
    );

    $account = \App\Actions\Accounting\PaymentAccount\Types\UpdateBankPaymentAccount::make()->action($account, [
        'bank_name'         => 'Big Bank',
        'bank_account_name' => 'Ops',
    ]);
    expect(\Illuminate\Support\Arr::get($account->data, 'bank_name'))->toBe('Big Bank');

    $account = \App\Actions\Accounting\PaymentAccount\Types\UpdateCashPaymentAccount::make()->action($account, [
        'name' => 'Petty Cash',
    ]);
    expect($account->name)->toBe('Petty Cash');

    $account = \App\Actions\Accounting\PaymentAccount\Types\UpdateBraintreePaymentAccount::make()->action($account, [
        'braintree_client_id'     => 'bt_id',
        'braintree_client_secret' => 'bt_secret',
    ]);
    expect(\Illuminate\Support\Arr::get($account->data, 'braintree_client_id'))->toBe('bt_id');
});

/*
|--------------------------------------------------------------------------
| Actions: invoice category delete
|--------------------------------------------------------------------------
*/

test('store then delete invoice category', function () {
    $invoiceCategory = StoreInvoiceCategory::make()->action($this->organisation, [
        'name'        => 'Temp Category',
        'state'       => InvoiceCategoryStateEnum::ACTIVE,
        'type'        => InvoiceCategoryTypeEnum::IN_ORGANISATION,
        'currency_id' => $this->organisation->currency_id,
        'priority'    => 99,
    ]);
    expect($invoiceCategory)->toBeInstanceOf(InvoiceCategory::class);

    $deleted = \App\Actions\Accounting\InvoiceCategory\DeleteInvoiceCategory::make()->handle($invoiceCategory);
    expect(InvoiceCategory::find($invoiceCategory->id))->toBeNull()
        ->and($deleted)->toBeInstanceOf(InvoiceCategory::class);
});

/*
|--------------------------------------------------------------------------
| UI: organisation-level index & report pages (render, no external calls)
|--------------------------------------------------------------------------
*/

test('UI accounting org-level index and report pages render', function () {
    $org = $this->organisation->slug;

    $routes = [
        'grp.org.accounting.credit_transactions.index',
        'grp.org.accounting.invoices-shop',
        'grp.org.accounting.payments.methods.index',
        'grp.org.accounting.refunds.index',
        'grp.org.overview.invoices.index',
        'grp.org.overview.refunds.index',
        'grp.org.reports.customer-credit',
        'grp.org.reports.intrastat.exports',
        'grp.org.reports.intrastat.imports',
        'grp.org.reports.montana-invoices',
        'grp.org.reports.sage-invoices',
    ];

    foreach ($routes as $routeName) {
        get(route($routeName, [$org]))->assertOk();
    }
});

/*
|--------------------------------------------------------------------------
| UI: invoice edit + refund show/index
|--------------------------------------------------------------------------
*/

test('UI invoice edit and refund pages render', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $shop     = $this->shop;
    $customer = createCustomer($shop);
    [, $product] = createProduct($shop);

    $invoice     = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    $transaction = StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
        'date'            => now(),
        'tax_category_id' => $invoice->tax_category_id,
        'quantity'        => 2,
        'gross_amount'    => 200,
        'net_amount'      => 200,
    ]);

    get(route('grp.org.accounting.invoices.edit', [$this->organisation->slug, $invoice->slug]))->assertOk();

    $refund = StoreRefund::make()->action($invoice, []);
    StoreRefundInvoiceTransaction::make()->action($refund, $transaction, [
        'net_amount' => $transaction->net_amount,
    ]);

    get(route('grp.org.accounting.refunds.show', [$this->organisation->slug, $refund->slug]))->assertOk();
    get(route('grp.org.accounting.invoices.show.refunds.index', [$this->organisation->slug, $invoice->slug]))->assertOk();
});

/*
|--------------------------------------------------------------------------
| UI: payment account customers/shops sub-pages
|--------------------------------------------------------------------------
*/

test('UI payment account sub-pages render', function () {
    $shop   = $this->shop;
    $orgPsp = $this->organisation->orgPaymentServiceProviders()->first();
    $account = StorePaymentAccount::make()->action(
        $orgPsp,
        array_merge(PaymentAccount::factory()->definition(), ['type' => PaymentAccountTypeEnum::BANK->value])
    );
    $paymentAccountShop = StorePaymentAccountShop::make()->action($account, $shop, [
        'currency_id' => $shop->currency_id,
        'state'       => PaymentAccountShopStateEnum::ACTIVE,
    ]);

    $org = $this->organisation->slug;

    get(route('grp.org.accounting.payment-accounts.show.customers.index', [$org, $account->slug]))->assertOk();
    get(route('grp.org.accounting.payment-accounts.show.shops.index', [$org, $account->slug]))->assertOk();
    get(route('grp.org.accounting.payment-accounts.show.shops.show', [$org, $account->slug, $paymentAccountShop]))->assertOk();
    get(route('grp.org.accounting.payment-accounts.show.shops.edit', [$org, $account->slug, $paymentAccountShop]))->assertOk();
});

/*
|--------------------------------------------------------------------------
| UI: top-ups and credit transactions in shop dashboard
|--------------------------------------------------------------------------
*/

test('UI shop top-up and credit transaction pages render', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $shop   = $this->shop;
    $orgPsp = $this->organisation->orgPaymentServiceProviders()->first();
    $account = $orgPsp->paymentAccounts()->firstOr(
        fn () => StorePaymentAccount::make()->action(
            $orgPsp,
            array_merge(PaymentAccount::factory()->definition(), ['type' => PaymentAccountTypeEnum::BANK->value])
        )
    );
    $customer = createCustomer($shop);
    $payment  = StorePayment::make()->action(
        customer: $customer,
        paymentAccount: $account,
        modelData: Payment::factory()->definition()
    );
    $topUp = StoreTopUp::make()->action($payment, ['amount' => 100, 'reference' => 'UITOP01']);

    $org = $this->organisation->slug;

    get(route('grp.org.shops.show.dashboard.payments.accounting.top_ups.index', [$org, $shop->slug]))->assertOk();
    get(route('grp.org.shops.show.dashboard.payments.accounting.top_ups.show', [$org, $shop->slug, $topUp]))->assertOk();
    get(route('grp.org.shops.show.dashboard.payments.accounting.credit_transactions.index', [$org, $shop->slug]))->assertOk();
    get(route('grp.org.shops.show.dashboard.payments.accounting.dashboard', [$org, $shop->slug]))->assertOk();
});

/*
|--------------------------------------------------------------------------
| UI: group overview + product invoices pages
|--------------------------------------------------------------------------
*/

test('UI group overview and product invoice pages render', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $shop     = $this->shop;
    $customer = createCustomer($shop);
    [, $product] = createProduct($shop);

    $invoice = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
    StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
        'date'            => now(),
        'tax_category_id' => $invoice->tax_category_id,
        'quantity'        => 1,
        'gross_amount'    => 100,
        'net_amount'      => 100,
    ]);

    get(route('grp.overview.accounting.invoices.index'))->assertOk();
    get(route('grp.overview.accounting.refunds.index'))->assertOk();
    get(route('grp.overview.ordering.transactions.index'))->assertOk();

    get(route('grp.org.shops.show.catalogue.products.current_products.invoices', [
        $this->organisation->slug, $shop->slug, $product->slug,
    ]))->assertOk();
});

/*
|--------------------------------------------------------------------------
| UI: fulfilment accounting, statements and standalone invoice pages
|--------------------------------------------------------------------------
*/

test('UI fulfilment accounting and invoice pages render', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $fulfilment = createFulfilment($this->organisation);
    $org        = $fulfilment->organisation->slug;

    $fulfilmentCustomer = \App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'contact_name'    => 'Contact FC',
            'company_name'    => 'Company FC',
            'interest'        => ['pallets_storage', 'items_storage'],
            'contact_address' => \App\Models\Helpers\Address::factory()->definition(),
        ]
    );

    $service = \App\Actions\Billables\Service\StoreService::make()->action(
        $fulfilment->shop,
        [
            'price' => 100,
            'unit'  => 'job',
            'code'  => 'ACC-SER-01',
            'name'  => 'Acc Service',
            'state' => \App\Enums\Billables\Service\ServiceStateEnum::ACTIVE,
        ]
    );

    $inProcess = \App\Actions\Accounting\StandaloneFulfilmentInvoice\StoreStandaloneFulfilmentInvoice::make()->action($fulfilmentCustomer, []);
    \App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\StoreStandaloneFulfilmentInvoiceTransaction::make()
        ->action($inProcess, $service->historicAsset, ['quantity' => 5]);

    $completed = \App\Actions\Accounting\StandaloneFulfilmentInvoice\StoreStandaloneFulfilmentInvoice::make()->action($fulfilmentCustomer, []);
    \App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\StoreStandaloneFulfilmentInvoiceTransaction::make()
        ->action($completed, $service->historicAsset, ['quantity' => 3]);
    $completed = \App\Actions\Accounting\StandaloneFulfilmentInvoice\CompleteStandaloneFulfilmentInvoice::make()->action($completed);

    $f = $fulfilment->slug;

    // operations / accounting dashboard
    get(route('grp.org.fulfilments.show.operations.accounting.dashboard', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.accounting.customer_balances.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.accounting.payments.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.accounting.accounts.index', [$org, $f]))->assertOk();

    // statements
    get(route('grp.org.fulfilments.show.operations.invoices.all.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.invoices.deleted_invoices.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.invoices.paid_invoices.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.invoices.unpaid_invoices.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.invoices.refunds.index', [$org, $f]))->assertOk();
    get(route('grp.org.fulfilments.show.operations.invoices.show', [$org, $f, $completed]))->assertOk();

    // crm customer invoices + standalone in-process
    get(route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [$org, $f, $fulfilmentCustomer]))->assertOk();
    get(route('grp.org.fulfilments.show.crm.customers.show.invoices.show', [$org, $f, $fulfilmentCustomer, $completed]))->assertOk();
    get(route('grp.org.fulfilments.show.crm.customers.show.invoices.in-process.show', [$org, $f, $fulfilmentCustomer, $inProcess]))->assertOk();
});

/*
|--------------------------------------------------------------------------
| UI: refund action endpoints (create / tax / finalise / delete)
|--------------------------------------------------------------------------
*/

test('UI refund action endpoints create tax finalise and delete', function () {
    GetCurrencyExchange::shouldRun()->andReturn(1);

    $org      = $this->organisation->slug;
    $shop     = $this->shop;
    $customer = createCustomer($shop);
    [, $product] = createProduct($shop);

    $makeInvoiceWithTransaction = function () use ($customer, $product) {
        $invoice = StoreInvoice::make()->action($customer, Invoice::factory()->definition());
        StoreInvoiceTransaction::make()->action($invoice, $product->historicAsset, [
            'date'            => now(),
            'tax_category_id' => $invoice->tax_category_id,
            'quantity'        => 2,
            'gross_amount'    => 200,
            'net_amount'      => 200,
        ]);

        return $invoice->refresh();
    };

    // CreateRefund (POST) -> redirects to the referral route's refunds.show
    $invoiceA = $makeInvoiceWithTransaction();
    \Pest\Laravel\post(route('grp.models.refund.create', [$invoiceA]), [
        'referral_route' => [
            'name'       => 'grp.org.accounting.invoices.show',
            'parameters' => [$org, $invoiceA->slug],
        ],
    ])->assertRedirect();
    expect($invoiceA->refunds()->count())->toBe(1);

    // CreateTaxRefund (POST)
    $invoiceB = $makeInvoiceWithTransaction();
    \Pest\Laravel\post(route('grp.models.refund.create_tax_refund', [$invoiceB]), [
        'referral_route' => [
            'name'       => 'grp.org.accounting.invoices.show',
            'parameters' => [$org, $invoiceB->slug],
        ],
    ])->assertRedirect();
    $taxRefund = $invoiceB->refunds()->first();
    expect($taxRefund->is_tax_only)->toBeTrue();

    // FinaliseRefund (::action) on the in-process refund of invoice A
    $refundA      = $invoiceA->refunds()->first();
    $transactionA = $invoiceA->invoiceTransactions()->first();
    StoreRefundInvoiceTransaction::make()->action($refundA, $transactionA, [
        'net_amount' => $transactionA->net_amount,
    ]);
    $finalised = \App\Actions\Accounting\Invoice\UI\FinaliseRefund::make()->action($refundA, []);
    expect($finalised->in_process)->toBeFalse();

    // DeleteRefund (PATCH) on the tax refund of invoice B
    \Pest\Laravel\patch(route('grp.models.refund.delete', [$taxRefund]), [
        'deleted_note' => 'test delete',
    ])->assertRedirect();
    expect($taxRefund->refresh()->trashed())->toBeTrue();
});
