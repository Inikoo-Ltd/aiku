<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 21 Aug 2026 10:12:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use stdClass;

trait WithWebBlockReveal
{
    /**
     * @return array{key: string, close_label: string|null, scroll_to: bool}|null
     */
    public function getWebBlockReveal(object|array|null $reveal): ?array
    {
        if ($reveal instanceof stdClass) {
            $reveal = (array) $reveal;
        }

        if (!is_array($reveal) || !Arr::get($reveal, 'enabled', true) || !Arr::get($reveal, 'key')) {
            return null;
        }

        return [
            'key'         => Arr::get($reveal, 'key'),
            'close_label' => Arr::get($reveal, 'close_label') ?: null,
            'scroll_to'   => (bool) Arr::get($reveal, 'scroll_to', true),
        ];
    }

    public function getUniqueRevealKey(Webpage $webpage, string $revealKey): string
    {
        $usedRevealKeys = $webpage->webBlocks
            ->map(fn ($webBlock) => data_get($webBlock->layout, 'reveal.key'))
            ->filter()
            ->all();

        if (!in_array($revealKey, $usedRevealKeys, true)) {
            return $revealKey;
        }

        $suffix = 2;
        while (in_array($revealKey.'-'.$suffix, $usedRevealKeys, true)) {
            $suffix++;
        }

        return $revealKey.'-'.$suffix;
    }
}
