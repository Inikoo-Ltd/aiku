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
        '/api/v1/out-transaction/by-state/55'  => Http::response(['transactions' => [['id' => 7000, 'status' => 'REJECTED'], ['id' => 7001, 'status' => 'SENT']], 'nextPageToken' => '']),
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
        '/api/v1/out-transaction/by-state/57' => Http::response(['transactions' => [['id' => 8800, 'status' => 'SENT']], 'nextPageToken' => '']),
    ]);
    SendEInvoice::make()->transmit($transmission->refresh());

    expect($transmission->refresh()->transaction_id)->toBe('8800')
        ->and($transmission->status)->toBe('SENT')
        ->and($sends)->toBe(0);
});
