<?php

/*
 * author Louis Perez
 * created on 26-02-2026-13h-40m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\RetinaAction;
use App\Http\Resources\History\HistoryResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaVATValidationHistory extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle();
    }


    public function handle($prefix = null): LengthAwarePaginator
    {
        return IndexHistory::run($this->customer, eventScopeFilter: 'tax_number_validation');
    }

    public function jsonResponse(LengthAwarePaginator $userHistory): AnonymousResourceCollection
    {
        return HistoryResource::collection($userHistory);
    }

    public function htmlResponse(LengthAwarePaginator $userHistory, ActionRequest $request): Response
    {
        $title = __('VAT Validation History');

        return Inertia::render(
            'SysAdmin/RetinaVATValidationHistory',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName()),
                'title'       => $title,
                'pageHead'    => [
                    'title'   => $title,
                    'icon'    => [
                        'type' => 'icon',
                        'icon' => 'fal fa-history'
                    ],
                ],
                'data' => HistoryResource::collection($userHistory),
            ]
        )
        ->table(IndexHistory::make()->tableStructure());
    }


    public function getBreadcrumbs(string $routeName): array
    {
        return match ($routeName) {
            'retina.sysadmin.vat-validation-history' =>
            array_merge(
                ShowRetinaFulfilmentSysAdminDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.vat-validation-history',
                            ],
                            'label' => __('VAT Validation History'),
                            'icon'  => 'fal fa-history',
                        ],

                    ]
                ]
            ),
        };
    }

}
