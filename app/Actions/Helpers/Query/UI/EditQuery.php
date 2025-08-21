<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Query\UI;

use App\Actions\InertiaAction;
use App\Models\Helpers\Query;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditQuery extends InertiaAction
{
    public function handle(Query $query): Query
    {
        return $query;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("portfolio.edit");

    }

    public function asController(Query $query, ActionRequest $request): Query
    {
        $this->initialisation($request);

        return $this->handle($query);
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Query $query, ActionRequest $request): Response
    {
        $sections['properties'] = [
            'label'  => __('query properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'username' => [
                    'type'     => 'input',
                    'label'    => __('name'),
                    'required' => true,
                    'value'    => $query->name
                ],
                'base' => [
                    'type'     => 'input',
                    'label'    => __('base'),
                    'required' => true,
                    'value'    => $query->base
                ],
                'filters' => [
                    'type'     => 'input',
                    'label'    => __('filters'),
                    'required' => true,
                    'value'    => $query->filters
                ]
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') && Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __("Query"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation' => [
                    'previous' => $this->getPrevious($query, $request),
                    'next'     => $this->getNext($query, $request),
                ],
                'pageHead' => [
                    'title' => $query->name,
                    'icon'  => [
                        'title' => __('query'),
                        'icon'  => 'fal fa-globe'
                    ],


                    'iconRight' =>
                        [
                            'icon'  => ['fal', 'fa-edit'],
                            'title' => __("Editing query")
                        ],

                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData' => [
                    'current'   => $currentSection,
                    'blueprint' => $sections,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'org.models.query.update',
                            'parameters' => $query->slug
                        ],
                    ]
                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return [];
    }

    public function getPrevious(Query $query, ActionRequest $request): ?array
    {
        $previous = Query::where('slug', '<', $query->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Query $query, ActionRequest $request): ?array
    {
        $next = Query::where('slug', '>', $query->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Query $query, string $routeName): ?array
    {
        if (!$query) {
            return null;
        }

        return match ($routeName) {
            'customer.portfolio.social-accounts.edit' => [
                'label' => $query->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'portfolioSocialAccount' => $query->slug
                    ]
                ]
            ]
        };
    }
}
