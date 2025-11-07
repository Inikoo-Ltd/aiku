<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 04 Nov 2025 09:37:22 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTags extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Tags/Tags',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Tags'),
                'pageHeading' => [
                    'title'  => __('Tags'),
                    'icon'   => [
                        'title' => __('Tags'),
                        'icon'  => ['fal', 'fa-tags'],
                    ],
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Create Tag'),
                            'label'   => __('Create Tag'),
                            'route'   => [
                                'name'       => 'grp.org.tags.create',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                ],
                            ],
                        ],
                    ],
                ],
                'data'        => TagsResource::collection(IndexTags::run($this->organisation, __('Tags'))),
            ],
        )->table(
            IndexTags::make()->tableStructure(
                prefix: 'Tags'
            ),
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.tags.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Tags'),
                    ],
                ],
            ],
        );
    }
}
