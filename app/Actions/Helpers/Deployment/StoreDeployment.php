<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Oct 2023 21:33:09 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Deployment;

use App\Actions\Web\Webpage\Hydrators\WebpageHydrateDeployments;
use App\Models\Announcement;
use App\Models\Comms\Email;
use App\Models\Comms\EmailTemplate;
use App\Models\Helpers\Deployment;
use App\Models\Web\Banner;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreDeployment
{
    use AsAction;

    public function handle(Website|Webpage|EmailTemplate|Banner|Email|Announcement $model, array $modelData): Deployment
    {

        /** @var Deployment $deployment */
        $deployment = $model->deployments()->create($modelData);
        $deployment->saveQuietly();

        if ($deployment->model_type == 'Webpage') {
            WebpageHydrateDeployments::dispatch($model);
        }


        return $deployment;
    }
}
