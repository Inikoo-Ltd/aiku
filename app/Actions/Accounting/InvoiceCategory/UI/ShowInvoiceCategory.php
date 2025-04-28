<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-11h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceCategory\UI;

use App\Actions\Accounting\InvoiceCategory\WithInvoiceCategorySubNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use App\Enums\UI\Accounting\InvoiceCategoryTabsEnum;
use App\Http\Resources\Accounting\InvoiceCategoryResource;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowInvoiceCategory extends OrgAction
{
    use WithInvoiceCategorySubNavigation;
    private Organisation|Group $parent;


    public function handle(InvoiceCategory $invoiceCategory): InvoiceCategory
    {
        return $invoiceCategory;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, InvoiceCategory $invoiceCategory, ActionRequest $request): InvoiceCategory
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(InvoiceCategoryTabsEnum::values());
        return $this->handle($invoiceCategory);
    }

    public function htmlResponse(InvoiceCategory $invoiceCategory, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/InvoiceCategory',
            [
                'title'                                 => $invoiceCategory->name,
                'breadcrumbs'                           => $this->getBreadcrumbs($invoiceCategory, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'                            => [
                    'previous' => $this->getPrevious($invoiceCategory, $request),
                    'next'     => $this->getNext($invoiceCategory, $request),
                ],
                'pageHead'    => [
                    'subNavigation' => $this->getInvoiceCategoryNavigation($invoiceCategory),
                    'model'     => __('Invoice Category'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-sitemap'],
                        'title' => __('invoice category')
                    ],
                    'title'     => $invoiceCategory->name,
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'tooltip' => __('edit invoice category'),
                            'label'   => __('Edit Invoice Category'),
                            'route'   => [
                                'name' => 'grp.org.accounting.invoice-categories.edit',
                                'parameters' => [
                                    'organisation' => $invoiceCategory->organisation->slug,
                                    'invoiceCategory' => $invoiceCategory->slug
                                ]
                            ]
                        ]
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => InvoiceCategoryTabsEnum::navigation()
                ],
            ]
        );
    }


    public function jsonResponse(InvoiceCategory $invoiceCategory): InvoiceCategoryResource
    {
        return new InvoiceCategoryResource($invoiceCategory);
    }

    public function getBreadcrumbs(InvoiceCategory $invoiceCategory, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (InvoiceCategory $invoiceCategory, array $routeParameters, string $suffix = null) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Invoice Categories'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $invoiceCategory->name ?? __('No name'),
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.invoice-categories.show' => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs(
                    'grp.org.accounting.dashboard',
                    Arr::only($routeParameters, ['organisation'])
                ),
                $headCrumb(
                    $invoiceCategory,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoice-categories.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoice-categories.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoiceCategory'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.accounting.invoice-categories.show.invoices.index' => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs(
                    'grp.org.accounting.dashboard',
                    Arr::only($routeParameters, ['organisation'])
                ),
                $headCrumb(
                    $invoiceCategory,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoice-categories.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoice-categories.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoiceCategory'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.accounting.invoice-categories.show.refunds.index' => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs(
                    'grp.org.accounting.dashboard',
                    Arr::only($routeParameters, ['organisation'])
                ),
                $headCrumb(
                    $invoiceCategory,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoice-categories.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoice-categories.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoiceCategory'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function getPrevious(InvoiceCategory $invoiceCategory, ActionRequest $request): ?array
    {
        $previous = InvoiceCategory::where('slug', '<', $invoiceCategory->slug)->when(true, function ($query) use ($invoiceCategory, $request) {
            if ($this->parent instanceof Organisation) {
                $query->where('organisation_id', $this->parent->id);
            } else {
                $query->where('group', $this->parent->id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(InvoiceCategory $invoiceCategory, ActionRequest $request): ?array
    {
        $next = InvoiceCategory::where('slug', '>', $invoiceCategory->slug)->when(true, function ($query) use ($invoiceCategory, $request) {
            if ($this->parent instanceof Organisation) {
                $query->where('organisation_id', $this->parent->id);
            } else {
                $query->where('group', $this->parent->id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?InvoiceCategory $invoiceCategory, string $routeName): ?array
    {
        if (!$invoiceCategory) {
            return null;
        }
        return match ($routeName) {
            'grp.org.accounting.invoice-categories.show' => [
                'label' => $invoiceCategory->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'organisation' => $invoiceCategory->organisation->slug,
                        'invoiceCategory'  => $invoiceCategory->slug
                    ]
                ]
            ],
        };
    }
}
