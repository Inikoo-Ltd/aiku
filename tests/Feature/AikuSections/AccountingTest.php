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
use App\Actions\Accounting\Payment\Search\ReindexPaymentSearch;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentAccount\Search\ReindexPaymentAccountSearch;
use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccount\UpdatePaymentAccount;
use App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop;
use App\Actions\Accounting\PaymentAccountShop\UpdatePaymentAccountShop;
use App\Actions\Accounting\PaymentServiceProvider\DeletePaymentServiceProvider;
use App\Actions\Accounting\PaymentServiceProvider\UpdatePaymentServiceProvider;
use App\Actions\Accounting\TopUp\Search\ReindexTopUpSearch;
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

    $stocks          = createStocks($this->group);
    $orgStocks       = createOrgStocks($this->organisation, $stocks);
    $this->orgStock1 = $orgStocks[0];
    $this->orgStock2 = $orgStocks[1];

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
    expect(PaymentServiceProvider::count())->toBe(12)->
    and(
        $this->group->accountingStats->number_payment_service_providers
    )->toBe(12);
});

test('add payment service provider to organisation', function () {
    expect($this->organisation->accountingStats->number_org_payment_service_providers)->toBe(1)
        ->and($this->organisation->accountingStats->number_org_payment_service_providers_type_account)->toBe(1)
        ->and($this->group->accountingStats->number_payment_service_providers)->toBe(12);

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


//todo restrict payments account types depending of the Service Account type
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
                    ->where('title', 'new payment')
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
        ->and($topUp->creditTransaction->type)->toBe(CreditTransactionTypeEnum::TOP_UP->value);

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
        ->and($topUp->creditTransaction->type)->toBe(CreditTransactionTypeEnum::TOP_UP->value);

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

test('UI create invoice categories', function () {
    $response = get(route('grp.org.accounting.invoice-categories.create', $this->organisation->slug));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('formData')
            ->has('pageHead')
            ->has('breadcrumbs', 4);
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
                    ->where('title', 'accounting')
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

// test('UI show payment account in organisation payment service provider (payments tab)', function () {
//     $this->withoutExceptionHandling();
//     $orgPaymentServiceProvider = $this->organisation->orgPaymentServiceProviders->first();
//     $paymentAccount            = $orgPaymentServiceProvider->paymentAccounts->first();

//     $response                  = get('http://app.aiku.test/org/'.$this->organisation->slug.'/accounting/providers/'.$orgPaymentServiceProvider->slug.'/accounts/'.$paymentAccount->slug.'?tab=payments');

//     $response->assertInertia(function (AssertableInertia $page) use ($paymentAccount) {
//         $page
//             ->component('Org/Accounting/PaymentAccount')
//             ->has('title')
//             ->has('breadcrumbs', 4)
//             ->has('pageHead')
//             ->has(
//                 'pageHead',
//                 fn (AssertableInertia $page) => $page
//                     ->where('title', $paymentAccount->name)
//                     ->has('actions')
//                     ->etc()
//             )
//             ->has('tabs');
//     });
// });
//  THIS NO LONGER EXIST

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
                    ->where('title', 'new payment account')
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
                    ->where('title', 'payments')
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
                    ->where('model', 'invoice')
                    ->where('title', $invoice->reference)
                    ->etc()
            )
            ->has(
                'tabs',
                fn (AssertableInertia $page) => $page
                    ->has('current')
                    ->has('navigation')
            )
            ->has('order_summary', 3)
            ->has('invoiceExportOptions')
            ->has(
                'box_stats',
                fn (AssertableInertia $page) => $page
                    ->has(
                        'customer',
                        fn (AssertableInertia $page) => $page
                            ->has('slug')
                            ->has('reference')
                            ->has('route')
                            ->has('contact_name')
                            ->has('company_name')
                            ->has('location')
                            ->has('phone')
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
                    ->where('model', 'invoice')
                    ->where('title', $invoice->reference)
                    ->etc()
            )
            ->has(
                'tabs',
                fn (AssertableInertia $page) => $page
                    ->has('current')
                    ->has('navigation')
            )
            ->has('order_summary', 3)
            ->has('invoiceExportOptions')
            ->has(
                'box_stats',
                fn (AssertableInertia $page) => $page
                    ->has(
                        'customer',
                        fn (AssertableInertia $page) => $page
                            ->has('slug')
                            ->has('reference')
                            ->has('route')
                            ->has('contact_name')
                            ->has('company_name')
                            ->has('location')
                            ->has('phone')
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

    $orgStocks = [
        $this->orgStock1->id => [
            'quantity' => 1,
        ]
    ];

    $productData        = array_merge(
        Product::factory()->definition(),
        [
            'org_stocks' => $orgStocks,
            'price'      => 100,
            'unit'       => 'unit'
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


test('payments search', function () {
    $this->artisan('search:payments')->assertExitCode(0);

    $payment = Payment::first();
    ReindexPaymentSearch::run($payment);
    expect($payment->universalSearch()->count())->toBe(1);
});

test('payment accounts search', function () {
    $this->artisan('search:payment_accounts')->assertExitCode(0);

    $paymentAccount = PaymentAccount::first();
    ReindexPaymentAccountSearch::run($paymentAccount);
    expect($paymentAccount->universalSearch()->count())->toBe(1);
});

test('top up search', function () {
    $this->artisan('search:top_ups')->assertExitCode(0);
    $topUp = TopUp::first();
    ReindexTopUpSearch::run($topUp);
    expect($topUp->universalSearch()->count())->toBe(1);
});

test('delete payment service provider', function () {
    /** @var Group $group */
    $group = $this->group;
    expect($group->paymentServiceProviders()->count())->toBe(12);
    $paymentServiceProvider = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first();
    DeletePaymentServiceProvider::make()->action($paymentServiceProvider);
    $group->refresh();
    expect($group->paymentServiceProviders()->count())->toBe(11);
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
