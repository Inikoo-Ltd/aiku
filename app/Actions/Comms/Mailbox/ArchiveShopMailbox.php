<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatMessage;
use App\Models\Comms\EmailArchiveMessage;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Copies a shop mailbox's past correspondence with its customers into the email archive: what
 * they wrote and what we answered, as text, linked to the one customer the address belongs to;
 * mail with anybody else is not kept. Machine mail, our own mailshots, marketplaces, couriers,
 * staff writing to each other and anything sent automatically (our own closed-now replies
 * included) are left out, as are mails the chat inbox already holds and the part of a mail quoted
 * from earlier ones. Each mail is first fetched as its headers only, and only the few with a
 * customer are fetched whole. Already archived mails are skipped and the page reached is
 * remembered, so a run that stops is continued by running it again. With --queue each mailbox is
 * read one page per job on the long-low-priority queue, three mailboxes at a time, a few mails
 * at a time, well inside Gmail's limit per mailbox; a page Gmail refuses is read again a minute
 * later, giving up after MAX_RATE_LIMITED refusals in a row. Starting the command again replaces
 * the chain of jobs a mailbox already has, so two never read the same mailbox.
 */
class ArchiveShopMailbox
{
    use AsAction;

    public string $commandSignature = 'mailbox:archive {shop? : shop slug} {--m|months=12} {--l|limit= : Stop after this many mails read} {--fresh : Forget what was archived and start again} {--queue : Run every mailbox side by side on the queue}';

    private const int TEXT_LIMIT = 20000;

    private const int CONCURRENCY = 5;

    private const int RATE_LIMIT_PAUSE = 60;

    private const int MAX_RATE_LIMITED = 10;

    /**
     * Left out by Gmail's own search, never fetched: spam, bin, drafts, chats and the
     * promotions, social and forums tabs, where no customer writes to us.
     */
    private const string LEAVE_OUT = '-in:spam -in:trash -in:drafts -in:chats -category:promotions -category:social -category:forums';

    /**
     * What the headers-only pass fetches to decide whether a mail is worth reading.
     */
    private const array HEADERS = ['From', 'To', 'Cc', 'Subject', 'Auto-Submitted', 'X-Autoreply', 'X-Autorespond', 'X-Auto-Response-Suppress', 'Precedence', 'List-Unsubscribe'];

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public int $jobTries = 3;

    /**
     * The whole mailbox in one go, page after page, for running from the command line.
     *
     * @return array{read: int, archived: int, skipped: int, failed: int, done: bool}
     */
    public function handle(Shop $shop, int $months = 12, ?int $limit = null): array
    {
        $total = ['read' => 0, 'archived' => 0, 'skipped' => 0, 'failed' => 0, 'done' => false];

        do {
            $page = $this->archivePage($shop, $months);

            foreach (['read', 'archived', 'skipped', 'failed'] as $key) {
                $total[$key] += $page[$key];
            }
            $total['done'] = $page['done'];

            if ($page['rate_limited'] && !$page['stopped']) {
                Sleep::for(self::RATE_LIMIT_PAUSE)->seconds();
            }
        } while (!$page['done'] && !$page['stopped'] && (!$limit || $total['read'] < $limit));

        return $total;
    }

    /**
     * On the queue each job reads one page of the listing and queues the next, so no job runs for
     * long and a restarted worker carries on from the page reached. A job of a chain the command
     * has since replaced does nothing.
     */
    public function asJob(Shop $shop, int $months = 12, ?string $run = null): void
    {
        if (Cache::get(self::runKey($shop)) !== $run) {
            return;
        }

        $page = $this->archivePage($shop, $months);

        if ($page['done'] || $page['stopped']) {
            return;
        }

        $page['rate_limited']
            ? static::dispatch($shop, $months, $run)->delay(now()->addSeconds(self::RATE_LIMIT_PAUSE))
            : static::dispatch($shop, $months, $run);
    }

    /**
     * One page of the listing (up to 500 mails), newest first: the headers of the new ones, then
     * the whole of those worth keeping, CONCURRENCY at a time.
     *
     * Gmail still refusing after a pause leaves the page where it was, to be read again later.
     *
     * @return array{read: int, archived: int, skipped: int, failed: int, done: bool, stopped: bool, rate_limited: bool}
     */
    public function archivePage(Shop $shop, int $months): array
    {
        $result  = ['read' => 0, 'archived' => 0, 'skipped' => 0, 'failed' => 0, 'done' => false, 'stopped' => false, 'rate_limited' => false];
        $client  = GmailClient::forShop($shop);
        $mailbox = mb_strtolower((string) Arr::get($shop->settings, 'gmail.email'));

        if (!$client || $mailbox === '') {
            $result['stopped'] = true;

            return $result;
        }

        $cursorKey = self::cursorKey($shop, $months);
        $page      = retry(4, fn () => $client->listMessageIds("newer_than:{$months}m ".self::LEAVE_OUT, Cache::get($cursorKey)), 30000, fn (Throwable $exception) => $this->isRateLimit($exception));
        $new       = array_values(array_diff($page['ids'], EmailArchiveMessage::where('shop_id', $shop->id)->whereIn('gmail_message_id', $page['ids'])->pluck('gmail_message_id')->all()));
        $worth     = [];

        foreach (array_chunk($new, self::CONCURRENCY) as $ids) {
            $headers = $this->fetch($client, $ids, self::HEADERS);

            if ($headers === null) {
                return ['rate_limited' => true, 'stopped' => $this->refusedTooOften($shop)] + $result;
            }

            foreach ($headers as $id => $raw) {
                $result['read']++;

                if (!is_array($raw)) {
                    $result['failed']++;
                } elseif ($this->counterpart($shop, $mailbox, $raw)) {
                    $worth[] = $id;
                } else {
                    $result['skipped']++;
                }
            }
        }

        foreach (array_chunk($worth, self::CONCURRENCY) as $ids) {
            $messages = $this->fetch($client, $ids);

            if ($messages === null) {
                return ['rate_limited' => true, 'stopped' => $this->refusedTooOften($shop)] + $result;
            }

            foreach ($messages as $raw) {
                if (!is_array($raw)) {
                    $result['failed']++;

                    continue;
                }

                try {
                    $this->archive($shop, $mailbox, $raw) ? $result['archived']++ : $result['skipped']++;
                } catch (Throwable $exception) {
                    report($exception);
                    $result['failed']++;
                }
            }
        }

        Cache::forget(self::rateLimitedKey($shop));

        if ($page['next']) {
            Cache::put($cursorKey, $page['next'], now()->addDays(7));
        } else {
            Cache::forget($cursorKey);
            $result['done'] = true;
        }

        return $result;
    }

    public static function cursorKey(Shop $shop, int $months): string
    {
        return "mailbox-archive:{$shop->id}:{$months}";
    }

    public static function runKey(Shop $shop): string
    {
        return "mailbox-archive-run:{$shop->id}";
    }

    private static function rateLimitedKey(Shop $shop): string
    {
        return "mailbox-archive-rate-limited:{$shop->id}";
    }

    private function refusedTooOften(Shop $shop): bool
    {
        $refusals = Cache::increment(self::rateLimitedKey($shop));

        if ($refusals < self::MAX_RATE_LIMITED) {
            return false;
        }

        Cache::forget(self::rateLimitedKey($shop));
        Log::warning("mailbox:archive {$shop->slug}: Gmail refused ".self::MAX_RATE_LIMITED.' times in a row, stopped; run it again to continue');

        return true;
    }

    /**
     * Several mails, whole or only the given headers; null when Gmail still refuses after a pause.
     *
     * @param  array<int, string>  $ids
     * @param  array<int, string>  $onlyHeaders
     * @return array<string, array<string, mixed>|null>|null
     */
    private function fetch(GmailClient $client, array $ids, array $onlyHeaders = []): ?array
    {
        $messages = $client->getMessages($ids, $onlyHeaders);

        if (in_array('rate_limited', $messages, true)) {
            Sleep::for(20)->seconds();
            $messages = array_merge($messages, $client->getMessages(array_keys(array_filter($messages, fn ($raw) => $raw === 'rate_limited')), $onlyHeaders));
        }

        return in_array('rate_limited', $messages, true) ? null : $messages;
    }

    /**
     * Who the mail is with, when it is worth keeping: one customer of the shop, written by a
     * person. Decided from the headers alone, so most mail is dropped before its body is fetched.
     *
     * @param  array<string, mixed>  $raw
     * @return array{from: string, to: array<int, string>, is_outbound: bool, other: string, subject: string|null, customer_id: int}|null
     */
    private function counterpart(Shop $shop, string $mailbox, array $raw): ?array
    {
        $from       = mb_strtolower((string) GmailMessageParser::fromAddress($raw)['address']);
        $to         = array_map(fn (array $address) => mb_strtolower($address['address']), [...GmailMessageParser::addresses($raw, 'To'), ...GmailMessageParser::addresses($raw, 'Cc')]);
        $isOutbound = in_array('SENT', Arr::get($raw, 'labelIds', []), true) || $from === $mailbox;
        $ours       = ProcessInboundEmail::ourOwnAddresses();
        $other      = $isOutbound
            ? collect($to)->first(fn (string $address) => $address !== $mailbox && !isset($ours[$address]))
            : $from;
        $subject    = GmailMessageParser::header($raw, 'Subject');

        if (!$other
            || isset($ours[$other])
            || ProcessInboundEmail::isAutomatedMail($other, $subject)
            || ProcessInboundEmail::isMarketplaceNotice($other)
            || ProcessInboundEmail::isCarrierAddress($other, $shop->group)
            || ProcessInboundEmail::isAutoReply($raw)
            || GmailMessageParser::header($raw, 'List-Unsubscribe')) {
            return null;
        }

        $customerId = $this->customerId($shop, $other);

        if (!$customerId || ChatMessage::where('metadata->gmail_message_id', (string) Arr::get($raw, 'id'))->exists()) {
            return null;
        }

        return ['from' => $from, 'to' => $to, 'is_outbound' => $isOutbound, 'other' => $other, 'subject' => $subject, 'customer_id' => $customerId];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function archive(Shop $shop, string $mailbox, array $raw): ?EmailArchiveMessage
    {
        $counterpart = $this->counterpart($shop, $mailbox, $raw);
        $text        = $counterpart ? trim(strip_tags(GmailMessageParser::body($raw))) : '';

        if ($text === '') {
            return null;
        }

        ['from' => $from, 'to' => $to, 'is_outbound' => $isOutbound, 'other' => $other, 'subject' => $subject, 'customer_id' => $customerId] = $counterpart;

        return EmailArchiveMessage::firstOrCreate(
            ['shop_id' => $shop->id, 'gmail_message_id' => (string) Arr::get($raw, 'id')],
            [
                'group_id'            => $shop->group_id,
                'organisation_id'     => $shop->organisation_id,
                'customer_id'         => $customerId,
                'gmail_thread_id'     => GmailMessageParser::threadId($raw),
                'is_outbound'         => $isOutbound,
                'from_address'        => $from ?: null,
                'to_addresses'        => $to,
                'counterpart_address' => $other,
                'subject'             => mb_substr((string) $subject, 0, 1000) ?: null,
                'text'                => mb_substr($text, 0, self::TEXT_LIMIT),
                'sent_at'             => Carbon::createFromTimestampMs((int) Arr::get($raw, 'internalDate')),
            ]
        );
    }

    /**
     * The one customer of the shop with this address, on their record or a web user of theirs.
     * An address two customers share belongs to neither: their letters must never show on the
     * wrong customer's page.
     */
    private function customerId(Shop $shop, string $address): ?int
    {
        $ids = Customer::where('shop_id', $shop->id)->where('email', $address)->pluck('id')
            ->merge(WebUser::where('shop_id', $shop->id)->where('email', $address)->pluck('customer_id'))
            ->filter()
            ->unique();

        return $ids->count() === 1 ? $ids->first() : null;
    }

    private function isRateLimit(Throwable $exception): bool
    {
        return $exception instanceof RequestException && GmailClient::isRateLimited($exception->response);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $slug  = $command->argument('shop');
        $shops = $slug ? Shop::where('slug', $slug)->get() : Shop::whereNotNull('settings->gmail->refresh_token')->get();

        foreach ($shops as $shop) {
            $run = (string) Str::uuid();
            Cache::put(self::runKey($shop), $run, now()->addDays(7));

            if ($command->option('fresh')) {
                EmailArchiveMessage::where('shop_id', $shop->id)->delete();
                Cache::forget(self::cursorKey($shop, (int) $command->option('months')));
            }

            if ($command->option('queue')) {
                static::dispatch($shop, (int) $command->option('months'), $run);
                $command->info("{$shop->slug}: queued");

                continue;
            }

            $result = $this->handle($shop, (int) $command->option('months'), $command->option('limit') ? (int) $command->option('limit') : null);
            $command->info("{$shop->slug}: {$result['archived']} archived, {$result['skipped']} left out, {$result['failed']} failed of {$result['read']} read".($result['done'] ? '' : ' (run again to continue)'));
        }

        return 0;
    }
}
