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
use Illuminate\Support\Sleep;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Copies a shop mailbox's past correspondence into the email archive: what customers wrote and
 * what we answered, as text, linked to the customer by address. Machine mail, our own mailshots,
 * marketplaces, couriers, staff writing to each other and anything sent automatically (our own
 * closed-now replies included) are left out, as are mails the chat inbox already holds and the
 * part of a mail quoted from earlier ones. Already archived mails are skipped and the page
 * reached is remembered, so a run that stops is continued by running it again. With --queue every
 * mailbox runs side by side on Horizon, one page per job; each mailbox is read a few mails at a
 * time, well inside Gmail's limit per mailbox, and a page Gmail refuses is read again a minute
 * later.
 */
class ArchiveShopMailbox
{
    use AsAction;

    public string $commandSignature = 'mailbox:archive {shop? : shop slug} {--m|months=12} {--l|limit= : Stop after this many mails read} {--fresh : Forget what was archived and start again} {--queue : Run every mailbox side by side on the queue}';

    private const int TEXT_LIMIT = 20000;

    private const int CONCURRENCY = 5;

    private const int RATE_LIMIT_PAUSE = 60;

    public string $jobQueue = 'low-priority';

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

            if ($page['rate_limited']) {
                Sleep::for(self::RATE_LIMIT_PAUSE)->seconds();
            }
        } while (!$page['done'] && !$page['stopped'] && (!$limit || $total['read'] < $limit));

        return $total;
    }

    /**
     * On the queue each job reads one page of the listing and queues the next, so every mailbox
     * runs side by side, no job runs for long and a restarted worker carries on from the page
     * reached.
     */
    public function asJob(Shop $shop, int $months = 12): void
    {
        $page = $this->archivePage($shop, $months);

        if ($page['rate_limited']) {
            static::dispatch($shop, $months)->delay(now()->addSeconds(self::RATE_LIMIT_PAUSE));
        } elseif (!$page['done'] && !$page['stopped']) {
            static::dispatch($shop, $months);
        }
    }

    /**
     * One page of the listing (up to 500 mails), newest first, fetched CONCURRENCY at a time.
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
        $page      = retry(4, fn () => $client->listMessageIds("newer_than:{$months}m -in:spam -in:trash -in:drafts -in:chats", Cache::get($cursorKey)), 30000, fn (Throwable $exception) => $this->isRateLimit($exception));
        $new       = array_values(array_diff($page['ids'], EmailArchiveMessage::where('shop_id', $shop->id)->whereIn('gmail_message_id', $page['ids'])->pluck('gmail_message_id')->all()));

        foreach (array_chunk($new, self::CONCURRENCY) as $ids) {
            $messages = $client->getMessages($ids);

            if (in_array('rate_limited', $messages, true)) {
                Sleep::for(20)->seconds();
                $messages = array_merge($messages, $client->getMessages(array_keys(array_filter($messages, fn ($raw) => $raw === 'rate_limited'))));
            }

            if (in_array('rate_limited', $messages, true)) {
                $result['rate_limited'] = true;

                return $result;
            }

            foreach ($messages as $raw) {
                $result['read']++;

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

    /**
     * @param  array<string, mixed>  $raw
     */
    public function archive(Shop $shop, string $mailbox, array $raw): ?EmailArchiveMessage
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
            || ChatMessage::where('metadata->gmail_message_id', (string) Arr::get($raw, 'id'))->exists()
            || GmailMessageParser::header($raw, 'List-Unsubscribe')) {
            return null;
        }

        $text = trim(strip_tags(GmailMessageParser::body($raw)));

        if ($text === '') {
            return null;
        }

        return EmailArchiveMessage::firstOrCreate(
            ['shop_id' => $shop->id, 'gmail_message_id' => (string) Arr::get($raw, 'id')],
            [
                'group_id'            => $shop->group_id,
                'organisation_id'     => $shop->organisation_id,
                'customer_id'         => $this->customerId($shop, $other),
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

    private function customerId(Shop $shop, string $address): ?int
    {
        return Customer::where('shop_id', $shop->id)->where('email', $address)->value('id')
            ?? WebUser::where('shop_id', $shop->id)->where('email', $address)->value('customer_id');
    }

    private function isRateLimit(Throwable $exception): bool
    {
        return $exception instanceof RequestException && in_array($exception->response->status(), [403, 429, 500, 503], true);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $slug  = $command->argument('shop');
        $shops = $slug ? Shop::where('slug', $slug)->get() : Shop::whereNotNull('settings->gmail->refresh_token')->get();

        foreach ($shops as $shop) {
            if ($command->option('fresh')) {
                EmailArchiveMessage::where('shop_id', $shop->id)->delete();
                Cache::forget(self::cursorKey($shop, (int) $command->option('months')));
            }

            if ($command->option('queue')) {
                static::dispatch($shop, (int) $command->option('months'));
                $command->info("{$shop->slug}: queued");

                continue;
            }

            $result = $this->handle($shop, (int) $command->option('months'), $command->option('limit') ? (int) $command->option('limit') : null);
            $command->info("{$shop->slug}: {$result['archived']} archived, {$result['skipped']} left out, {$result['failed']} failed of {$result['read']} read".($result['done'] ? '' : ' (run again to continue)'));
        }

        return 0;
    }
}
