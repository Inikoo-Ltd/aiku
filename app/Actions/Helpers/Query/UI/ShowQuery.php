<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:04 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Query\UI;

use App\Actions\InertiaAction;
use App\Actions\UI\Customer\Portfolio\ShowPortfolio;
use App\Http\Resources\Helpers\QueryResource;
use App\Models\Helpers\Query;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowQuery extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('portfolio.banners.view');
    }

    public function handle(Query $query): Query
    {
        return $query;
    }

    public function asController(Query $query): Query
    {
        return $this->handle($query);
    }

    public function htmlResponse(Query $query, ActionRequest $request): Response
    {
        return Inertia::render(
            'Query/Query',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('images'),
                'pageHead' => [
                    'title'     => __('images'),
                    'iconRight' => [
                        'title' => __('Image'),
                        'icon'  => 'fal fa-images'
                    ],
                ],
                'data' => new QueryResource($query)
            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('images'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'customer.portfolio.images.index' =>
            array_merge(
                ShowPortfolio::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'portfolio.images.index',
                        null
                    ]
                ),
            ),

            default => []
        };
    }
}
