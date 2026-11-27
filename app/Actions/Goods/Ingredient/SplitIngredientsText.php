<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 11:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Ingredient;

use Lorisleiva\Actions\Concerns\AsObject;

class SplitIngredientsText
{
    use AsObject;

    /**
     * Splits an ingredients list at the commas outside brackets, so "Aqua (Water, Eau)" stays whole.
     * A stray ")" (lists broken by old imports end in "CI 45430)") does not stop the splitting.
     *
     * @return array<int, string>
     */
    public function handle(string $text): array
    {
        $parts = [];
        $part  = '';
        $depth = 0;

        foreach (mb_str_split($text) as $character) {
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')' && $depth > 0) {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $parts[] = $part;
                $part    = '';
                continue;
            }
            $part .= $character;
        }
        $parts[] = $part;

        return $parts;
    }
}
