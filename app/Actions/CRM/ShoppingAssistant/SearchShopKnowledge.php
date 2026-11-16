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
 * The question can be searched in more than one wording (the customer's words and an English
 * version): a short question in another language sometimes misses a fact an English wording finds
 * (INI-257). Each entry keeps its nearest distance over the wordings.
 *
 * Null means the search is not available (an empty question, no embeddings key, or the provider
 * failed): the caller must not treat it as "nothing written about this".
 */
class SearchShopKnowledge
{
    use AsAction;

    private const int MAX_QUESTION = 500;

    public const int LIMIT = 6;

    /**
     * @param  string|array<int, string>  $questions
     * @return ?Collection<int, ChatKnowledgeEntry>
     */
    public function handle(Shop $shop, string|array $questions, int $limit = self::LIMIT): ?Collection
    {
        $questions = array_values(array_unique(array_filter(array_map(fn (string $question) => mb_substr(trim($question), 0, self::MAX_QUESTION), (array) $questions))));

        if (!$questions) {
            return null;
        }

        $vectors = EmbedTexts::run($questions);

        if (!$vectors || in_array(null, $vectors, true)) {
            return null;
        }

        return collect($vectors)
            ->flatMap(fn (array $vector) => ChatKnowledgeEntry::forShop($shop)
                ->whereNotNull('embedding')
                ->select('chat_knowledge_entries.*')
                ->selectVectorDistance('embedding', $vector, 'distance')
                ->orderByVectorDistance('embedding', $vector)
                ->limit($limit)
                ->get())
            ->sortBy('distance')
            ->unique('id')
            ->take($limit)
            ->values();
    }
}
