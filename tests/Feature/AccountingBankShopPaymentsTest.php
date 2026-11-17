<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\BankConnection\SetBankConnectionAutoPay;
use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\Accounting\BankTransaction\PayMatchedBankTransactions;
use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProviderAccount;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, , $this->shop) = createShop();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    GetCurrencyExchange::shouldRun()->andReturn(1.0);

    $this->otherShop = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['currency_id' => $this->shop->currency_id]));
    $this->otherShop->updateQuietly(['state' => ShopStateEnum::OPEN, 'type' => ShopTypeEnum::B2B]);

    $this->bankConnection = BankConnection::create([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'provider'        => BankConnectionProviderEnum::WISE,
        'name'            => 'Wise shop payments',
        'external_id'     => (string)random_int(1000, 9999),
    ]);
    $bankAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('code', 'bank')->firstOrFail(),
        ['code' => 'wise'.random_int(1000, 99999), 'name' => 'Wise shop payments']
    );
    SetBankConnectionAutoPay::run($this->bankConnection, [$this->shop->currency->code => $bankAccount->code]);
    SetBankConnectionAutoPay::run($this->bankConnection->refresh(), [], off: true);
    $this->bankConnection->refresh();
});

function shopPaymentsLine(BankConnection $bankConnection, float $amount, int $currencyId, string $payer = 'Payer Ltd'): BankTransaction
{
    return BankTransaction::create([
        'group_id'                => $bankConnection->group_id,
        'organisation_id'         => $bankConnection->organisation_id,
        'bank_connection_id'      => $bankConnection->id,
        'provider_transaction_id' => 'shop'.random_int(100000, 9999999),
        'provider_type'           => 'TRANSFER',
        'direction'               => BankTransactionDirectionEnum::IN,
        'amount'                  => $amount,
        'currency_id'             => $currencyId,
        'status'                  => BankTransactionStatusEnum::BOOKED,
        'reference'               => 'Thanks',
        'counterparty_name'       => $payer,
        'booked_at'               => now(),
        'match_state'             => BankTransactionMatchStateEnum::UNMATCHED,
        'match_data'              => ['reason' => 'no order number in the reference'],
    ]);
}

function shopPaymentsOrder(Customer $customer, float $total): Order
{
    $order = StoreOrder::make()->action($customer, [
        'date'             => date('Y-m-d'),
        'delivery_address' => new Address(Address::factory()->definition()),
        'billing_address'  => new Address(Address::factory()->definition()),
    ]);
    $order->updateQuietly(['reference' => 'SHP'.random_int(100000, 999999), 'state' => OrderStateEnum::SUBMITTED, 'total_amount' => $total, 'payment_amount' => 0]);

    return $order->refresh();
}

function shopPaymentsStaff(Shop $shop): User
{
    $user = User::factory()->create(['group_id' => $shop->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($user, ["orders.$shop->id.view", "orders.$shop->id.edit"]);

    return $user;
}

test('shop staff pay a bank payment only to an order of their own shop, and the decision is audited with who took it', function () {
    $staff      = shopPaymentsStaff($this->shop);
    $ownOrder   = shopPaymentsOrder(createCustomer($this->shop), 40);
    $otherOrder = shopPaymentsOrder(createCustomer($this->otherShop), 40);
    $payer      = $ownOrder->customer->name;
    $line       = shopPaymentsLine($this->bankConnection, 40, $this->shop->currency_id, $payer);
    $shopRoute  = fn (string $name, BankTransaction $bankTransaction) => route("grp.models.org.shop.bank_transaction.$name", [$this->organisation->id, $this->shop->id, $bankTransaction->id]);

    post($shopRoute('pay_order', $line), ['order_id' => $otherOrder->id, 'order_reference' => $otherOrder->reference])->assertSessionHasErrors('order');
    expect($line->refresh()->payment_id)->toBeNull();

    post(route('grp.models.org.bank_transaction.pay_order', [$this->organisation->id, $line->id]), ['order_id' => $ownOrder->id, 'order_reference' => $ownOrder->reference])->assertForbidden();
    post(route('grp.models.org.shop.bank_transaction.pay_order', [$this->organisation->id, $this->otherShop->id, $line->id]), ['order_id' => $otherOrder->id, 'order_reference' => $otherOrder->reference])->assertForbidden();
    post(route('grp.models.org.bank_transaction.dismiss', [$this->organisation->id, $line->id]), ['note' => 'not ours'])->assertForbidden();

    $setAside = shopPaymentsLine($this->bankConnection, 40, $this->shop->currency_id, $payer);
    $setAside->update(['match_state' => BankTransactionMatchStateEnum::DISMISSED, 'match_data' => ['dismissed_note' => 'refund from a supplier']]);
    $tiedToOtherShop = shopPaymentsLine($this->bankConnection, 40, $this->shop->currency_id, $payer);
    $nobodysPayer    = shopPaymentsLine($this->bankConnection, 40, $this->shop->currency_id, 'Unknown Payer '.random_int(1000, 9999));
    post($shopRoute('pay_order', $nobodysPayer), ['order_id' => $ownOrder->id, 'order_reference' => $ownOrder->reference])->assertSessionHasErrors('order');
    post($shopRoute('customer_credit', $nobodysPayer), ['customer_id' => $ownOrder->customer_id])->assertSessionHasErrors('order');
    post($shopRoute('point_customer', $nobodysPayer), ['customer_id' => $ownOrder->customer_id])->assertSessionHasErrors('order');
    expect($nobodysPayer->refresh()->payment_id)->toBeNull()->and($nobodysPayer->staff_hint)->toBeNull();
    $tiedToOtherShop->update(['match_state' => BankTransactionMatchStateEnum::MATCHED, 'order_id' => $otherOrder->id]);
    post($shopRoute('pay_order', $setAside), ['order_id' => $ownOrder->id, 'order_reference' => $ownOrder->reference])->assertSessionHasErrors('order');
    post($shopRoute('customer_credit', $setAside), ['customer_id' => $ownOrder->customer_id])->assertSessionHasErrors('order');
    post($shopRoute('pay_order', $tiedToOtherShop), ['order_id' => $ownOrder->id, 'order_reference' => $ownOrder->reference])->assertSessionHasErrors('order');
    expect($setAside->refresh()->payment_id)->toBeNull()->and($tiedToOtherShop->refresh()->payment_id)->toBeNull();

    post($shopRoute('pay_order', $line), ['order_id' => $ownOrder->id, 'order_reference' => $ownOrder->reference, 'absorb_bank_fee' => true])->assertSessionHasNoErrors();

    $line->refresh();
    $audit = DB::table('audits')->where('auditable_type', 'BankTransaction')->where('auditable_id', $line->id)->latest('id')->first();
    expect($line->payment_id)->not->toBeNull()
        ->and($line->order_id)->toBe($ownOrder->id)
        ->and($line->bank_fee_payment_id)->toBeNull()
        ->and($audit->event)->toBe('paid_to_order')
        ->and($audit->user_id)->toBe($staff->id)
        ->and(json_decode($audit->new_values, true))->toMatchArray(['order' => $ownOrder->reference, 'from_shop' => $this->shop->code]);
});

test('shop staff say which customer of their shop sent a payment, in any currency, and the hint survives matching again', function () {
    $staff         = shopPaymentsStaff($this->shop);
    $customer      = createCustomer($this->shop);
    $otherCustomer = createCustomer($this->otherShop);
    $otherCurrency = Currency::where('id', '!=', $this->shop->currency_id)->value('id');
    $line          = shopPaymentsLine($this->bankConnection, 55, $otherCurrency, $customer->name);
    $shopRoute     = fn (string $name) => route("grp.models.org.shop.bank_transaction.$name", [$this->organisation->id, $this->shop->id, $line->id]);

    post($shopRoute('customer_credit'), ['customer_id' => $customer->id])->assertSessionHasErrors('customer');
    post($shopRoute('point_customer'), ['customer_id' => $otherCustomer->id])->assertSessionHasErrors('customer');

    get(route('grp.json.shop.bank_transaction.customer_candidates', [$this->organisation->id, $this->shop->id, $line->id, 'search' => $customer->reference, 'any_currency' => 1]))
        ->assertOk()
        ->assertJsonFragment(['id' => $customer->id]);

    post($shopRoute('point_customer'), ['customer_id' => $customer->id, 'note' => 'paid in the wrong currency'])->assertSessionHasNoErrors();

    $line->update(['match_state' => null, 'order_id' => null, 'match_data' => [], 'matched_at' => null]);
    MatchBankTransactions::run($this->bankConnection);
    $line->refresh();
    $audit = DB::table('audits')->where('auditable_type', 'BankTransaction')->where('auditable_id', $line->id)->latest('id')->first();
    expect($line->payment_id)->toBeNull()
        ->and($line->staff_hint)->toMatchArray(['customer_id' => $customer->id, 'shop_code' => $this->shop->code, 'note' => 'paid in the wrong currency', 'user_id' => $staff->id])
        ->and($audit->event)->toBe('pointed_to_customer')
        ->and($audit->user_id)->toBe($staff->id);
});

test('the shop backlog and customers count only the waiting payments pointing at the shop, linking to the shop bank payments page', function () {
    shopPaymentsStaff($this->shop);
    $customer      = createCustomer($this->shop);
    $otherCurrency = Currency::where('id', '!=', $this->shop->currency_id)->value('id');
    $waiting       = IndexBankIncoming::waitingForShop($this->shop);

    $byName        = shopPaymentsLine($this->bankConnection, 12.5, $this->shop->currency_id, mb_strtoupper($customer->name));
    $wrongCurrency = shopPaymentsLine($this->bankConnection, 9, $otherCurrency, $customer->name);
    $nobodys       = shopPaymentsLine($this->bankConnection, 7, $this->shop->currency_id, 'Unknown Payer '.random_int(1000, 9999));
    $ankorstore    = shopPaymentsLine($this->bankConnection, 230, $this->shop->currency_id, 'Stripe Technology Eu');
    $ankorstore->update(['reference' => 'Ankorstore T1B3B4', 'match_state' => null, 'match_data' => []]);

    $paidBefore = shopPaymentsLine($this->bankConnection, 40, $this->shop->currency_id, 'Old Name s.r.o.');
    $paidBefore->update(['counterparty_account' => '2900123456/2010']);
    PayMatchedBankTransactions::make()->payToOrder($paidBefore, shopPaymentsOrder($customer, 40), false, 'staff');
    $sameAccount = shopPaymentsLine($this->bankConnection, 15, $this->shop->currency_id, 'New Name s.r.o.');
    $sameAccount->update(['counterparty_account' => '2900123456/2010']);

    MatchBankTransactions::run($this->bankConnection);
    expect($ankorstore->refresh()->match_state)->toBe(BankTransactionMatchStateEnum::UNMATCHED)
        ->and($ankorstore->match_data)->toMatchArray(['provider' => 'ankorstore', 'reason' => 'Ankorstore payout'])
        ->and(BankTransaction::whereKey($ankorstore->id)->selectRaw(IndexBankIncoming::BUCKET_SQL.' as section')->value('section'))->toBe('marketplaces');

    $notice = IndexBankIncoming::shopNotice($this->shop, auth()->user());
    expect($notice['count'])->toBe($waiting + 3)
        ->and($notice['route']['name'])->toBe('grp.org.shops.show.ordering.bank_payments');

    $token = 'lead'.random_int(100000, 999999);
    foreach ([$byName, $wrongCurrency, $nobodys, $ankorstore, $sameAccount] as $line) {
        $line->update(['reference' => $line->reference.' '.$token]);
    }
    $listed = get(route('grp.org.shops.show.ordering.bank_payments', [$this->organisation->slug, $this->shop->slug, 'filter' => ['global' => $token]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Accounting/BankIncoming')->where('shop.id', $this->shop->id)->where('canEdit', true))
        ->viewData('page')['props']['data']['data'];
    expect(collect($listed)->pluck('id')->sort()->values()->all())->toBe(collect([$byName->id, $wrongCurrency->id, $sameAccount->id])->sort()->values()->all());

    $outsider = User::factory()->create(['group_id' => $this->shop->group_id, 'status' => true]);
    actingAsUserWithOnlyPermissions($outsider, ["orders.{$this->otherShop->id}.view"]);
    expect(IndexBankIncoming::shopNotice($this->shop, $outsider))->toBeNull();
    get(route('grp.org.shops.show.ordering.bank_payments', [$this->organisation->slug, $this->shop->slug]))->assertForbidden();
});
