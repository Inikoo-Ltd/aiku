<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:50:05 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Luigi;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;

class ReindexWebpageLuigi extends OrgAction implements ShouldBeUnique
{
    public string $jobQueue = 'low-priority';

    public int $jobTries = 1;

    public function getJobUniqueId(Webpage $webpage): string
    {
        return $webpage->id;
    }

    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage): array
    {
        return ReindexWebpageLuigiData::run($webpage->id);
    }


    public function jsonResponse(array $response): array
    {
        return $response;
    }

    /**
     * @throws \Exception
     */
    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }
}
