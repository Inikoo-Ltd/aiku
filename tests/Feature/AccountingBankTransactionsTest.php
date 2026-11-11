<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\BankConnection\StoreBankConnection;
use App\Actions\Accounting\BankConnection\SyncBankConnection;
use App\Actions\Accounting\BankConnection\Wise\SyncWiseBankConnection;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Enums\Accounting\BankConnection\BankConnectionStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankTransaction;
use Illuminate\Support\Facades\Http;

uses()->group('base');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation) = createShop();
});

function wiseActivity(string $id, string $type, string $amount, string $title, string $status = 'COMPLETED', ?string $resourceId = null, string $updatedOn = '2026-11-10T10:05:00.071Z'): array
{
    return [
        'id'              => $id,
        'type'            => $type,
        'resource'        => ['type' => $type === 'TRANSFER' ? 'TRANSFER' : 'CARD_TRANSACTION', 'id' => $resourceId ?? $id],
        'title'           => "<strong>$title</strong>",
        'description'     => '',
        'primaryAmount'   => $amount,
        'secondaryAmount' => '',
        'status'          => $status,
        'createdOn'       => '2026-11-10T10:00:00.483Z',
        'updatedOn'       => $updatedOn,
    ];
}

test('wise display amounts are parsed strictly', function () {
    $parser = SyncWiseBankConnection::make();

    expect($parser->parseAmount('<positive>+ 1,312,516 HUF</positive>'))->toBe([true, 1312516.0, 'HUF'])
        ->and($parser->parseAmount('11.99 EUR'))->toBe([false, 11.99, 'EUR'])
        ->and($parser->parseAmount('<strikethrough>99.50 GBP</strikethrough>'))->toBe([false, 99.5, 'GBP'])
        ->and($parser->parseAmount(''))->toBeNull()
        ->and($parser->parseAmount("1\u{00A0}312\u{00A0}516 HUF"))->toBeNull()
        ->and($parser->parseAmount('1.312.516,00 EUR'))->toBeNull();
});

test('wise activities become the lines they moved on our balances', function () {
    $parser = SyncWiseBankConnection::make();
    $activity = fn (string $type, string $primary, string $secondary) => ['type' => $type, 'primaryAmount' => $primary, 'secondaryAmount' => $secondary];

    expect($parser->legs($activity('TRANSFER', '834,739.27 INR', '8,000 EUR')))
        ->toBe(['' => [BankTransactionDirectionEnum::OUT, 8000.0, 'EUR']])
        ->and($parser->legs($activity('TRANSFER', '<positive>+ 44,509.17 CZK</positive>', '44,604.47 CZK')))
        ->toBe(['' => [BankTransactionDirectionEnum::IN, 44509.17, 'CZK']])
        ->and($parser->legs($activity('CARD_PAYMENT', '11.99 EUR', '')))
        ->toBe(['' => [BankTransactionDirectionEnum::OUT, 11.99, 'EUR']])
        ->and($parser->legs($activity('INTERBALANCE', '20,000 USD', '6,498,489 HUF')))
        ->toBe([
            ''    => [BankTransactionDirectionEnum::OUT, 6498489.0, 'HUF'],
            '#in' => [BankTransactionDirectionEnum::IN, 20000.0, 'USD'],
        ]);
});

test('a wise connection mirrors every activity once, with transfer references', function () {
    Http::fake([
        'api.wise.com/v2/profiles'            => Http::response([
            ['id' => 111, 'type' => 'PERSONAL'],
            ['id' => 222, 'type' => 'BUSINESS'],
        ]),
        'api.wise.com/v1/profiles/222/activities*' => Http::sequence()
            ->push(['cursor' => 'next', 'activities' => [
                wiseActivity('a1', 'TRANSFER', '<positive>+ 577.30 PLN</positive>', 'Jan Kowalski', resourceId: '9001'),
                wiseActivity('a2', 'CARD_PAYMENT', '11.99 EUR', 'Shop &amp; Co'),
            ]])
            ->push(['cursor' => null, 'activities' => [
                wiseActivity('a3', 'TRANSFER', '250.00 EUR', 'Supplier Ltd', 'CANCELLED', '9002'),
            ]])
            ->push(['cursor' => null, 'activities' => [
                wiseActivity('a1', 'TRANSFER', '<positive>+ 577.30 PLN</positive>', 'Jan Kowalski', resourceId: '9001'),
            ]])
            ->push(['cursor' => null, 'activities' => [
                wiseActivity('a3', 'TRANSFER', '250.00 EUR', 'Supplier Ltd', 'COMPLETED', '9002', '2026-11-11T08:00:00.312Z'),
                wiseActivity('a4', 'TRANSFER', '<positive>+ 10.00 XYZ</positive>', 'Unknown', resourceId: '9003'),
            ]]),
        'api.wise.com/v1/transfers/9001'      => Http::response(['id' => 9001, 'reference' => 'PL31515']),
        'api.wise.com/v1/transfers/9002'      => Http::response(['errors' => [['code' => 'forbidden.error']]], 403),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise SK',
        'credentials' => 'test-token',
    ]);

    expect($bankConnection->external_id)->toBe('222')
        ->and($bankConnection->getRawOriginal('credentials'))->not->toBe('test-token');

    expect(SyncBankConnection::run($bankConnection, true))->toBe(['synced' => 3, 'skipped' => 0, 'skipped_examples' => []]);

    $lines = BankTransaction::where('bank_connection_id', $bankConnection->id)->get()->keyBy('provider_transaction_id');

    expect($lines)->toHaveCount(3)
        ->and($lines['a1']->direction)->toBe(BankTransactionDirectionEnum::IN)
        ->and((float)$lines['a1']->amount)->toBe(577.3)
        ->and($lines['a1']->currency->code)->toBe('PLN')
        ->and($lines['a1']->reference)->toBe('PL31515')
        ->and($lines['a1']->counterparty_name)->toBe('Jan Kowalski')
        ->and($lines['a1']->status)->toBe(BankTransactionStatusEnum::BOOKED)
        ->and((float)$lines['a2']->amount)->toBe(-11.99)
        ->and($lines['a2']->counterparty_name)->toBe('Shop & Co')
        ->and($lines['a3']->status)->toBe(BankTransactionStatusEnum::CANCELLED)
        ->and($lines['a3']->reference)->toBeNull();

    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 0, 'skipped' => 0, 'skipped_examples' => []])
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(3)
        ->and($bankConnection->refresh()->state)->toBe(BankConnectionStateEnum::ACTIVE);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/activities') && str_contains($request->url(), 'since='));

    expect(SyncBankConnection::run($bankConnection->refresh()))->toBe(['synced' => 1, 'skipped' => 1, 'skipped_examples' => ['a4: unknown currency: XYZ']])
        ->and($lines['a3']->refresh()->status)->toBe(BankTransactionStatusEnum::BOOKED)
        ->and(BankTransaction::where('bank_connection_id', $bankConnection->id)->count())->toBe(3);
});

test('the scheduled sync leaves connections that were never backfilled alone', function () {
    Http::fake([
        'api.wise.com/v2/profiles' => Http::response([['id' => 444, 'type' => 'BUSINESS']]),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise new',
        'credentials' => 'new-token',
    ]);

    $this->artisan('bank-connections:sync')->run();

    expect($bankConnection->refresh()->last_synced_at)->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/profiles/444/'));
});

test('a failing bank sync marks the connection as error', function () {
    Http::fake([
        'api.wise.com/v2/profiles'  => Http::response([['id' => 333, 'type' => 'BUSINESS']]),
        'api.wise.com/v1/profiles/*' => Http::response(['error' => 'invalid_token'], 401),
    ]);

    $bankConnection = StoreBankConnection::run($this->organisation, [
        'provider'    => BankConnectionProviderEnum::WISE,
        'name'        => 'Wise broken',
        'credentials' => 'bad-token',
    ]);

    expect(SyncBankConnection::run($bankConnection))->toBeNull()
        ->and($bankConnection->refresh()->state)->toBe(BankConnectionStateEnum::ERROR)
        ->and($bankConnection->last_error)->not->toBeNull();
});
