<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 01:01:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\HydrateModel;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateCloudflareData;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateGoogleCloudSearch;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateRedirects;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebUserRequests;
use App\Models\Web\Website;

class HydrateWebsite extends HydrateModel
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:websites {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Website::class;
    }

    public function handle(Website $website): void
    {
        WebsiteHydrateWebpages::run($website);
        WebsiteHydrateCloudflareData::run($website);
        WebsiteHydrateGoogleCloudSearch::run($website);
        WebsiteHydrateRedirects::run($website);
        WebsiteHydrateWebUserRequests::run($website->id);

    }


}
