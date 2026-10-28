<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A shop fact an independent reviewer checked against the tables or the code, kept with its
 * evidence so the next sweep cites it instead of working it out again. No person approves it, so
 * no single chat can make it trusted: it stays a candidate until checked while working two
 * different chats, which a message planted by one customer cannot do. It is then trusted until it
 * ages out, a draft that cited it is discarded or rated as a wrong fact, or staff remove it from
 * the Verified facts tab; checking it again (same title) brings it back, except after staff
 * removed it. Facts about one order, payment, parcel, balance or stock level never belong here:
 * they change by the hour.
 */
class RememberVerifiedChatFact
{
    use AsAction;

    public const string SOURCE = 'verified';

    public const int TRUSTED_DAYS = 30;

    public const int CHATS_TO_TRUST = 2;

    private const float CUSTOMER_WORDS = 0.7;

    public function handle(ChatSession|MetaChatSession $chatSession, User $user, string $title, string $fact, string $evidence, ?int $runId = null): ChatKnowledgeEntry
    {
        $shop  = $chatSession->shop;
        $entry = self::facts($shop)->whereRaw('lower(title) = ?', [mb_strtolower($title)])->first();

        $verification = array_filter([
            'evidence' => $evidence,
            'user_id'  => $user->id,
            'chat'     => $chatSession->ulid,
            'run_id'   => $runId,
            'at'       => now()->toIso8601String(),
        ]);

        $verifications = array_slice([...Arr::get($entry?->evidence ?? [], 'verifications', []), $verification], -10);

        $attributes = [
            'title'        => $title,
            'body'         => $fact,
            'status'       => count(array_unique(array_column($verifications, 'chat'))) >= self::CHATS_TO_TRUST ? 'active' : 'candidate',
            'is_active'    => true,
            'hydrated_at'  => now(),
            'last_seen_at' => now(),
            'expires_at'   => now()->addDays(self::TRUSTED_DAYS),
            'conflict'     => null,
        ];

        if ($entry) {
            $entry->update($attributes + ['evidence' => [...$entry->evidence ?? [], 'verifications' => $verifications]]);

            return $entry;
        }

        return ChatKnowledgeEntry::create($attributes + [
            'group_id'           => $shop->group_id,
            'organisation_id'    => $shop->organisation_id,
            'shop_id'            => $shop->id,
            'kind'               => self::SOURCE,
            'source_type'        => self::SOURCE,
            'created_by_user_id' => $user->id,
            'evidence'           => ['verifications' => $verifications, 'disputes' => []],
        ]);
    }

    /**
     * Why a fact may not be stored: staff removed it, or it mostly repeats what the customer wrote
     * in this chat, which is how an instruction hidden in a message would try to become a rule.
     */
    public static function refusal(ChatSession|MetaChatSession $chatSession, string $title, string $fact): ?string
    {
        if (self::facts($chatSession->shop)->whereRaw('lower(title) = ?', [mb_strtolower($title)])->where('status', 'removed')->exists()) {
            return 'Staff removed this fact from Verified facts; it cannot be stored again from here.';
        }

        $customerWrote = $chatSession->messages()->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])->latest('id')->limit(50)
            ->get(['message_text', 'original_text'])
            ->map(fn ($message) => $message->message_text.' '.$message->original_text)
            ->implode(' ');

        if (SettleChatAiDraft::draftWordsKept($fact, $customerWrote) >= self::CUSTOMER_WORDS) {
            return 'This fact mostly repeats what the customer wrote. A fact must come from the tables, the code or our own pages, never from a customer message.';
        }

        return null;
    }

    /**
     * Staff threw away, or rated as a wrong fact, a draft built on these facts: they stop being
     * trusted until a reviewer checks them again. A fact checked again after the draft was written
     * is not disputed by it.
     *
     * @param  array<int, int>  $factIds
     */
    public static function dispute(Shop $shop, array $factIds, string $reason, ?ChatAiDraft $draft = null, ?int $userId = null): int
    {
        $disputed = 0;

        foreach (self::facts($shop)->whereIn('id', $factIds)->where('status', 'active')->get() as $entry) {
            if ($draft && $entry->hydrated_at?->gt($draft->created_at)) {
                continue;
            }

            $entry->update([
                'status'   => 'disputed',
                'conflict' => $reason,
                'evidence' => [
                    ...$entry->evidence ?? [],
                    'disputes' => array_slice([...Arr::get($entry->evidence, 'disputes', []), array_filter([
                        'reason'   => $reason,
                        'draft_id' => $draft?->id,
                        'user_id'  => $userId,
                        'at'       => now()->toIso8601String(),
                    ])], -10),
                ],
            ]);
            $disputed++;
        }

        return $disputed;
    }

    public static function setStatusByStaff(ChatKnowledgeEntry $fact, string $status, int $userId): void
    {
        $fact->update([
            'status'   => $status,
            'conflict' => $status === 'removed' ? 'Removed by staff' : null,
            'evidence' => [
                ...$fact->evidence ?? [],
                'disputes' => array_slice([...Arr::get($fact->evidence, 'disputes', []), [
                    'reason'  => $status === 'removed' ? 'Removed by staff' : 'Restored by staff, to be checked again',
                    'user_id' => $userId,
                    'at'      => now()->toIso8601String(),
                ]], -10),
            ],
        ]);
    }

    public static function disputeFactsOf(ChatAiDraft $draft, string $reason, ?int $userId): void
    {
        $factIds = data_get($draft->facts, 'facts_used', []);

        if ($factIds && $draft->shop) {
            self::dispute($draft->shop, $factIds, $reason, $draft, $userId);
        }
    }

    /**
     * @return Builder<ChatKnowledgeEntry>
     */
    public static function trusted(Shop $shop): Builder
    {
        return self::facts($shop)->where('status', 'active')->where('expires_at', '>', now());
    }

    /**
     * @return Builder<ChatKnowledgeEntry>
     */
    public static function facts(Shop $shop): Builder
    {
        return ChatKnowledgeEntry::where('shop_id', $shop->id)->where('source_type', self::SOURCE);
    }
}
