<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 May 2023 12:18:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\InertiaAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\Web\WebsiteResource;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWebsitesFooter extends InertiaAction
{
    public function handle(Website $website): Website
    {
        return $website;
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): array
    {
        return  $modelOperations;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('websites.edit');
        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->authTo('websites.view')
            );
    }


    public function jsonResponse(Website  $website): AnonymousResourceCollection
    {
        return WebsiteResource::collection($this->handle($website));
    }


    public function htmlResponse(LengthAwarePaginator $websites, ActionRequest $request): Response
    {
        return Inertia::render(
            'Web/Websitefooter',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Websites footer'),
                'pageHead'    => [
                    'title'  => __('websites footer'),
                ],
            ]
        );
    }


    public function asController(Website $website, ActionRequest $request): Website
    {

        $this->initialisation($request);
        return $this->handle($website);
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
                        'label' => __('Websites footer'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'websites.footer.index' =>
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'websites.footer.index',
                        null
                    ]
                ),
            ),

            default => []
        };
    }
}
