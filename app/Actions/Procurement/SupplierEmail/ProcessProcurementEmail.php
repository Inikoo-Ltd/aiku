<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail;

use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use App\Enums\Procurement\SupplierEmail\SupplierEmailDirectionEnum;
use App\Models\Procurement\SupplierEmail;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use App\Services\HTMLSanitizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessProcurementEmail
{
    use AsAction;

    /**
     * The procurement mailbox is mirrored, never tidied: staff still read and answer it in Gmail,
     * so nothing is labelled, archived or marked read here. Both directions are kept, because what
     * we promised a supplier matters as much as what they told us.
     */
    public function handle(Organisation $organisation, string $gmailMessageId): ?SupplierEmail
    {
        if (SupplierEmail::where('gmail_message_id', $gmailMessageId)->exists()) {
            return null;
        }

        $client = GmailClient::forProcurement($organisation);

        if (! $client) {
            return null;
        }

        try {
            $raw = $client->getMessage($gmailMessageId);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }

        $labels = Arr::get($raw, 'labelIds', []);

        if (array_intersect($labels, ['DRAFT', 'SPAM', 'TRASH', 'CHAT'])) {
            return null;
        }

        $mailbox = Str::lower((string) Arr::get($organisation->settings, 'procurement.gmail.email'));
        $from    = GmailMessageParser::fromAddress($raw);
        $to      = GmailMessageParser::addresses($raw, 'To');
        $cc      = GmailMessageParser::addresses($raw, 'Cc');
        $subject = GmailMessageParser::header($raw, 'Subject');

        $isOutbound = in_array('SENT', $labels, true) || Str::lower((string) $from['address']) === $mailbox;

        $counterparts = $isOutbound
            ? collect($to)->merge($cc)->pluck('address')->reject(fn ($address) => Str::lower($address) === $mailbox)->values()->all()
            : [$from['address']];

        $threadId = GmailMessageParser::threadId($raw);

        [$orgSupplier, $routedBy] = RouteSupplierEmail::run($organisation, $counterparts, $threadId);

        if (! $orgSupplier && ! $isOutbound && ProcessInboundEmail::isAutomatedMail($from['address'], $subject)) {
            return null;
        }

        $html = GmailMessageParser::htmlBody($raw);

        return SupplierEmail::create([
            'group_id'         => $organisation->group_id,
            'organisation_id'  => $organisation->id,
            'supplier_id'      => $orgSupplier?->supplier_id,
            'org_supplier_id'  => $orgSupplier?->id,
            'gmail_message_id' => $gmailMessageId,
            'gmail_thread_id'  => $threadId,
            'direction'        => $isOutbound ? SupplierEmailDirectionEnum::OUTBOUND : SupplierEmailDirectionEnum::INBOUND,
            'routed_by'        => $routedBy,
            'from_address'     => $from['address'],
            'from_name'        => $from['name'],
            'to'               => $to,
            'cc'               => $cc,
            'subject'          => $subject,
            'snippet'          => html_entity_decode((string) Arr::get($raw, 'snippet'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'body_text'        => GmailMessageParser::body($raw),
            'body_html'        => $html ? app(HTMLSanitizer::class)->cleanEmail($html) : null,
            'attachments'      => $this->attachments($raw),
            'sent_at'          => Carbon::createFromTimestampMs((int) Arr::get($raw, 'internalDate', now()->getTimestampMs())),
        ]);
    }

    /**
     * Only what the sender attached on purpose. Files stay in Gmail and are fetched when someone
     * opens them, so a supplier's catalogue PDFs are not copied into our storage on arrival.
     *
     * @return array<int, array{attachment_id: string, name: string, mime_type: string, size: int}>
     */
    private function attachments(array $raw): array
    {
        return collect(GmailMessageParser::attachments(Arr::get($raw, 'payload', [])))
            ->filter(fn (array $attachment) => $attachment['attachmentId'] && ! $attachment['inline'])
            ->map(fn (array $attachment) => [
                'attachment_id' => $attachment['attachmentId'],
                'name'          => $attachment['filename'],
                'mime_type'     => $attachment['mimeType'],
                'size'          => $attachment['size'],
            ])
            ->values()
            ->all();
    }
}
