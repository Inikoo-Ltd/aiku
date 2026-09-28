<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatSession;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-3467: a vulnerability scanner left 317 guest conversations on awgifts.eu (27 Sep) and 27 on
 * shop 39 (30 Aug), all from its default address. Only that address, only guests, only ones
 * nobody has put in spam yet.
 */
class SpamScannerFloodChatSessions
{
    use AsAction;

    private const string SCANNER_EMAIL = 'sample@email.tst';

    public string $commandSignature = 'chat:spam_scanner_flood {--apply : Mark them as spam, otherwise only count}';

    public function handle(ChatSession $chatSession): void
    {
        $chatSession->update([
            'is_spam' => true,
            'spam_at' => now(),
        ]);

        StoreChatEvent::make()->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::SPAM,
            actorType: ChatActorTypeEnum::SYSTEM,
            payload: [
                'action_type' => 'spam',
                'note'        => 'HELP-3467 vulnerability scanner flood',
                'marked_at'   => now()->toISOString(),
            ]
        );
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $query = ChatSession::whereRaw("metadata->>'email' = ?", [self::SCANNER_EMAIL])
            ->whereNull('web_user_id')
            ->where('is_spam', false);

        $perShop = (clone $query)->selectRaw('shop_id, count(*) as sessions')->groupBy('shop_id')->pluck('sessions', 'shop_id');

        foreach ($perShop as $shopId => $sessions) {
            $command->line("shop $shopId: $sessions sessions");
        }

        if (!$command->option('apply')) {
            $command->info('Dry run, pass --apply to mark them as spam');

            return 0;
        }

        $marked = 0;
        $query->orderBy('id')->chunkById(100, function ($chatSessions) use (&$marked) {
            foreach ($chatSessions as $chatSession) {
                $this->handle($chatSession);
                $marked++;
            }
        });

        $command->info("Marked $marked sessions as spam");

        return 0;
    }
}
