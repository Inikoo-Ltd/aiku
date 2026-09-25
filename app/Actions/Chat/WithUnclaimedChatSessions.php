<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * A conversation nobody has claimed past the time agreed for its channel. Unowned rather than
 * slow: last week's unanswered Bulgarian, Spanish and French chats all reached a queue nobody
 * who could answer was watching, and nothing took them out of it.
 *
 * Claimed means an assignment somebody currently holds. A conversation stored as active whose
 * agent went home holds nobody, so it belongs here as much as one that was never picked up.
 *
 * The two tables ask the same question with different columns, and both answers are built here
 * so the queue, the badge and the alert can never disagree about what is in it.
 */
trait WithUnclaimedChatSessions
{
    public static function unclaimedAfterSeconds(string $channel): int
    {
        return (int) config("chat.unclaimed.after_seconds.$channel", 0);
    }

    /**
     * The shops that set their own time for this channel. Everything else runs on the group's
     * number, so a shop that never had an opinion has nothing stored and follows any change to
     * the default without being touched.
     *
     * ponytail: read once a minute rather than on every poll, and not invalidated on save. A
     * changed time taking a minute to bite is nothing; if that ever matters, forget the key in
     * UpdateShop.
     *
     * @return array<int, int>
     */
    protected function unclaimedShopOverrides(string $channel): array
    {
        return Cache::remember("chat:unclaimed:overrides:$channel", 60, fn () => Shop::query()
            ->whereNotNull('settings')
            ->pluck('settings', 'id')
            ->map(fn ($settings) => Arr::get((array) $settings, "chat.unclaimed_after_seconds.$channel"))
            ->filter(fn ($seconds) => is_numeric($seconds) && (int) $seconds > 0)
            ->map(fn ($seconds) => (int) $seconds)
            ->all());
    }

    /**
     * @param  Builder<ChatSession>  $query
     */
    public function scopeUnclaimedChatSessions(Builder $query): void
    {
        // The conditions every list already applies, repeated rather than inherited: a queue
        // that promised work sitting in sessions nobody may see would be worse than no queue.
        $query->whereHas('messages')
            ->where('is_spam', false)
            ->where('is_rubbish', false)
            ->where('is_carrier', false)
            ->whereIn('status', [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value])
            ->whereDoesntHave('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->where(function ($outer) {
                foreach (ChatChannelEnum::cases() as $channel) {
                    $outer->orWhere(function ($q) use ($channel) {
                        $q->where('channel', $channel->value);
                        $this->pastItsTime($q, $channel->value);
                    });
                }
            });
    }

    /**
     * @param  Builder<MetaChatSession>  $query
     */
    public function scopeUnclaimedMetaChatSessions(Builder $query): void
    {
        $query->where('is_spam', false)
            ->whereNotNull('last_visitor_message_at')
            ->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
            ->whereDoesntHave('assignments', fn ($a) => $a->where('status', ChatAssignmentStatusEnum::ACTIVE->value));

        $this->pastItsTime($query, 'whatsapp');
    }

    public function unclaimedChatSessions(): Builder
    {
        $query = ChatSession::query();
        $this->scopeUnclaimedChatSessions($query);

        return $query;
    }

    public function unclaimedMetaChatSessions(): Builder
    {
        $query = MetaChatSession::query();
        $this->scopeUnclaimedMetaChatSessions($query);

        return $query;
    }

    /**
     * Waiting longer than its shop allows on this channel: the group's time for every shop that
     * has not set its own, and each of those shops' own time for the few that have.
     *
     * @param  Builder<ChatSession|MetaChatSession>  $query
     */
    private function pastItsTime(Builder $query, string $channel): void
    {
        $overrides = $this->unclaimedShopOverrides($channel);

        $query->where(function ($outer) use ($overrides, $channel) {
            $outer->where(function ($q) use ($overrides, $channel) {
                $q->whereNotIn('shop_id', array_keys($overrides));
                $this->waitingLongerThan($q, self::unclaimedAfterSeconds($channel));
            });

            foreach ($overrides as $shopId => $seconds) {
                $outer->orWhere(function ($q) use ($shopId, $seconds) {
                    $q->where('shop_id', $shopId);
                    $this->waitingLongerThan($q, $seconds);
                });
            }
        });
    }

    /**
     * The clock runs from the customer's last word, not from when the conversation was opened:
     * a thread answered this morning and written into again a minute ago has been waiting a
     * minute, and one opened without a word is waiting for nothing.
     *
     * @param  Builder<ChatSession|MetaChatSession>  $query
     */
    private function waitingLongerThan(Builder $query, int $seconds): void
    {
        $table = $query->getModel()->getTable();

        $query->whereRaw(
            "coalesce({$table}.last_visitor_message_at, {$table}.created_at) <= ?",
            [now()->subSeconds($seconds)]
        );
    }
}
