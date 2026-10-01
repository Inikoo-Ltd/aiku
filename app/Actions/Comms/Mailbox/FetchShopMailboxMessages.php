<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatMessage;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailHistoryExpiredException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Nightwatch\Facades\Nightwatch;
use Throwable;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchShopMailboxMessages
{
    use AsAction;

    public string $commandSignature = 'mailbox:fetch {shop? : shop slug}';

    private const int ARCHIVE_STANDS_DOWN_MINUTES = 15;

    public function handle(Shop $shop): int
    {
        try {
            return $this->fetch($shop);
        } catch (RequestException $exception) {
            if (GmailClient::isRateLimited($exception->response)) {
                Cache::put(self::refusedKey($shop), true, now()->addMinutes(self::ARCHIVE_STANDS_DOWN_MINUTES));
            }

            throw $exception;
        }
    }

    /**
     * Gmail's allowance is per mailbox and shared with the archive. When the live fetch is refused,
     * the archive of that mailbox stands down so the customers' mail gets the allowance back first.
     */
    public static function wasRecentlyRefused(Shop $shop): bool
    {
        return Cache::has(self::refusedKey($shop));
    }

    private static function refusedKey(Shop $shop): string
    {
        return "gmail-live-fetch-refused:{$shop->id}";
    }

    private function fetch(Shop $shop): int
    {
        $client = GmailClient::forShop($shop);

        if (! $client) {
            return 0;
        }

        $historyId = Arr::get($shop->settings, 'gmail.history_id');

        if ($historyId) {
            try {
                $history      = $client->listHistory($historyId);
                $messageIds   = $history['message_ids'];
                $newHistoryId = $history['history_id'];
            } catch (GmailHistoryExpiredException) {
                $messageIds   = $client->listInboxMessageIds();
                $newHistoryId = $client->profile()['historyId'];
            }
        } else {
            $messageIds   = $client->listInboxMessageIds();
            $newHistoryId = $client->profile()['historyId'];
        }

        $spamIds    = $client->listInboxMessageIds($this->spamSweepQuery($shop), 25);
        $messageIds = array_unique(array_merge(
            $messageIds,
            $client->listInboxMessageIds($this->sweepQuery($shop), 100),
            $spamIds
        ));

        $dispatched = 0;

        foreach ($messageIds as $messageId) {
            if (ChatMessage::where('metadata->gmail_message_id', $messageId)->exists()) {
                // Mail Gmail moved to spam after it came in would be listed on every sweep, one of
                // the 25 places each time, until enough of them stopped the backlog altogether.
                if (in_array($messageId, $spamIds, true)) {
                    $client->fileAway($messageId, ProcessInboundEmail::SPAM_CHECKED_LABEL, markRead: false);
                }

                continue;
            }

            if (Cache::has(ProcessInboundEmail::leftInSpamKey($shop, $messageId))) {
                continue;
            }

            if (! Cache::add($this->dispatchedKey($shop, $messageId), true, now()->addMinutes(30))) {
                continue;
            }

            ProcessInboundEmail::dispatch($shop, $messageId);
            $dispatched++;
        }

        $this->updateGmailSettings($shop, $newHistoryId);

        return $dispatched;
    }

    /**
     * Never further back than the day the mailbox was connected. A shared mailbox has years of
     * mail in it that nobody ever offered to Aiku, and an unbounded sweep does not take back what
     * was lost, it claims the lot: every old message opens a conversation dated today and lands in
     * the queue as work. Only what arrived after we started answering this mailbox is ours.
     */
    private function sweepQuery(Shop $shop): string
    {
        $connectedAt = Arr::get($shop->settings, 'gmail.connected_at');

        if (! $connectedAt) {
            return 'in:inbox newer_than:1d';
        }

        return 'in:inbox after:'.Carbon::parse($connectedAt)->format('Y/m/d');
    }

    /**
     * Only what has not been read yet, so a backlog is worked through 25 at a time instead of the
     * newest 100 being listed forever, and a mailbox with a full spam folder does not spend its
     * Gmail quota in one burst.
     */
    private function spamSweepQuery(Shop $shop): string
    {
        $checked = str_replace('/', '-', ProcessInboundEmail::SPAM_CHECKED_LABEL);

        return str_replace('in:inbox', "in:spam -label:$checked", $this->sweepQuery($shop));
    }

    /**
     * History is read once and never again: a message whose job died took its only chance with
     * it, and nothing said so, because a failure writes no row and applies no label. Anything
     * taken in leaves the inbox, so what is still sitting there is exactly what has not arrived,
     * and sweeping it is the only honest answer to "did everything come through".
     *
     * ponytail: a message that fails every time is retried twice an hour rather than every
     * minute; if that ever becomes noise, file the repeat offenders under a label instead.
     */
    private function dispatchedKey(Shop $shop, string $messageId): string
    {
        return "gmail-message-dispatched:{$shop->id}:$messageId";
    }

    private function updateGmailSettings(Shop $shop, string $historyId): void
    {
        $settings           = $shop->settings ?? [];
        $settings['gmail']  = array_merge($settings['gmail'] ?? [], [
            'history_id'      => $historyId,
            'last_fetched_at' => now()->toIso8601String(),
        ]);

        $shop->update(['settings' => $settings]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $slug = $command->argument('shop');

        if ($slug) {
            $shop  = Shop::where('slug', $slug)->firstOrFail();
            $count = $this->handle($shop);
            $command->info("Dispatched {$count} inbound email(s) for {$shop->slug}");

            return 0;
        }

        $shops = Shop::whereNotNull('settings->gmail->refresh_token')->get();

        $total = 0;
        foreach ($shops as $shop) {
            try {
                $total += $this->handle($shop);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $command->info("Dispatched {$total} inbound email(s) across {$shops->count()} shop(s)");

        return 0;
    }
}
