<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\WithInertia;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSnapshot extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithWebAuthorisation;
    // use WithWebpageSubNavigation;


    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->initialisationFromShop($shop, $request);

        return $snapshot;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebpageTabsEnum::values());

        return $snapshot;
    }

    public function htmlResponse(Snapshot $snapshot, ActionRequest $request): Response
    {
        // $subNavigation = $this->getWebpageNavigation($snapshot->parent->website);

        return Inertia::render(
            'Org/Web/SnapshotWebpageShowcase',
            [
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->getName(),
                //     $request->route()->originalParameters()
                // ),
                'title'       => __('snapshot'),
                'pageHead'    => [
                    'title'         => $snapshot->label ?? __('Snapshot ').$snapshot->parent->code,
                    'afterTitle'    => [
                        'label' => '../'.$snapshot->parent->url,
                    ],
                    'icon'          => [
                        'title' => __('snapshot'),
                        'icon'  => 'fal fa-browser'
                    ],
                    // 'subNavigation' => $subNavigation,
                ],
                'data' => SnapshotResource::make($snapshot)->resolve()
            ]
        );
    }
}
