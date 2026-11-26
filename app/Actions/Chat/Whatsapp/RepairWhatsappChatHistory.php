<?php

/*
 * Author: Eka Yudianta
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\Whatsapp\Concerns\MatchesWhatsappHistory;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatSession;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Before INI-188's fix the history import stored a second copy of messages already in the
 * conversation and left types it could not read as empty bubbles. This removes the copies
 * (soft delete, the live message is kept) and gives the empty ones their words.
 */
class RepairWhatsappChatHistory
{
    use AsAction;
    use MatchesWhatsappHistory;

    public string $commandSignature = 'chat:repair_whatsapp_history {shop? : shop id or slug} {--d|dry-run}';

    public string $commandDescription = 'Remove WhatsApp Business app history copies of messages already in the conversation and fill history messages stored without text';

    /**
     * @return array{duplicates: list<int>, edits: list<int>, texts: list<int>}
     */
    public function handle(?Shop $shop = null, bool $dryRun = false): array
    {
        $result = ['duplicates' => [], 'edits' => [], 'texts' => []];
        $import = ImportWhatsappChatHistory::make();

        $shopIds = $shop
            ? [$shop->id]
            : Shop::where('settings->whatsapp->mode', 'coexistence')->pluck('id')->all();

        $sessions = MetaChatSession::withTrashed()
            ->whereIn('shop_id', $shopIds)
            ->whereHas('messages', fn ($query) => $query->where('metadata->source', self::WHATSAPP_HISTORY_SOURCE))
            ->get();

        foreach ($sessions as $metaChatSession) {
            $liveKeys = $this->liveWaMessageKeys($metaChatSession);

            $historyMessages = $metaChatSession->messages()
                ->where('metadata->source', self::WHATSAPP_HISTORY_SOURCE)
                ->orderBy('created_at')
                ->get();

            foreach ($historyMessages as $message) {
                $key = static::waMessageKey($message->meta_message_id);

                if (($key && $liveKeys->has($key))
                    || $this->findLiveCopy($metaChatSession, $this->isStaffSender($message->sender_type), $message->message_text, $message->created_at, $message->id)) {
                    $result['duplicates'][] = $message->id;
                    $dryRun || $message->delete();

                    continue;
                }

                if (filled($message->message_text)) {
                    continue;
                }

                $type    = (string) Arr::get($message->metadata, 'wa_type');
                $payload = Arr::get($message->metadata, 'wa_payload');
                $text    = $import->historyText($type, ['type' => $type, $type => $payload], $payload);

                if ($type === 'edit' && $import->findEditedMessage($metaChatSession, (array) $payload)) {
                    $result['edits'][] = $message->id;

                    if (!$dryRun && $import->applyEdit($metaChatSession, (array) $payload, $text, $message->created_at)) {
                        $message->delete();
                    }

                    continue;
                }

                $result['texts'][] = $message->id;
                $dryRun || $message->update(['message_text' => $text]);
            }
        }

        return $result;
    }

    /**
     * @return Collection<string, true>
     */
    protected function liveWaMessageKeys(MetaChatSession $metaChatSession): Collection
    {
        return $metaChatSession->messages()
            ->whereNotNull('meta_message_id')
            ->where(fn ($query) => $query->whereNull('metadata->source')->orWhere('metadata->source', '!=', self::WHATSAPP_HISTORY_SOURCE))
            ->pluck('meta_message_id')
            ->map(fn (string $waMessageId) => static::waMessageKey($waMessageId))
            ->filter()
            ->mapWithKeys(fn (string $key) => [$key => true]);
    }

    public function asCommand(Command $command): int
    {
        $shop = null;

        if ($argument = $command->argument('shop')) {
            $shop = Shop::where('slug', $argument)->orWhere('id', is_numeric($argument) ? (int) $argument : 0)->first();

            if (!$shop) {
                $command->error("Shop $argument not found");

                return 1;
            }
        }

        $dryRun = (bool) $command->option('dry-run');
        $result = $this->handle($shop, $dryRun);

        $command->table(['', 'count', 'message ids'], [
            [$dryRun ? 'duplicates to delete' : 'duplicates deleted', count($result['duplicates']), implode(', ', $result['duplicates'])],
            [$dryRun ? 'edits to apply' : 'edits applied', count($result['edits']), implode(', ', $result['edits'])],
            [$dryRun ? 'empty texts to fill' : 'empty texts filled', count($result['texts']), implode(', ', $result['texts'])],
        ]);

        return 0;
    }
}
