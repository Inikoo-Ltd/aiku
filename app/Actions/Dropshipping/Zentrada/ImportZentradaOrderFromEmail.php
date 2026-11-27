<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Zentrada;

use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Anyone can write "Delivery order transaction" in a subject, and an order made from it ships goods
 * billed to Zentrada, so only mail Gmail authenticated as tradesafe.eu (DKIM, or SPF when it was not
 * forwarded) is imported. Everything else, and anything that cannot be read, reaches the inbox.
 */
class ImportZentradaOrderFromEmail
{
    use AsAction;

    public const string SENDER_DOMAIN = 'tradesafe.eu';

    public static function isDeliveryOrderMail(Shop $shop, array $raw, ?string $fromAddress, ?string $subject): bool
    {
        return self::isDeliveryOrderSubject($subject) && self::isTradeSafeMail($shop, $raw, $fromAddress);
    }

    public static function isTradeSafeMail(Shop $shop, array $raw, ?string $fromAddress): bool
    {
        return (bool) Arr::get($shop->settings, 'zentrada.import_orders')
            && self::isFromSenderDomain($fromAddress)
            && self::isAuthenticated($raw);
    }

    public static function isDeliveryOrderSubject(?string $subject): bool
    {
        return $subject !== null
            && preg_match('/delivery order transaction/i', $subject)
            && !preg_match('/^\s*(re|fwd?|fw|aw|wg)\s*:/i', $subject);
    }

    private static function isFromSenderDomain(?string $address): bool
    {
        return $address !== null && preg_match('/@([\w-]+\.)*'.preg_quote(self::SENDER_DOMAIN, '/').'$/i', trim($address)) === 1;
    }

    /**
     * Gmail writes its verdict as the topmost Authentication-Results; any further down, and every ARC
     * header, could have been written by the sender. A DKIM signature by the sender's domain survives a
     * forwarding rule, the SPF result only holds for mail sent to us directly.
     */
    public static function isAuthenticated(array $raw): bool
    {
        $results = (string) Arr::get(collect(Arr::get($raw, 'payload.headers', []))
            ->first(fn ($header) => strcasecmp((string) Arr::get($header, 'name'), 'Authentication-Results') === 0), 'value');

        $address = '(?:[^@;\s]*@)?([\w-]+\.)*'.preg_quote(self::SENDER_DOMAIN, '/').'(?=[\s;)]|$)';

        return preg_match('/dkim=pass[^;]*header\.[id]='.$address.'/i', $results) === 1
            || preg_match('/spf=pass[^;]*smtp\.mailfrom='.$address.'/i', $results) === 1;
    }

    /**
     * Null when the mail could not be read, so it is left for a person.
     */
    public function handle(Shop $shop, GmailClient $client, string $gmailMessageId, array $raw): ?Order
    {
        $subject     = (string) GmailMessageParser::header($raw, 'Subject');
        $attachments = GmailMessageParser::attachments(Arr::get($raw, 'payload', []));
        $csv         = collect($attachments)->first(fn ($attachment) => preg_match('/\.csv$/i', $attachment['filename']));
        $transaction = self::transactionFromSubject($subject);

        try {
            if (!$csv) {
                throw new \RuntimeException(__('the email has no transaction CSV'));
            }

            $parsed = ParseZentradaTransactionCsv::make()->handle($this->contents($client, $gmailMessageId, $csv));
        } catch (Throwable $exception) {
            ImportZentradaOrder::make()->askForHelp($shop, null, $transaction ?? $subject, [$exception->getMessage()]);

            return null;
        }

        if ($transaction) {
            $parsed['transaction'] = $transaction;
        }

        $files = collect($attachments)
            ->filter(fn ($attachment) => !$attachment['inline'] && preg_match('/\.(csv|pdf)$/i', $attachment['filename']))
            ->map(fn ($attachment) => ['name' => $attachment['filename'], 'contents' => $this->contents($client, $gmailMessageId, $attachment)])
            ->values()
            ->all();

        $body = GmailMessageParser::body($raw);

        return ImportZentradaOrder::make()->handle($shop, $parsed, [
            'delivery_date' => self::deliveryDate($body),
            'buyer'         => preg_match('/buyer\s+(.+?)(\s*\(|$)/i', $subject, $match) ? trim($match[1]) : null,
        ], $files);
    }

    /**
     * "Delivery order transaction EU-NL 958559 , Buyer Federica Demi (ZuL-Auto)" gives "EU-NL 958559".
     */
    public static function transactionFromSubject(string $subject): ?string
    {
        return preg_match('/transaction\s+([A-Z]{2}-[A-Z]{2})\s*(\d+)/i', $subject, $match) ? strtoupper($match[1]).' '.$match[2] : null;
    }

    public static function deliveryDate(string $body): ?string
    {
        return preg_match('/(deliver|liefer)[^\n]{0,80}?(\d{1,2}\.\d{1,2}\.\d{4})/i', $body, $match) ? $match[2] : null;
    }

    /**
     * @param array{attachmentId: ?string, data: ?string} $attachment
     */
    private function contents(GmailClient $client, string $gmailMessageId, array $attachment): string
    {
        if ($attachment['data']) {
            return GmailMessageParser::decodeData($attachment['data']);
        }

        return $client->getAttachment($gmailMessageId, (string) $attachment['attachmentId']);
    }
}
