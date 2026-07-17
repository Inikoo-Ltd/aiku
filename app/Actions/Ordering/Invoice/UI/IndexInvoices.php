<?php

namespace App\Actions\Ordering\Invoice\UI;

use App\Actions\Accounting\Invoice\UI\IndexInvoices as IndexAccountingInvoices;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Ordering\Order\WithOrdersSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Enums\UI\Ordering\OrderingInvoicesTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexInvoices extends OrgAction
{
    use WithOrderingAuthorisation;
    use WithOrdersSubNavigation;

    private Shop $parent;

    public function handle(
        Shop $shop,
        ?string $prefix = null,
        string $bucket = OrderingInvoicesTabsEnum::ALL->value
    ): LengthAwarePaginator {
        return IndexAccountingInvoices::make()->handle($shop, $prefix, $bucket);
    }

    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return InvoicesResource::collection($invoices);
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        $currentTab = $this->tab ?? OrderingInvoicesTabsEnum::ALL->value;

        return Inertia::render(
            'Org/Ordering/Invoices',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Invoices'),
                'pageHead'    => [
                    'title'         => __('Invoices'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => __('Invoices'),
                    ],
                    'subNavigation' => $this->getOrdersNavigation($this->parent),
                ],
                'tabs'        => [
                    'current'    => $currentTab,
                    'navigation' => OrderingInvoicesTabsEnum::navigation(),
                ],
                OrderingInvoicesTabsEnum::ALL->value => $currentTab === OrderingInvoicesTabsEnum::ALL->value
                    ? fn () => InvoicesResource::collection($invoices)
                    : Inertia::optional(fn () => InvoicesResource::collection(
                        $this->handle(
                            $this->parent,
                            OrderingInvoicesTabsEnum::ALL->value,
                            OrderingInvoicesTabsEnum::ALL->value
                        )
                    )),
                OrderingInvoicesTabsEnum::PAID->value => $currentTab === OrderingInvoicesTabsEnum::PAID->value
                    ? fn () => InvoicesResource::collection($invoices)
                    : Inertia::optional(fn () => InvoicesResource::collection(
                        $this->handle(
                            $this->parent,
                            OrderingInvoicesTabsEnum::PAID->value,
                            OrderingInvoicesTabsEnum::PAID->value
                        )
                    )),
                OrderingInvoicesTabsEnum::UNPAID->value => $currentTab === OrderingInvoicesTabsEnum::UNPAID->value
                    ? fn () => InvoicesResource::collection($invoices)
                    : Inertia::optional(fn () => InvoicesResource::collection(
                        $this->handle(
                            $this->parent,
                            OrderingInvoicesTabsEnum::UNPAID->value,
                            OrderingInvoicesTabsEnum::UNPAID->value
                        )
                    )),
            ]
        )->table(
            IndexAccountingInvoices::make()->tableStructure(
                $this->parent,
                OrderingInvoicesTabsEnum::ALL->value
            )
        )->table(
            IndexAccountingInvoices::make()->tableStructure(
                $this->parent,
                OrderingInvoicesTabsEnum::PAID->value
            )
        )->table(
            IndexAccountingInvoices::make()->tableStructure(
                $this->parent,
                OrderingInvoicesTabsEnum::UNPAID->value
            )
        );
    }

    public function asController(
        Organisation $organisation,
        Shop $shop,
        ActionRequest $request
    ): LengthAwarePaginator {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(OrderingInvoicesTabsEnum::values());

        $currentTab = $this->tab ?? OrderingInvoicesTabsEnum::ALL->value;

        return $this->handle($shop, $currentTab, $currentTab);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.ordering.invoices.index' => array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Invoices'),
                            'icon'  => 'fal fa-file-invoice-dollar',
                        ],
                    ],
                ]
            ),
            default => [],
        };
    }
}
