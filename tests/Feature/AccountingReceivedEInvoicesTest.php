<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\EInvoice\FetchReceivedEInvoices;
use App\Actions\Accounting\EInvoice\MatchReceivedEInvoice;
use App\Actions\Accounting\EInvoice\ReadUblDocument;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Enums\Accounting\ReceivedEInvoice\ReceivedEInvoiceMatchStateEnum;
use App\Models\Accounting\ReceivedEInvoice;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->organisation->update(['settings' => array_merge($this->organisation->settings ?? [], ['e_invoicing' => ['countries' => ['SK']]])]);

    config([
        'inertia.pages.paths'             => [resource_path('js/Pages/Grp')],
        'services.softip.url'             => 'https://softip.test',
        'services.softip.api_key'         => 'secret-key-123',
        'services.softip.organization_id' => '777',
    ]);

    $this->transactionId = (string)random_int(1_000_000, 9_000_000);
    $this->pdfStatus     = 200;
    $this->acknowledged  = [];
    $this->xml           = file_get_contents(base_path('tests/fixtures/peppol/sk-invoice.xml'));
    $this->pdf           = "%PDF-1.7\n\x00\xff\xfe binary\n%%EOF";

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $path === '/api/v1/in-transaction/by-status/777' => Http::response(['transactions' => [[
                'id'             => (int)$this->transactionId,
                'state'          => 'NEW',
                'receivedAt'     => '2027-01-15T10:00:00Z',
                'senderName'     => 'OBALY Trnava s.r.o.',
                'senderId'       => '0245:2022405020',
                'transmissionId' => 'tx-'.$this->transactionId,
            ]]]),
            $path === "/api/v1/in-transaction/download-doc/$this->transactionId" => Http::response($this->xml, 200, ['Content-Type' => 'application/xml']),
            $path === "/api/v1/in-transaction/download-pdf/$this->transactionId" => Http::response($this->pdfStatus === 200 ? $this->pdf : '', $this->pdfStatus, ['Content-Type' => 'application/pdf']),
            $path === "/api/v1/in-transaction/acknowledge/$this->transactionId" => (function () {
                $this->acknowledged[] = $this->transactionId;

                return Http::response(['id' => (int)$this->transactionId, 'state' => 'ACKNOWLEDGED']);
            })(),
            default => Http::response('not found', 404),
        };
    });
});

test('an empty or broken download is never stored or acknowledged', function () {
    $this->xml = '';
    FetchReceivedEInvoices::run($this->organisation);
    expect(ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail()->hasXml())->toBeFalse();

    $this->xml = file_get_contents(base_path('tests/fixtures/peppol/sk-invoice.xml'));
    $this->pdf = '<html>error</html>';
    FetchReceivedEInvoices::run($this->organisation);

    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    expect($received->hasXml())->toBeTrue()
        ->and($received->hasPdf())->toBeFalse()
        ->and($received->acknowledged_at)->toBeNull()
        ->and($this->acknowledged)->toBe([]);
});

test('peppol bis 3 examples are read', function () {
    $read = fn (string $file) => ReadUblDocument::run(file_get_contents(base_path("tests/fixtures/peppol/$file")));

    expect($read('base-example.xml'))->toMatchArray([
        'document_type'       => 'invoice',
        'document_number'     => 'Snippet1',
        'issue_date'          => '2017-11-13',
        'due_date'            => '2017-12-01',
        'currency_code'       => 'EUR',
        'net_amount'          => '1325',
        'tax_amount'          => '331.25',
        'total_amount'        => '1656.25',
        'supplier_name'       => 'SupplierOfficialName Ltd',
        'supplier_peppol_id'  => '0088:9482348239847239874',
        'supplier_vat_number' => 'GB1232434',
        'buyer_reference'     => '0150abc',
        'order_reference'     => null,
    ])
        ->and($read('base-creditnote-correction.xml'))->toMatchArray([
            'document_type'     => 'credit_note',
            'billing_reference' => 'Snippet1',
        ])
        ->and($read('Allowance-example.xml')['tax_amount'])->toBe('1225.00')
        ->and($read('sales-order-example.xml')['order_reference'])->toBeNull()
        ->and($read('sk-invoice.xml'))->toMatchArray([
            'supplier_peppol_id'      => '0245:2022405020',
            'supplier_vat_number'     => 'SK2022405020',
            'supplier_tax_number'     => '2022405020',
            'supplier_company_number' => '36231401',
            'supplier_name'           => 'OBALY Trnava s.r.o.',
            'order_reference'         => 'SK-PO-0042',
            'total_amount'            => '246.00',
        ]);

    expect(fn () => ReadUblDocument::run('<Order xmlns="urn:x"/>'))->toThrow(RuntimeException::class)
        ->and(fn () => ReadUblDocument::run('not xml'))->toThrow(RuntimeException::class);
});

test('a received e-invoice is stored unchanged, read, suggested and only then acknowledged', function () {
    $supplier = StoreSupplier::make()->action(parent: $this->organisation->group, modelData: array_merge(Supplier::factory()->definition(), ['name' => 'OBALY Trnava s.r.o', 'company_name' => 'OBALY Trnava s.r.o']));

    $stats = FetchReceivedEInvoices::run($this->organisation);

    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    expect($stats)->toMatchArray(['received' => 1, 'acknowledged' => 1, 'failed' => 0])
        ->and($received->xmlContent())->toBe($this->xml)
        ->and($received->xml_sha256)->toBe(hash('sha256', $this->xml))
        ->and($received->pdfContent())->toBe($this->pdf)
        ->and($received->document_number)->toBe('FA2027000123')
        ->and($received->total_amount)->toBe('246.00')
        ->and($received->match_state)->toBe(ReceivedEInvoiceMatchStateEnum::UNMATCHED)
        ->and($received->match_data['suggestion'])->toMatchArray(['supplier_id' => $supplier->id, 'supplier_reason' => 'name'])
        ->and($received->acknowledged_at)->not->toBeNull()
        ->and($this->acknowledged)->toBe([$this->transactionId]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/by-status/777?status=NEW&status=READ&status=DOWNLOADED&limit=100')
        && $request->hasHeader('X-API-KEY', 'secret-key-123'));

    $logged = DB::table('e_invoice_api_calls')->where('path', 'like', "%$this->transactionId%")->get();
    expect($logged->pluck('response')->implode(' '))->not->toContain('secret-key-123')
        ->and($logged->firstWhere('path', "/api/v1/in-transaction/download-pdf/$this->transactionId")->response)->toContain(hash('sha256', $this->pdf));
});

test('a pdf that fails is not acknowledged, and the next run resumes the same row without touching the xml', function () {
    $this->pdfStatus = 503;

    expect(FetchReceivedEInvoices::run($this->organisation))->toMatchArray(['failed' => 1, 'acknowledged' => 0])
        ->and($this->acknowledged)->toBe([]);
    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    expect($received->hasXml())->toBeTrue()->and($received->hasPdf())->toBeFalse();

    $this->pdfStatus = 200;
    $this->xml       = '<changed/>';
    FetchReceivedEInvoices::run($this->organisation);

    $received->refresh();
    expect(ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->count())->toBe(1)
        ->and($received->xmlContent())->toBe(file_get_contents(base_path('tests/fixtures/peppol/sk-invoice.xml')))
        ->and($received->pdfContent())->toBe($this->pdf)
        ->and($received->acknowledged_at)->not->toBeNull()
        ->and($this->acknowledged)->toBe([$this->transactionId]);
});

test('a document that cannot be read is kept and acknowledged with the reason', function () {
    $this->xml = '<?xml version="1.0"?><Invoice xmlns="urn:not-ubl"/>';

    FetchReceivedEInvoices::run($this->organisation);

    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    expect($received->parse_error)->toContain('No UBL Invoice')
        ->and($received->xmlContent())->toBe($this->xml)
        ->and($received->acknowledged_at)->not->toBeNull();
});

test('staff match a received e-invoice to a supplier of the organisation, or set it aside', function () {
    FetchReceivedEInvoices::run($this->organisation);
    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    $supplier = StoreSupplier::make()->action(parent: $this->organisation->group, modelData: Supplier::factory()->definition());

    expect(fn () => MatchReceivedEInvoice::make()->action($received, ['match_state' => 'matched']))->toThrow(ValidationException::class)
        ->and(fn () => MatchReceivedEInvoice::make()->action($received, ['match_state' => 'matched', 'supplier_id' => $supplier->id, 'purchase_order_reference' => 'NO-SUCH-PO']))->toThrow(ValidationException::class);

    MatchReceivedEInvoice::make()->action($received, ['match_state' => 'matched', 'supplier_id' => $supplier->id]);
    expect($received->refresh()->match_state)->toBe(ReceivedEInvoiceMatchStateEnum::MATCHED)->and($received->supplier_id)->toBe($supplier->id);

    MatchReceivedEInvoice::make()->action($received, ['match_state' => 'ignored', 'note' => 'rent']);
    expect($received->refresh()->match_state)->toBe(ReceivedEInvoiceMatchStateEnum::IGNORED)
        ->and($received->supplier_id)->toBeNull()
        ->and($received->match_data['decided_note'])->toBe('rent');
});

test('the accounts team lists received e-invoices and downloads them as received', function () {
    FetchReceivedEInvoices::run($this->organisation);
    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    actingAs(createAdminGuest($this->organisation->group)->getUser());

    get(route('grp.org.accounting.received_e_invoices.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Accounting/ReceivedEInvoices')
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $received->id && $row['document_number'] === 'FA2027000123' && $row['pdf_url'] !== null)));

    $xml = get(route('grp.org.accounting.received_e_invoices.xml', [$this->organisation->slug, $received->id]))->assertOk();
    expect($xml->getContent())->toBe($this->xml);
    $pdf = get(route('grp.org.accounting.received_e_invoices.pdf', [$this->organisation->slug, $received->id]))->assertOk();
    expect($pdf->getContent())->toBe($this->pdf)->and($pdf->headers->get('Content-Type'))->toBe('application/pdf');
});

test('only an organisation that e-invoices fetches, and an answer that is not an invoice or credit note is never stored or acknowledged', function () {
    $settings = $this->organisation->settings;
    $this->organisation->update(['settings' => array_merge($settings, ['e_invoicing' => ['countries' => []]])]);
    expect(FetchReceivedEInvoices::run($this->organisation))->toBeNull()
        ->and(ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->exists())->toBeFalse();
    $this->organisation->update(['settings' => $settings]);

    $this->xml = '<?xml version="1.0"?><error><message>Service unavailable</message></error>';
    expect(FetchReceivedEInvoices::run($this->organisation))->toMatchArray(['failed' => 1, 'acknowledged' => 0]);

    expect(ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail()->hasXml())->toBeFalse()
        ->and($this->acknowledged)->toBe([]);
});

test('a document whose PDF never comes is acknowledged a day later without it', function () {
    $this->pdfStatus = 404;
    FetchReceivedEInvoices::run($this->organisation);
    $received = ReceivedEInvoice::where('provider_transaction_id', $this->transactionId)->firstOrFail();
    expect($received->acknowledged_at)->toBeNull();

    $received->forceFill(['created_at' => now()->subDays(2)])->save();
    FetchReceivedEInvoices::run($this->organisation);

    expect($received->refresh()->acknowledged_at)->not->toBeNull()
        ->and($received->hasPdf())->toBeFalse()
        ->and($this->acknowledged)->toBe([$this->transactionId]);
});
