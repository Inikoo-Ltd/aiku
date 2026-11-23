<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\EInvoice\BuildUblInvoice;
use App\Actions\Accounting\EInvoice\IsInvoiceInEInvoiceScope;
use App\Actions\Accounting\EInvoice\PollEInvoiceTransmissions;
use App\Actions\Accounting\EInvoice\SendEInvoice;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceEInvoiceTransmission;
use App\Models\SysAdmin\Guest;
use App\Notifications\EInvoiceProblemNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, , $this->shop) = createShop();
    $this->customer = createCustomer($this->shop);

    createInvoiceFor($this->customer, $this->shop, now()->toDateString(), 100);
    $this->invoice = Invoice::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();

    config([
        'services.softip.url'             => 'https://softip.test',
        'services.softip.api_key'         => 'secret-key-'.uniqid(),
        'services.softip.organization_id' => null,
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    $GLOBALS['softipRoutes'] = [];
    Http::fake(function (Request $request) {
        foreach ($GLOBALS['softipRoutes'] as $pattern => $response) {
            if (str_contains($request->url(), $pattern)) {
                return is_callable($response) ? $response($request) : $response;
            }
        }

        return Http::response(['errors' => [['message' => 'unexpected '.$request->url()]]], 404);
    });
    Notification::fake();

    app()->instance(IsInvoiceInEInvoiceScope::class, new class () extends IsInvoiceInEInvoiceScope {
        public function handle(Invoice $invoice): bool
        {
            return true;
        }
    });
    app()->instance(BuildUblInvoice::class, new class () extends BuildUblInvoice {
        public function handle(Invoice $invoice): string
        {
            return '<Invoice>'.$invoice->reference.'</Invoice>';
        }
    });

    setPermissionsTeamId($this->organisation->group_id);
    $this->accountsUser = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action(
        $this->organisation->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $this->accountsUser->givePermissionTo("accounting.{$this->organisation->id}.edit");
    $this->accountsUser->refresh();
});

function softipFake(array $routes): void
{
    $GLOBALS['softipRoutes'] = $routes;
}

test('a finalised invoice in scope is sent with the key, a single-use idempotency key, the file and the invoice tag, and the call is logged without the key', function () {
    softipFake(['/api/v1/document/send' => Http::response(['transactionId' => '9001', 'status' => 'NEW', 'checksum' => 'abc'])]);

    $transmission = SendEInvoice::make()->handle($this->invoice);

    expect($transmission->status)->toBe('NEW')
        ->and($transmission->transaction_id)->toBe('9001')
        ->and($transmission->checksum)->toBe('abc')
        ->and($transmission->xml)->toBe('<Invoice>'.$this->invoice->reference.'</Invoice>')
        ->and($transmission->tag)->toBe($this->invoice->uuid ?: 'aiku-invoice-'.$this->invoice->id)
        ->and($transmission->final_at)->toBeNull();

    Http::assertSent(function (Request $request) use ($transmission) {
        $parts = collect($request->data())->keyBy('name');

        return $request->hasHeader('X-API-KEY', config('services.softip.api_key'))
            && $request->hasHeader('Idempotency-Key', $transmission->idempotency_key)
            && $request->isMultipart()
            && $parts['File']['contents'] === $transmission->xml
            && $parts['Tag']['contents'] === $transmission->tag;
    });

    $logged = DB::table('e_invoice_api_calls')->where('path', '/api/v1/document/send')->latest('id')->first();
    expect($logged->response_status)->toBe(200)
        ->and($logged->request.$logged->response)->not->toContain(config('services.softip.api_key'))
        ->and(json_decode($logged->request, true)['idempotency_key'])->toBe($transmission->idempotency_key);

    expect(SendEInvoice::make()->handle($this->invoice->refresh()))->toBeNull()
        ->and($this->invoice->eInvoiceTransmissions()->count())->toBe(1);
});

test('an invoice the UBL builder refuses is kept as not sent, nothing goes to the provider and the accounts team is told once', function () {
    app()->instance(BuildUblInvoice::class, new class () extends BuildUblInvoice {
        public function handle(Invoice $invoice): string
        {
            throw new RuntimeException('Buyer has no Slovak VAT number to address it on Peppol');
        }
    });
    softipFake([]);
    setPermissionsTeamId(null);

    $transmission = SendEInvoice::make()->handle($this->invoice);

    expect($transmission->status)->toBe(InvoiceEInvoiceTransmission::NOT_BUILT)
        ->and($transmission->errorMessage())->toContain('no Slovak VAT number')
        ->and($transmission->final_at)->not->toBeNull();
    Http::assertNothingSent();
    Notification::assertSentToTimes($this->accountsUser, EInvoiceProblemNotification::class, 1);

    expect(InvoiceEInvoiceTransmission::summaryFor($this->invoice))->toMatchArray(['status' => 'NOT_BUILT', 'tone' => 'danger', 'attempts' => 1]);
});

test('a refused document is INVALID and a new attempt after it gets a new idempotency key', function () {
    softipFake(['/api/v1/document/send' => Http::response(['errors' => [['message' => 'BR-CO-15 total mismatch']]], 400)]);

    $first = SendEInvoice::make()->handle($this->invoice);
    expect($first->status)->toBe('INVALID')->and($first->errorMessage())->toContain('BR-CO-15');
    Notification::assertSentToTimes($this->accountsUser, EInvoiceProblemNotification::class, 1);

    softipFake(['/api/v1/document/send' => Http::response(['transactionId' => '9002', 'status' => 'NEW', 'checksum' => 'x'])]);
    $second = SendEInvoice::make()->handle($this->invoice->refresh());

    expect($second->transaction_id)->toBe('9002')
        ->and($second->idempotency_key)->not->toBe($first->idempotency_key);
});

test('a 409 after a lost answer recovers the transaction by the invoice tag, skipping transactions other attempts hold', function () {
    $tag = $this->invoice->uuid ?: 'aiku-invoice-'.$this->invoice->id;
    InvoiceEInvoiceTransmission::create([
        'organisation_id' => $this->organisation->id, 'invoice_id' => $this->invoice->id, 'provider' => 'softip',
        'idempotency_key' => (string)Str::uuid(), 'tag' => $tag, 'transaction_id' => '7000', 'status' => 'REJECTED', 'final_at' => now(), 'alerted_status' => 'REJECTED',
    ]);

    softipFake([
        '/api/v1/document/send'                => Http::response(['errors' => [['message' => 'Idempotency key already used']]], 409),
        '/api/v1/organization/logged-user'     => Http::response(['organizations' => [['id' => 55]], 'nextPageToken' => '']),
        '/api/v1/out-transaction/by-state/55'  => Http::response(['transactions' => [['id' => 7000, 'status' => 'REJECTED', 'tag' => $tag], ['id' => 7001, 'status' => 'SENT', 'tag' => $tag], ['id' => 7002, 'status' => 'SENT', 'tag' => 'another-invoice']], 'nextPageToken' => '']),
    ]);

    $transmission = SendEInvoice::make()->handle($this->invoice);

    expect($transmission->transaction_id)->toBe('7001')->and($transmission->status)->toBe('SENT');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'by-state/55') && str_contains($request->url(), 'tag='.urlencode($tag)));
});

test('a timeout keeps the row queued and polling resends it with the same key; delivery and refusal are polled, alerting once', function () {
    softipFake(['/api/v1/document/send' => Http::failedConnection('Operation timed out')]);
    $transmission = SendEInvoice::make()->handle($this->invoice);
    expect($transmission->status)->toBe(InvoiceEInvoiceTransmission::QUEUED)
        ->and($transmission->error['http_status'])->toBe(0)
        ->and($transmission->first_attempted_at)->not->toBeNull();
    Notification::assertNothingSent();

    $keys = [];
    softipFake([
        '/api/v1/document/send'        => function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];

            return Http::response(['transactionId' => '8001', 'status' => 'NEW', 'checksum' => 'c']);
        },
        '/api/v1/out-transaction/8001' => Http::response(['id' => 8001, 'status' => 'REJECTED', 'events' => [['message' => 'Buyer refused']]]),
        '/api/v1/organization/logged-user' => Http::response(['organizations' => [['id' => 56]], 'nextPageToken' => '']),
        '/api/v1/out-transaction/by-state/56' => Http::response(['transactions' => [], 'nextPageToken' => '']),
    ]);

    PollEInvoiceTransmissions::make()->handle();
    expect($keys)->toBe([$transmission->idempotency_key])
        ->and($transmission->refresh()->transaction_id)->toBe('8001');

    PollEInvoiceTransmissions::make()->handle();
    PollEInvoiceTransmissions::make()->handle();

    expect($transmission->refresh()->status)->toBe('REJECTED')->and($transmission->final_at)->not->toBeNull();
    Notification::assertSentToTimes($this->accountsUser, EInvoiceProblemNotification::class, 1);
});

test('without the API key nothing is sent or alerted; the queued invoice goes out once the key is set', function () {
    config(['services.softip.api_key' => null]);
    softipFake([]);

    $transmission = SendEInvoice::make()->handle($this->invoice);
    PollEInvoiceTransmissions::make()->handle();

    expect($transmission->refresh()->status)->toBe(InvoiceEInvoiceTransmission::QUEUED);
    Http::assertNothingSent();
    Notification::assertNothingSent();

    $transmission->forceFill(['created_at' => now()->subDays(10)])->save();
    app()->instance(BuildUblInvoice::class, new class () extends BuildUblInvoice {
        public function handle(Invoice $invoice): string
        {
            return '<Invoice>edited while waiting</Invoice>';
        }
    });
    config(['services.softip.api_key' => 'now-set']);
    softipFake(['/api/v1/document/send' => Http::response(['transactionId' => '9100', 'status' => 'NEW', 'checksum' => 'd'])]);
    PollEInvoiceTransmissions::make()->handle();

    expect($transmission->refresh()->xml)->toBe('<Invoice>edited while waiting</Invoice>')
        ->and($transmission->status)->toBe('NEW');
    Notification::assertNothingSent();

    softipFake(['/api/v1/out-transaction/9100' => Http::response(['id' => 9100, 'status' => 'DELIVERED'])]);
    PollEInvoiceTransmissions::make()->handle();

    expect($transmission->refresh()->status)->toBe('DELIVERED')
        ->and(InvoiceEInvoiceTransmission::summaryFor($this->invoice))->toMatchArray(['tone' => 'success', 'transaction_id' => '9100']);
});

test('invoices out of scope are not sent and the job is queued only for organisations that e-invoice', function () {
    app()->forgetInstance(IsInvoiceInEInvoiceScope::class);
    softipFake([]);

    expect(SendEInvoice::make()->handle($this->invoice))->toBeNull();
    Http::assertNothingSent();

    Queue::fake();
    SendEInvoice::dispatchIfEnabled($this->invoice);
    Queue::assertNothingPushed();

    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['e_invoicing' => ['countries' => ['SK']]])]);
    SendEInvoice::dispatchIfEnabled($this->invoice->refresh());
    Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof SendEInvoice);
});

test('a transmission still not final after three days is reported once as stuck', function () {
    softipFake([
        '/api/v1/document/send'        => Http::response(['transactionId' => '8500', 'status' => 'SENT_WITHOUT_RESPONSE', 'checksum' => 'c']),
        '/api/v1/out-transaction/8500' => Http::response(['id' => 8500, 'status' => 'SENT_WITHOUT_RESPONSE']),
    ]);
    $transmission = SendEInvoice::make()->handle($this->invoice);
    $transmission->forceFill(['created_at' => now()->subDays(4), 'accepted_at' => now()->subDays(4)])->save();

    PollEInvoiceTransmissions::make()->handle();
    PollEInvoiceTransmissions::make()->handle();

    expect($transmission->refresh()->alerted_status)->toBe('STUCK')->and($transmission->final_at)->toBeNull();
    Notification::assertSentToTimes($this->accountsUser, EInvoiceProblemNotification::class, 1);
});

test('a retry after a lost answer adopts the transaction found under the tag instead of sending again, since keys expire after 24 hours', function () {
    softipFake(['/api/v1/document/send' => Http::failedConnection()]);
    $transmission = SendEInvoice::make()->handle($this->invoice);

    $sends = 0;
    softipFake([
        '/api/v1/document/send'               => function () use (&$sends) {
            $sends++;

            return Http::response(['transactionId' => '9999', 'status' => 'NEW', 'checksum' => 'x']);
        },
        '/api/v1/organization/logged-user'    => Http::response(['organizations' => [['id' => 57]], 'nextPageToken' => '']),
        '/api/v1/out-transaction/by-state/57' => Http::response(['transactions' => [['id' => 8800, 'status' => 'SENT', 'tag' => $transmission->tag]], 'nextPageToken' => '']),
    ]);
    SendEInvoice::make()->transmit($transmission->refresh());

    expect($transmission->refresh()->transaction_id)->toBe('8800')
        ->and($transmission->status)->toBe('SENT')
        ->and($sends)->toBe(0);
});

test('an invoice SOFTIP accepted can no longer be changed or deleted, but one it refused as invalid can', function () {
    $this->invoice->update(['address_id' => $this->customer->address_id]);
    softipFake(['/api/v1/document/send' => Http::response(['transactionId' => '9101', 'status' => 'NEW', 'checksum' => 'abc'])]);
    $transmission = SendEInvoice::make()->handle($this->invoice);
    $invoice      = $this->invoice->fresh();

    \App\Actions\Accounting\Invoice\UpdateInvoice::make()->action($invoice, ['footer' => 'Thank you']);
    expect($invoice->fresh()->footer)->toBe('Thank you');

    \App\Actions\Accounting\EInvoice\EnsureInvoiceNotSentAsEInvoice::run($invoice, ['net_amount' => $invoice->net_amount, 'reference' => $invoice->reference]);

    expect(fn () => \App\Actions\Accounting\Invoice\UpdateInvoice::make()->action($invoice->fresh(), ['reference' => $invoice->reference.'-X']))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => \App\Actions\Accounting\Invoice\UpdateInvoiceDate::run($invoice->fresh(), ['date' => now()->subDay()]))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => \App\Actions\Accounting\Invoice\DeleteInvoice::run($invoice->fresh(), ['deleted_note' => 'test']))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($invoice->fresh()->reference)->toBe($invoice->reference)
        ->and($invoice->fresh()->deleted_at)->toBeNull();

    $transmission->update(['status' => 'QUEUED', 'transaction_id' => null, 'first_attempted_at' => now()]);
    expect(\App\Actions\Accounting\EInvoice\EnsureInvoiceNotSentAsEInvoice::isSent($invoice))->toBeTrue()
        ->and(fn () => \App\Actions\Accounting\Invoice\UpdateInvoice::make()->action($invoice->fresh(), ['is_re' => !$invoice->is_re]))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    $transmission->update(['status' => 'INVALID', 'transaction_id' => '9101']);
    expect(\App\Actions\Accounting\EInvoice\EnsureInvoiceNotSentAsEInvoice::isSent($invoice))->toBeFalse();
});

test('Slovak business customers without a Peppol ID or a validated VAT number are listed, and can give their DIČ in Retina', function () {
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['e_invoicing' => ['countries' => ['SK']]])]);
    $this->invoice->update(['address_id' => $this->customer->address_id]);
    $this->invoice->refresh()->address->update(['country_code' => 'SK']);
    $this->invoice->update(['tax_number' => null, 'tax_number_valid' => null, 'identity_document_number' => '54018579']);
    $this->customer->address->update(['country_code' => 'SK']);
    $this->customer->taxNumber?->delete();
    $this->customer->update(['company_name' => 'Obchod s.r.o.']);
    $customer = $this->customer->fresh();

    $listed = fn () => \App\Actions\Accounting\EInvoice\UI\IndexCustomersMissingPeppolId::make()->handle($this->organisation->fresh())->getCollection()->pluck('id');
    expect($listed())->toContain($customer->id)
        ->and(\App\Actions\Retina\SysAdmin\UpdateRetinaCustomer::needsPeppolDic($customer))->toBeTrue();

    \App\Actions\Retina\SysAdmin\UpdateRetinaCustomer::make()->handle($customer, ['peppol_dic' => '2121551498']);

    expect($customer->fresh()->peppol_id)->toBe('0245:2121551498')
        ->and($listed())->not->toContain($customer->id);

    expect(fn () => \App\Actions\CRM\Customer\UpdateCustomer::make()->action($customer->fresh(), ['peppol_id' => '0245:2121551499']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

test('a transaction listed under another tag is never adopted as this invoice\'s', function () {
    softipFake(['/api/v1/document/send' => Http::failedConnection()]);
    $transmission = SendEInvoice::make()->handle($this->invoice);

    softipFake([
        '/api/v1/document/send'               => Http::response(['transactionId' => '9998', 'status' => 'NEW', 'checksum' => 'x']),
        '/api/v1/organization/logged-user'    => Http::response(['organizations' => [['id' => 58]], 'nextPageToken' => '']),
        '/api/v1/out-transaction/by-state/58' => Http::response(['transactions' => [['id' => 8801, 'status' => 'DELIVERED', 'tag' => 'another-invoice']], 'nextPageToken' => '']),
    ]);
    SendEInvoice::make()->transmit($transmission->refresh());

    expect($transmission->refresh()->transaction_id)->toBe('9998');
});

test('a send the provider has not accepted a day after it was queued is reported once, even without the API key', function () {
    config(['services.softip.api_key' => null]);
    softipFake([]);
    $transmission = SendEInvoice::make()->handle($this->invoice);
    $transmission->forceFill(['created_at' => now()->subHours(25)])->save();

    PollEInvoiceTransmissions::make()->handle();
    PollEInvoiceTransmissions::make()->handle();

    expect($transmission->refresh()->status)->toBe(InvoiceEInvoiceTransmission::QUEUED)
        ->and($transmission->alerted_status)->toBe('UNSENT');
    Notification::assertSentToTimes($this->accountsUser, EInvoiceProblemNotification::class, 1);
});

test('an unexpected error while writing the e-invoice is kept as not built and the accounts team is told', function () {
    app()->instance(BuildUblInvoice::class, new class () extends BuildUblInvoice {
        public function handle(Invoice $invoice): string
        {
            throw new TypeError('unexpected');
        }
    });
    softipFake([]);

    $transmission = SendEInvoice::make()->handle($this->invoice);

    expect($transmission->status)->toBe(InvoiceEInvoiceTransmission::NOT_BUILT)
        ->and($transmission->errorMessage())->toContain('unexpected');
    Http::assertNothingSent();
    Notification::assertSentToTimes($this->accountsUser, EInvoiceProblemNotification::class, 1);
});

test('staff send a refused e-invoice again from the invoice page once it is fixed', function () {
    app()->instance(BuildUblInvoice::class, new class () extends BuildUblInvoice {
        public function handle(Invoice $invoice): string
        {
            throw new RuntimeException('Buyer has no Slovak VAT number');
        }
    });
    softipFake([]);
    SendEInvoice::make()->handle($this->invoice);
    $summary = InvoiceEInvoiceTransmission::summaryFor($this->invoice);
    expect($summary['send_route']['name'])->toBe('grp.models.invoice.e_invoice.send');

    app()->instance(BuildUblInvoice::class, new class () extends BuildUblInvoice {
        public function handle(Invoice $invoice): string
        {
            return '<Invoice>fixed</Invoice>';
        }
    });
    softipFake(['/api/v1/document/send' => Http::response(['transactionId' => '9200', 'status' => 'NEW', 'checksum' => 'e'])]);

    $this->actingAs($this->accountsUser)
        ->post(route($summary['send_route']['name'], $summary['send_route']['parameters']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(InvoiceEInvoiceTransmission::summaryFor($this->invoice))->toMatchArray(['status' => 'NEW', 'attempts' => 2, 'send_route' => null]);

    $this->actingAs($this->accountsUser)
        ->post(route($summary['send_route']['name'], $summary['send_route']['parameters']))
        ->assertSessionHasErrors('e_invoice');
});

test('a sent invoice cannot be deleted as in process, nor its lines or refund lines changed', function () {
    softipFake(['/api/v1/document/send' => Http::response(['transactionId' => '9300', 'status' => 'NEW', 'checksum' => 'f'])]);
    SendEInvoice::make()->handle($this->invoice);
    $invoice = $this->invoice->fresh();
    $line    = \App\Models\Accounting\InvoiceTransaction::forceCreate([
        'group_id' => $invoice->group_id, 'organisation_id' => $invoice->organisation_id, 'shop_id' => $invoice->shop_id,
        'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'tax_category_id' => $invoice->tax_category_id,
        'date' => $invoice->date, 'quantity' => 1, 'net_amount' => 100, 'gross_amount' => 100, 'in_process' => false,
    ]);

    expect(fn () => \App\Actions\Accounting\Invoice\DeleteInProcessInvoice::make()->action($invoice, ['deleted_note' => 'test']))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => \App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction::run($line))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => \App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction::run($line, ['net_amount' => 1]))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => \App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction::make()->handle($invoice, $line, ['net_amount' => 1]))
        ->toThrow(Illuminate\Validation\ValidationException::class)
        ->and($invoice->fresh()->deleted_at)->toBeNull()
        ->and($line->fresh()->net_amount)->toBe($line->net_amount);

    \App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction::run($line, ['data' => ['note' => 'ok']]);
});

test('changing one invoice\'s billing address leaves the address other invoices share untouched', function () {
    $shared = $this->customer->address;
    createInvoiceFor($this->customer, $this->shop, now()->toDateString(), 50);
    $other = Invoice::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    $this->invoice->update(['address_id' => $shared->id]);
    $other->update(['address_id' => $shared->id]);
    $before = $shared->only(['address_line_1', 'locality', 'postal_code']);

    \App\Actions\Accounting\Invoice\UpdateInvoice::make()->action($this->invoice->fresh(), [
        'invoice_billing_address' => array_merge(\Illuminate\Support\Arr::only($shared->toArray(), ['address_line_2', 'postal_code', 'locality', 'administrative_area', 'dependent_locality', 'sorting_code', 'country_id', 'country_code']), ['address_line_1' => 'Hlavná 99']),
    ]);

    expect($this->invoice->fresh()->address->address_line_1)->toBe('Hlavná 99')
        ->and($this->invoice->fresh()->address_id)->not->toBe($shared->id)
        ->and($other->fresh()->address_id)->toBe($shared->id)
        ->and($shared->fresh()->only(['address_line_1', 'locality', 'postal_code']))->toBe($before);
});

test('fulfilment invoices, completed standalone or consolidated from a recurring bill, are sent too', function () {
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['e_invoicing' => ['countries' => ['SK']]])]);
    $isSendJob = fn ($job) => $job->getAction() instanceof SendEInvoice;

    Queue::fake();
    $recurringBill = new \App\Models\Fulfilment\RecurringBill();
    $recurringBill->setRelation('transactions', new \Illuminate\Database\Eloquent\Collection());
    \App\Actions\Accounting\Invoice\InvoiceRecurringBillTransactions::run($this->invoice->refresh(), $recurringBill);
    Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, $isSendJob);

    Queue::fake();
    createInvoiceFor($this->customer, $this->shop, now()->toDateString(), 20, inProcess: true);
    $standalone = Invoice::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    \App\Actions\Accounting\StandaloneFulfilmentInvoice\CompleteStandaloneFulfilmentInvoice::run($standalone);
    Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, $isSendJob);
});
