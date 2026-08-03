<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Github: aqordeon
 * Copyright (c) 2026, Vika Aqordi
 */

namespace App\Actions\DevOps\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\DevOps\AppDeploymentsResource;
use App\Models\DevOps\AppDeployment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexAppDeployments extends OrgAction
{
    public function handle(): LengthAwarePaginator
    {
        return AppDeployment::query()
            ->latest('id')
            ->paginate(perPage: 10)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('group-overview');
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle();
    }

    public function htmlResponse(LengthAwarePaginator $deployments, ActionRequest $request): Response
    {
        $title = __('Deployments');

        return Inertia::render(
            'Devops/Deploys',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-rocket'],
                        'title' => $title,
                    ],
                ],
                'deployments' => Inertia::scroll(fn () => AppDeploymentsResource::collection($deployments)),
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-rocket',
                        'route' => [
                            'name' => 'grp.deploys',
                        ],
                        'label' => __('Deployments'),
                    ],
                ],
            ]
        );
    }
}
