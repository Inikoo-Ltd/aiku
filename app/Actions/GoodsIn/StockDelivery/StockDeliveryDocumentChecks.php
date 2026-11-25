<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Models\GoodsIn\StockDelivery;

/**
 * What a reading of a document must pass before any of its amounts can be costed. None of it asks a model: the
 * amounts must be printed in the document, the document must name the delivery and no other, and two readers
 * must have read the same.
 */
class StockDeliveryDocumentChecks
{
    private const int MINIMUM_TEXT = 40;

    private const string SEPARATOR = '[ \x{00A0}\x{202F}.,\']?';

    public static function hasText(string $text): bool
    {
        return mb_strlen((string) preg_replace('/\s+/u', '', $text)) >= self::MINIMUM_TEXT;
    }

    /**
     * Whether the amount is printed in the text, in any of the ways a document writes it: 5804, 5 804,00,
     * 5,804.00 or 5.804,00, with one, two or four decimals but never three, which is how thousands are written.
     * Text taken from a PDF runs columns together, so a number cut short on the left (695,00 out of 1 695,00) is
     * still found, and so is a small whole number that is a quantity: the totals check is what catches those.
     */
    public static function amountInText(float $amount, string $text): bool
    {
        $cents    = (int) round(abs($amount) * 100);
        $groups   = array_map('strrev', array_reverse(str_split(strrev((string) intdiv($cents, 100)), 3)));
        $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
        $shown    = rtrim($fraction, '0');
        $decimals = $shown === ''
            ? '(?:[.,](?:0{4}|0{1,2}))?'
            : '[.,](?:'.str_pad($shown, 4, '0').'|'.str_pad($shown, 2, '0').(strlen($shown) === 1 ? '|'.$shown : '').')';

        return (bool) preg_match('/(?<![\d.,])'.implode(self::SEPARATOR, $groups).$decimals.'(?![.,]?\d)/u', $text);
    }

    /**
     * The ways a document can name the delivery, from its reference: AWChina-92-VAL260365 is named by the shipment
     * number VAL260365, but not by the order AWChina-92, which other deliveries share, nor Brenntag-00035 by a
     * zero-padded counter 00035 that any customs document code can contain (HELP-4952). A reference with no such
     * number of its own is named only by the whole of it, and one that reads like a date or a count by nothing.
     *
     * @return array<int, string> regular expressions
     */
    public static function identifiers(string $reference): array
    {
        $tokens = array_values(array_filter((array) preg_split('/[^A-Za-z0-9]+/', $reference), fn ($token) => $token !== ''));
        $strong = array_filter($tokens, fn (string $token) => strlen(ctype_digit($token) ? ltrim($token, '0') : $token) >= 5 && preg_match_all('/\d/', $token) >= 3);

        if ($strong) {
            return array_values(array_unique(array_map(fn (string $token) => self::pattern([$token]), $strong)));
        }

        $whole = implode('', $tokens);

        return strlen($whole) >= 6 && preg_match('/[A-Za-z]/', $whole) && preg_match_all('/\d/', $whole) >= 2
            ? [self::pattern($tokens)]
            : [];
    }

    /**
     * Null when the reference gives nothing to look for, so it is a person who must check it.
     */
    public static function mentions(string $reference, string $text): ?bool
    {
        $identifiers = self::identifiers($reference);

        if (!$identifiers) {
            return null;
        }

        foreach ($identifiers as $identifier) {
            if (preg_match($identifier, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every other delivery of the group in the last two years that the document names, costed or cancelled ones
     * and those of other organisations included: an invoice that names two deliveries is not all of one. A delivery
     * named the same way as this one counts, since the document cannot tell them apart.
     *
     * @return array<int, string> references
     */
    public static function otherDeliveriesMentioned(StockDelivery $stockDelivery, string $text): array
    {
        return StockDelivery::where('group_id', $stockDelivery->group_id)
            ->where('id', '!=', $stockDelivery->id)
            ->where('created_at', '>=', now()->subYears(2))
            ->pluck('reference')
            ->unique()
            ->filter(fn (string $reference) => self::mentions($reference, $text) === true)
            ->values()
            ->all();
    }

    /**
     * Whether the document prints the currency, as its code or the way it is usually written.
     */
    public static function currencyInText(string $code, string $text): bool
    {
        $written = [
            'EUR' => ['€'],
            'GBP' => ['£'],
            'USD' => ['US$', '$'],
            'CNY' => ['RMB', '¥', '￥', '元'],
            'JPY' => ['¥', '￥', '円'],
            'INR' => ['₹', 'Rs'],
        ];

        foreach ([strtoupper($code), ...($written[strtoupper($code)] ?? [])] as $sign) {
            if (mb_stripos($text, $sign) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * What two readings of the same invoice do not agree on. Wording is free to differ, numbers and product codes
     * are not: a line is its code, quantity and amount, and a service invoice line, which has neither code nor
     * quantity, its amount alone.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<int, string>
     */
    public static function disagreements(array $first, array $second): array
    {
        $cents  = fn ($amount) => $amount === null ? null : (int) round((float) $amount * 100);
        $sorted = function (array $values) {
            sort($values);

            return $values;
        };
        $lines   = fn (array $reading) => $sorted(array_map(
            fn (array $line) => self::normalise($line['code'] ?? null).'|'.(isset($line['quantity']) ? round((float) $line['quantity'], 4) : '').'|'.$cents($line['amount'] ?? null),
            $reading['lines'] ?? []
        ));
        $charges = fn (array $reading) => $sorted(array_map(fn (array $charge) => $cents($charge['amount'] ?? null), $reading['charges'] ?? []));
        $same    = [
            'is_invoice'     => fn (array $reading) => (bool) ($reading['is_invoice'] ?? false),
            'invoice_number' => fn (array $reading) => self::normalise($reading['invoice_number'] ?? null),
            'invoice_date'   => fn (array $reading) => $reading['invoice_date'] ?? null,
            'currency'       => fn (array $reading) => (string) self::currencyCode($reading['currency'] ?? null),
            'net_total'      => fn (array $reading) => $cents($reading['net_total'] ?? null),
            'vat_total'      => fn (array $reading) => $cents($reading['vat_total'] ?? null),
            'total_payable'  => fn (array $reading) => $cents($reading['total_payable'] ?? null),
            'total'          => fn (array $reading) => $cents($reading['total'] ?? null),
            'lines'          => $lines,
            'charges'        => $charges,
        ];

        return array_keys(array_filter($same, fn (callable $value) => $value($first) !== $value($second)));
    }

    /**
     * The ISO code of a currency as a reader gave it: Chinese invoices print RMB for CNY.
     */
    public static function currencyCode(?string $currency): ?string
    {
        $code = strtoupper(trim((string) $currency));

        return ['RMB' => 'CNY'][$code] ?? ($code ?: null);
    }

    public static function normalise(?string $value): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private static function pattern(array $tokens): string
    {
        return '/(?<![A-Za-z0-9])'.implode('[\W_]*', array_map(fn (string $token) => preg_quote($token, '/'), $tokens)).'(?![A-Za-z0-9])/iu';
    }
}
