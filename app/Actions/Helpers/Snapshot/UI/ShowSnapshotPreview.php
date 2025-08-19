<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot\UI;

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
use App\Http\Resources\Helpers\SnapshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

class ShowSnapshotPreview extends OrgAction
{

    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->initialisationFromShop($shop, $request);

        return $snapshot;
    }

    public function htmlResponse(Snapshot $snapshot, ActionRequest $request): Response
    {
        $website = $snapshot->parent->website;

        return Inertia::render(
            'Web/PreviewWorkshop',
            [
                'webpage' => SnapshotResource::make($snapshot)->getArray(),
                'header' => GetWebsiteWorkshopHeader::run($website),
                'footer' => GetWebsiteWorkshopFooter::run($website),
                'navigation' => GetWebsiteWorkshopMenu::run($website),
                'layout' => Arr::get($website->published_layout, 'theme'),
            ]
        );
    }
}
