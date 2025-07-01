<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Feb 2024 23:43:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateDeployments;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateRedirects;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateChildWebpages;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateSnapshots;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateWebUserRequests;
use App\Models\Web\Webpage;

class HydrateWebpage
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:webpages {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Webpage::class;
    }

    public function handle(Webpage $webpage): void
    {
        WebpageHydrateChildWebpages::run($webpage);
        WebpageHydrateRedirects::run($webpage);
        WebpageHydrateSnapshots::run($webpage);
        WebpageHydrateDeployments::run($webpage);
        WebpageHydrateWebUserRequests::run($webpage->id);
    }


}
