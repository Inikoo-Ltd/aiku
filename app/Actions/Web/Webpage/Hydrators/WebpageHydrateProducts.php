<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-13h-17m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Webpage $webpage): string
    {
        return $webpage->id;
    }

    public function handle(Webpage $webpage): void
    {
        $stats = [
            'number_products' => $webpage->webpageHasProducts()->count(),
        ];

        $webpage->stats()->update($stats);
    }


}
