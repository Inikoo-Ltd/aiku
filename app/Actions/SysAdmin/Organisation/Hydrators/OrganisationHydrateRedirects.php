<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Redirect;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateRedirects implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_redirects' => $organisation->redirects()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'redirects',
                field: 'type',
                enum: RedirectTypeEnum::class,
                models: Redirect::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        $organisation->webStats()->update($stats);
    }
}
