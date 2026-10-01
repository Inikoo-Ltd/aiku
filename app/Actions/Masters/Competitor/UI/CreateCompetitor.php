<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateCompetitor extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithCompetitorForm;

    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop);
    }

    public function handle(MasterShop $masterShop): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => array_merge(
                    ShowMasterShop::make()->getBreadcrumbs($masterShop),
                    [['type' => 'creatingModel', 'creatingModel' => ['label' => __('New competitor')]]]
                ),
                'title'       => __('New competitor'),
                'pageHead'    => [
                    'title'   => __('New competitor'),
                    'icon'    => ['title' => __('Competitor'), 'icon' => 'fal fa-binoculars'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.masters.master_shops.show',
                                'parameters' => ['masterShop' => $masterShop->slug, 'tab' => 'competitors'],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->competitorBlueprint($masterShop),
                    'route'     => [
                        'name'       => 'grp.models.master_shops.competitor.store',
                        'parameters' => ['masterShop' => $masterShop->id],
                    ],
                ],
            ]
        );
    }
}
