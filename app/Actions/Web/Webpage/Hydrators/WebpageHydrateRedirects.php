<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateRedirects implements ShouldBeUnique
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
            'number_redirects' => $webpage->redirects()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'redirects',
                field: 'type',
                enum: RedirectTypeEnum::class,
                models: Redirect::class,
                where: function ($q) use ($webpage) {
                    $q->where('webpage_id', $webpage->id);
                }
            )
        );

        $webpage->stats()->update($stats);
    }
}
