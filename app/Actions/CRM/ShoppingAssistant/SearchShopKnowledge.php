<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\AI\EmbedTexts;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatKnowledgeEntry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The texts of a shop's knowledge base (its own pages and settings, staff notes, verified facts and
 * approved learned rules, plus those of its organisation and group) nearest in meaning to what a
 * customer asked, nearest first, each with its cosine `distance`.
 *
 * By meaning, not by words: customers write in any language, and the pages are cut into sections
 * with poor titles, so a keyword match would miss most questions. The scan is exact because a shop
 * has tens of entries. There is no distance threshold: the answering model decides whether the
 * texts answer the question, and AskShoppingAssistant checks that the answer cites one of them.
 *
 * Null means the search is not available (an empty question, no embeddings key, or the provider
 * failed): the caller must not treat it as "nothing written about this".
 */
class SearchShopKnowledge
{
    use AsAction;

    private const int MAX_QUESTION = 500;

    /**
     * @return ?Collection<int, ChatKnowledgeEntry>
     */
    public function handle(Shop $shop, string $question, int $limit = 4): ?Collection
    {
        $question = mb_substr(trim($question), 0, self::MAX_QUESTION);

        if ($question === '') {
            return null;
        }

        $vector = EmbedTexts::run([$question])[0] ?? null;

        if ($vector === null) {
            return null;
        }

        return ChatKnowledgeEntry::forShop($shop)
            ->whereNotNull('embedding')
            ->select('chat_knowledge_entries.*')
            ->selectVectorDistance('embedding', $vector, 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->limit($limit)
            ->get();
    }
}
