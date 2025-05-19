<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Website\GetWebsiteWorkshopFooter;
use App\Actions\Web\Website\GetWebsiteWorkshopHeader;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Web\Website\GetWebsiteWorkshopMenu;
use Illuminate\Support\Arr;

class ShowWebsitePreview extends OrgAction
{
    public function asController(Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisation($website->organisation, $request);

        return $webpage;
    }

    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {
        $website = $webpage->website;

        return Inertia::render(
            'Web/PreviewWorkshop',
            [
                'webpage' => WebpageResource::make($webpage)->getArray(),
                'header' => GetWebsiteWorkshopHeader::run($website),
                'footer' => GetWebsiteWorkshopFooter::run($website),
                'navigation' => GetWebsiteWorkshopMenu::run($website),
                'layout' => Arr::get($website->published_layout, 'theme'),
            ]
        );
    }
}
