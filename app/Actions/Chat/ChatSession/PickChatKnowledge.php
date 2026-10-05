<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 02:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\AskJev;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatKnowledgeEntry;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The few knowledge base entries that answer what the customer asks, chosen by Jev from the
 * shop's entries by their titles and first lines, so the model that writes the answer reads a
 * handful of short notes instead of whole pages. Staff notes are offered first.
 */
class PickChatKnowledge
{
    use AsAction;

    private const int MAX_PICKED = 3;

    private const float MIN_PROBABILITY = 0.15;

    /**
     * @return array<int, array{id: int, title: string, url: string|null, text: string, manual: bool}>
     */
    public function handle(Shop $shop, string $customerWrote, string $weSaid): array
    {
        $entries = ChatKnowledgeEntry::forShop($shop)->orderByDesc('is_manual')->orderBy('id')->limit(250)->get()->keyBy('id');

        if ($entries->isEmpty()) {
            return [];
        }

        $answer = AskJev::make()->handle(
            ['we_said' => mb_substr($weSaid, 0, 1500), 'customer_wrote' => mb_substr($customerWrote, 0, 4000)],
            ['entry' => [
                'type'         => 'choice',
                'instructions' => 'Customer service of a wholesale giftware supplier. Which of our notes answers what the customer asks now?',
                'criteria'     => [
                    ...$entries->mapWithKeys(fn (ChatKnowledgeEntry $entry) => ['e'.$entry->id => $entry->title.': '.mb_substr($entry->body, 0, 160)])->all(),
                    'none' => 'None of these notes answers it',
                ],
            ]]
        );

        return collect(Arr::get($answer ?? [], 'entry.probabilities', []))
            ->except('none')
            ->filter(fn ($probability) => (float) $probability >= self::MIN_PROBABILITY)
            ->sortDesc()
            ->take(self::MAX_PICKED)
            ->keys()
            ->map(fn (string $key) => $entries->get((int) substr($key, 1)))
            ->filter()
            ->map(fn (ChatKnowledgeEntry $entry) => [
                'id'     => $entry->id,
                'title'  => $entry->title,
                'url'    => $entry->url,
                'text'   => $entry->body,
                'manual' => $entry->is_manual,
            ])
            ->values()
            ->all();
    }
}
