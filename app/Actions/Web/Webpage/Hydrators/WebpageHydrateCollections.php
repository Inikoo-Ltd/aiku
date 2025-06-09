<?php
/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-15h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Web\Webpage;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateCollections
{
    use AsAction;
    use WithEnumStats;

    private Webpage $webpage;

    public function __construct(Webpage $webpage)
    {
        $this->webpage = $webpage;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->webpage->id))->dontRelease()];
    }

    public function handle(Webpage $webpage): void
    {
        $stats = [
            'number_collections' => $webpage->webpageHasCollections()->count(),
        ];

        $webpage->stats()->update($stats);
    }


}
