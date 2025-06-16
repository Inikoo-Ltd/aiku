<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 12:58:04 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateDeployments implements ShouldBeUnique
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
            'number_deployments' => $webpage->deployments()->count(),
        ];

        $webpage->stats()->update($stats);
    }


}
