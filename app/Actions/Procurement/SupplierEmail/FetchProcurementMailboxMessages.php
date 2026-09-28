<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail;

use App\Models\Procurement\SupplierEmail;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailHistoryExpiredException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchProcurementMailboxMessages
{
    use AsAction;

    public string $commandSignature = 'procurement-mailbox:fetch {organisation? : organisation slug}';

    /**
     * History says what was added since the last read, in the inbox and in sent mail alike. The
     * sweep over the last two days catches whatever a failed job dropped, since history is only
     * ever read once.
     */
    public function handle(Organisation $organisation): int
    {
        $client = GmailClient::forProcurement($organisation);

        if (! $client) {
            return 0;
        }

        $historyId = Arr::get($organisation->settings, 'procurement.gmail.history_id');

        try {
            $history      = $historyId ? $client->listHistory($historyId, null) : null;
            $messageIds   = $history['message_ids'] ?? [];
            $newHistoryId = $history['history_id'] ?? $client->profile()['historyId'];
        } catch (GmailHistoryExpiredException) {
            $messageIds   = [];
            $newHistoryId = $client->profile()['historyId'];
        }

        $messageIds = array_unique(array_merge($messageIds, $client->listInboxMessageIds($this->sweepQuery($organisation), 100)));

        $known = SupplierEmail::whereIn('gmail_message_id', $messageIds)->pluck('gmail_message_id')->all();

        $dispatched = 0;

        foreach (array_diff($messageIds, $known) as $messageId) {
            if (! Cache::add("procurement-gmail-dispatched:$messageId", true, now()->addMinutes(30))) {
                continue;
            }

            ProcessProcurementEmail::dispatch($organisation, $messageId);
            $dispatched++;
        }

        $settings = $organisation->settings ?? [];
        data_set($settings, 'procurement.gmail.history_id', $newHistoryId);
        data_set($settings, 'procurement.gmail.last_fetched_at', now()->toIso8601String());
        $organisation->update(['settings' => $settings]);

        return $dispatched;
    }

    /**
     * Never before the day the mailbox was connected: years of old mail are not ours to import.
     */
    private function sweepQuery(Organisation $organisation): string
    {
        $connectedAt = Carbon::parse(Arr::get($organisation->settings, 'procurement.gmail.connected_at', now()));
        $since       = $connectedAt->max(now()->subDays(2));

        return '-in:spam -in:trash -in:drafts -in:chats after:'.$since->format('Y/m/d');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::whereNotNull('settings->procurement->gmail->refresh_token')
            ->when($command->argument('organisation'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        $total = 0;
        foreach ($organisations as $organisation) {
            $total += $this->handle($organisation);
        }

        $command->info("Dispatched $total procurement email(s) across {$organisations->count()} organisation(s)");

        return 0;
    }
}
