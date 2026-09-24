<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAiDraft;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether drafts on one topic in one shop have earned being sent without a person. Earned from
 * what staff did with them, never switched on by hand: enough drafts decided in the last 30
 * days, nearly all of them sent exactly as written, and no automatic answer flagged as wrong in
 * that time. One flag closes it again until the window has moved past it.
 */
class GetChatAutoSendGate
{
    use AsAction;

    /**
     * @return array{earned: bool, decided: int, used: int, used_share: float, flagged: int}
     */
    public function handle(Shop $shop, ChatTopicEnum $topic): array
    {
        $since = now()->subDays((int) config('chat.ai_auto_send.window_days'));

        $drafts = ChatAiDraft::where('shop_id', $shop->id)
            ->where('topic', $topic)
            ->where('created_at', '>=', $since);

        $counts = (clone $drafts)
            ->whereIn('status', [ChatAiDraftStatusEnum::USED, ChatAiDraftStatusEnum::EDITED, ChatAiDraftStatusEnum::DISCARDED])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $decided = (int) $counts->sum();
        $used    = (int) ($counts[ChatAiDraftStatusEnum::USED->value] ?? 0);
        $share   = $decided ? $used / $decided : 0.0;
        $flagged = (clone $drafts)->whereNotNull('flagged_wrong_at')->count();

        return [
            'earned'     => $decided >= (int) config('chat.ai_auto_send.min_decided')
                && $share >= (float) config('chat.ai_auto_send.min_used_share')
                && $flagged === 0,
            'decided'    => $decided,
            'used'       => $used,
            'used_share' => round($share, 3),
            'flagged'    => $flagged,
        ];
    }
}
