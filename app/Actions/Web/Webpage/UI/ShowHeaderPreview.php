<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Apr 2024 16:56:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Website\GetWebsiteWorkshopHeader;
use App\Actions\Web\Website\GetWebsiteWorkshopMenu;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Arr;

class ShowHeaderPreview extends OrgAction
{
    use AsAction;

    public function handle(Website $website): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'Web/PreviewWorkshop',
            [
                'header' => GetWebsiteWorkshopHeader::run($website),
                'navigation' => GetWebsiteWorkshopMenu::run($website),
                'layout' => Arr::get($website->published_layout, 'theme'),
            ]
        );
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisation($website->organisation, $request);

        return $website;
    }
}
