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
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchShopMailboxMessages
{
    use AsAction;

    public string $commandSignature = 'mailbox:fetch {shop? : shop slug}';

    public function handle(Shop $shop): int
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

        $dispatched = 0;

        foreach ($messageIds as $messageId) {
            if (ChatMessage::where('metadata->gmail_message_id', $messageId)->exists()) {
                continue;
            }

            ProcessInboundEmail::dispatch($shop, $messageId);
            $dispatched++;
        }

        $this->updateGmailSettings($shop, $newHistoryId);

        return $dispatched;
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
            $total += $this->handle($shop);
        }

        $command->info("Dispatched {$total} inbound email(s) across {$shops->count()} shop(s)");

        return 0;
    }
}
