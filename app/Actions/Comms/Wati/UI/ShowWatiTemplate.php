<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Wati\UI;

use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingAuthorisation;
use App\Http\Resources\Comms\WatiTemplateResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WatiTemplate;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWatiTemplate extends OrgAction
{
    use HasUIMailshots;
    use WithMarketingAuthorisation;

    public function handle(WatiTemplate $watiTemplate): WatiTemplate
    {
        return $watiTemplate;
    }

    public function asController(
        Organisation $organisation,
        Shop $shop,
        WatiTemplate $watiTemplate,
        ActionRequest $request
    ): WatiTemplate {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($watiTemplate);
    }

    public function htmlResponse(WatiTemplate $watiTemplate, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/WatiTemplate',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $watiTemplate
                ),
                'title'    => $watiTemplate->element_name,
                'pageHead' => [
                    'title' => $watiTemplate->element_name,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comment-alt-lines'],
                        'title' => __('Wati Template'),
                    ],
                    'model' => __('Wati Template'),
                ],
                'data' => WatiTemplateResource::make($watiTemplate),
            ]
        );
    }
}
