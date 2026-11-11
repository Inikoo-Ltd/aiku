<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\Competitor;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCompetitor extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithCompetitorForm;
    use WithCompetitorsSubNavigation;

    public function asController(Competitor $competitor, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($competitor);
    }

    public function handle(Competitor $competitor): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => array_merge(
                    $this->getCompetitorsBreadcrumbs('grp.masters.competitors.index', __('List')),
                    [['type' => 'simple', 'simple' => ['label' => $competitor->name]]]
                ),
                'title'       => __('Edit competitor').': '.$competitor->name,
                'pageHead'    => [
                    'title'   => $competitor->name,
                    'model'   => __('Edit competitor'),
                    'icon'    => ['title' => __('Competitor'), 'icon' => 'fal fa-binoculars'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.masters.competitors.show',
                                'parameters' => ['competitor' => $competitor->slug],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->competitorBlueprint($competitor->group, $competitor),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.competitor.update',
                            'parameters' => ['competitor' => $competitor->id],
                        ],
                    ],
                ],
            ]
        );
    }
}
