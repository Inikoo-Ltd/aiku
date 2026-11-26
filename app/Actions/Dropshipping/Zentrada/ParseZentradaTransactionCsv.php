<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Zentrada;

use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * The transaction_<id>.csv TradeSafe attaches to a "Delivery order transaction" email: semicolon
 * separated, one row per order line, Windows-1252 rather than UTF-8, decimal commas and some prices
 * followed by " EUR". The delivery address columns M01 to M11 are told apart by their German names.
 */
class ParseZentradaTransactionCsv
{
    use AsObject;

    private const array ADDRESS_FIELDS = [
        'company'   => ['FIRMA', 'COMPANY'],
        'firstName' => ['VORNAME', 'FIRSTNAME', 'FIRST_NAME'],
        'name'      => ['NACHNAME', 'LASTNAME', 'LAST_NAME', 'NAME'],
        'street'    => ['STRASSE', 'STRAßE', 'STREET'],
        'number'    => ['HAUSNR', 'HAUSNUMMER', 'HOUSE'],
        'line2'     => ['ZUSATZ', 'ADDITION', 'LINE2'],
        'postal'    => ['PLZ', 'ZIP', 'POSTAL', 'POSTLEITZAHL'],
        'city'      => ['ORT', 'STADT', 'CITY'],
        'country'   => ['LAND', 'COUNTRY'],
        'phone'     => ['TELEFON', 'TEL', 'PHONE'],
        'email'     => ['EMAIL', 'E_MAIL', 'MAIL'],
    ];

    /**
     * @return array{transaction: string, currency: string, freight: float, vat_number: string, address: array<string, string>, lines: list<array{code: string, packs: float, pieces: ?float, piece_price: ?float, amount: float, ean: string}>}
     */
    public function handle(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        if (!mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }

        $rows = array_values(array_filter(
            array_map(fn (string $line) => str_getcsv($line, ';', '"', ''), preg_split('/\R/', trim($csv))),
            fn (array $row) => count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0
        ));

        if (count($rows) < 2) {
            throw new RuntimeException('the CSV has no order lines');
        }

        $header = array_map(fn ($column) => strtoupper(trim((string) $column)), array_shift($rows));
        foreach (['A_TRANSAKTIONID', 'G_ARTIKEL_NR', 'D_MENGE_VE'] as $required) {
            if (!in_array($required, $header, true)) {
                throw new RuntimeException("the CSV has no $required column");
            }
        }

        $records = array_map(function (array $row) use ($header) {
            $row = array_pad(array_slice($row, 0, count($header)), count($header), '');

            return array_combine($header, array_map(fn ($value) => trim((string) $value), $row));
        }, $rows);

        $first = $records[0];

        return [
            'transaction' => $first['A_TRANSAKTIONID'],
            'currency'    => strtoupper($this->firstFilled($records, 'F3_WAEHRUNG') ?: 'EUR'),
            'freight'     => (float) $this->number($this->firstFilled($records, 'O_FREIGHT')),
            'vat_number'  => (string) $this->firstFilled($records, 'N_UID_ZE'),
            'address'     => $this->address($header, $first),
            'lines'       => array_map(fn (array $record) => $this->line($record), $records),
        ];
    }

    /**
     * @param array<string, string> $record
     *
     * @return array{code: string, packs: float, pieces: ?float, piece_price: ?float, amount: float, ean: string}
     */
    private function line(array $record): array
    {
        $code  = $record['G_ARTIKEL_NR'] ?? '';
        $packs = $this->number($record['D_MENGE_VE'] ?? null);

        if ($code === '' || $packs === null) {
            throw new RuntimeException('a line has no product code or quantity');
        }

        $pieces     = $this->number($record['E_MENGE_STUECK'] ?? null);
        $piecePrice = $this->number($record['I2_PREIS_STUECK'] ?? null);
        $amount     = $this->number($record['F2_PREIS_GESAMT'] ?? null);

        if ($amount === null && $piecePrice !== null && $pieces !== null) {
            $amount = round($piecePrice * $pieces, 2);
        }

        if ($amount === null) {
            throw new RuntimeException("$code has no price");
        }

        return [
            'code'        => $code,
            'packs'       => $packs,
            'pieces'      => $pieces,
            'piece_price' => $piecePrice,
            'amount'      => $amount,
            'ean'         => $record['L_EAN'] ?? '',
        ];
    }

    /**
     * @param list<string> $header
     * @param array<string, string> $record
     *
     * @return array<string, string>
     */
    private function address(array $header, array $record): array
    {
        $address = [];
        foreach ($header as $column) {
            if (!preg_match('/^M\d{2}_?(.*)$/', $column, $match)) {
                continue;
            }

            foreach (self::ADDRESS_FIELDS as $field => $keywords) {
                if (!isset($address[$field]) && $this->namesField($match[1], $keywords)) {
                    $address[$field] = $record[$column];
                    break;
                }
            }
        }

        return array_filter($address, fn ($value) => $value !== '');
    }

    /**
     * @param list<string> $keywords
     */
    private function namesField(string $column, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (preg_match('/(^|_)'.preg_quote($keyword, '/').'($|_)/u', $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, string>> $records
     */
    private function firstFilled(array $records, string $column): ?string
    {
        foreach ($records as $record) {
            if (($record[$column] ?? '') !== '') {
                return $record[$column];
            }
        }

        return null;
    }

    public function number(?string $value): ?float
    {
        $value = preg_replace('/[^\d,.\-]/', '', (string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
