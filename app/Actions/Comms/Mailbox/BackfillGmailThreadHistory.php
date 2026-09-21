<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Services\Gmail\GmailClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Sleep;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class BackfillGmailThreadHistory
{
    use AsAction;

    public string $commandSignature = 'mailbox:backfill-threads {shop? : shop slug} {--closed : include closed conversations}';

    /**
     * @return array{sessions: int, messages: int, failed: int}
     */
    public function handle(Shop $shop, bool $includeClosed = false): array
    {
        $result = ['sessions' => 0, 'messages' => 0, 'failed' => 0];
        $client = GmailClient::forShop($shop);

        if (! $client) {
            return $result;
        }

        $mailboxAddress = Arr::get($shop->settings, 'gmail.email');

        ChatSession::where('shop_id', $shop->id)
            ->where('channel', ChatChannelEnum::EMAIL)
            ->whereNotNull('metadata->gmail_thread_id')
            ->when(! $includeClosed, fn ($query) => $query->where('status', '!=', ChatSessionStatusEnum::CLOSED))
            ->with('webUser')
            ->chunkById(100, function ($sessions) use ($client, $mailboxAddress, &$result) {
                foreach ($sessions as $session) {
                    try {
                        $result['messages'] += retry(
                            4,
                            fn () => ProcessInboundEmail::make()->importThreadHistory(
                                $client,
                                $session,
                                Arr::get($session->metadata, 'gmail_thread_id'),
                                $mailboxAddress,
                                $session->webUser
                            ),
                            30000,
                            fn (Throwable $exception) => $exception instanceof RequestException
                                && in_array($exception->response->status(), [403, 429], true)
                        );
                        $result['sessions']++;
                        Sleep::for(1)->second();
                    } catch (Throwable $exception) {
                        report($exception);
                        $result['failed']++;
                    }
                }
            });

        return $result;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $slug  = $command->argument('shop');
        $shops = $slug
            ? Shop::where('slug', $slug)->get()
            : Shop::whereNotNull('settings->gmail->refresh_token')->get();

        foreach ($shops as $shop) {
            $result = $this->handle($shop, (bool) $command->option('closed'));
            $command->info("{$shop->slug}: {$result['messages']} message(s) into {$result['sessions']} conversation(s), {$result['failed']} failed");
        }

        return 0;
    }
}
