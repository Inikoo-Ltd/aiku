<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Tag\Hydrators;

use App\Models\Catalogue\Tag;
use Lorisleiva\Actions\Concerns\AsAction;

class TagHydrateModels
{
    use AsAction;

    public function handle(Tag $tag): void
    {
        $stats = [
            'number_models' => $tag->tradeUnits()->count(),
        ];

        $tag->updateQuietly($stats);
    }


}
