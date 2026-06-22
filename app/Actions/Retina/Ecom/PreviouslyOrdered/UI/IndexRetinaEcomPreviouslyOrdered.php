<?php

namespace App\Actions\Retina\Ecom\PreviouslyOrdered\UI;

use App\Actions\Retina\Traits\HasBasketTransactions;
use App\Actions\Retina\Traits\WithRetinaProductsList;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\CRM\RetinaCustomerFavouritesResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaEcomPreviouslyOrdered extends RetinaAction
{
    use HasBasketTransactions;
    use WithRetinaProductsList;

    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = $this->getRetinaProductsListQuery($customer);
        $query->whereIn('products.id', function ($subQuery) use ($customer) {
            $subQuery->select('transactions.model_id')
                ->from('transactions')
                ->join('orders', 'transactions.order_id', '=', 'orders.id')
                ->where('orders.customer_id', $customer->id)
                ->where('transactions.model_type', 'Product')
                ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->whereNull('transactions.deleted_at');
        });

        return $query->defaultSort('-products.created_at')
            ->allowedSorts(['code', 'name', 'created_at'])
            ->allowedFilters([$this->getRetinaProductsGlobalSearch()])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __("You haven't ordered any products yet"),
                    'count' => 0,
                ]);

            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'actions', label: '', canBeHidden: false);
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $basketTransactions = $this->getBasketTransactions($this->customer);

        return Inertia::render(
            'Ecom/Interests',
            [
                'breadcrumbs'               => $this->getBreadcrumbs(),
                'title'                     => __('Previously Ordered'),
                'pageHead'                  => [
                    'title' => __('Previously Ordered'),
                    'icon'  => 'fal fa-history',
                ],
                'data'                      => RetinaCustomerFavouritesResource::collection($products),
                'basketTransactions'        => $basketTransactions,
                'attachToFavouriteRoute'    => [
                    'name' => 'retina.models.product.favourite'
                ],
                'detachToFavouriteRoute'    => [
                    'name' => 'retina.models.product.unfavourite'
                ],
                'attachBackInStockRoute'    => [
                    'name' => 'retina.models.remind_back_in_stock.store'
                ],
                'detachBackInStockRoute'    => [
                    'name' => 'retina.models.remind_back_in_stock.delete'
                ],
                'addToBasketRoute'          => [
                    'name'   => 'retina.models.product.add-to-basket',
                    'method' => 'post'
                ],
                'updateBasketQuantityRoute' => [
                    'name'   => 'retina.models.transaction.update',
                    'method' => 'patch'
                ]
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.ecom.interest.previously_ordered.index'
                            ],
                            'label' => __('Previously Ordered'),
                        ]
                    ]
                ]
            );
    }
}
