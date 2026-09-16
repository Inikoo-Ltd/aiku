<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Which ad group a new keyword most likely belongs in, by matching words against the group's name.
 *
 * A campaign can carry dozens of ad groups. One here has twenty-seven, and asking a marketer to pick
 * from a list that long for every suggestion is the kind of friction that empties a queue: the cards
 * stop being a decision and become data entry.
 *
 * Word overlap only, and nothing clever. Ad groups named "Incense Dropship" and "Bags Dropship" catch
 * `incense` and `bags` outright, while `selenite` matches nothing in "Esoteric and Gemstone Dropship"
 * and is honestly left unanswered for a person to place. A wrong default is worse than none, because
 * a default is what gets accepted without reading.
 */
class SuggestAdGroupForKeyword
{
    use AsAction;

    /** Words that appear in half the ad group names and say nothing about what belongs where. */
    private const array IGNORED = ['dropship', 'dropshipping', 'uk', 'general', 'shop', 'supplier', 'suppliers', 'free', 'and', 'the', 'phrase', 'exact', 'broad'];

    /**
     * @param array<int, array> $adGroups
     */
    public function handle(string $term, array $adGroups): ?string
    {
        if (count($adGroups) === 1) {
            return (string) Arr::get($adGroups, '0.id');
        }

        $wanted = $this->words($term);

        if ($wanted === []) {
            return null;
        }

        $best      = null;
        $bestScore = 0;

        foreach ($adGroups as $group) {
            $score = count(array_intersect($wanted, $this->words((string) Arr::get($group, 'name'))));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = (string) Arr::get($group, 'id');
            }
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function words(string $value): array
    {
        return collect(preg_split('/[^a-z0-9]+/i', mb_strtolower($value)))
            ->filter(fn (string $word) => mb_strlen($word) > 2 && !in_array($word, self::IGNORED, true))

            /* A crude singular, so `bags` finds "Bags Dropship" and `candles` finds "Candle Dropship". */
            ->map(fn (string $word) => rtrim($word, 's'))
            ->unique()
            ->values()
            ->all();
    }
}
