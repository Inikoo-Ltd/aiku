<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateCompetitor extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithCompetitorForm;
    use WithCompetitorsSubNavigation;

    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function handle(Group $group): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => array_merge(
                    $this->getCompetitorsBreadcrumbs('grp.masters.competitors.index', __('Competitors')),
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
                                'name'       => 'grp.masters.competitors.index',
                                'parameters' => [],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->competitorBlueprint($group),
                    'route'     => [
                        'name'       => 'grp.models.competitor.store',
                        'parameters' => [],
                    ],
                ],
            ]
        );
    }
}
