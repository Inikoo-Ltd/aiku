<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Ordering\Order\UI\GetOrderAddressManagement;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\RetinaAction;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Models\Dropshipping\Platform;

class ShowRetinaDropshippingOrder extends RetinaAction
{
    public function handle(Order $order): Order
    {
        return $order;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function inBasket(Platform $platform, Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromPlatform($platform, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function inPlatform(Platform $platform, Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromPlatform($platform, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }



    public function htmlResponse(Order $order, ActionRequest $request): Response
    {

        $finalTimeline = ShowOrder::make()->getOrderTimeline($order);


        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));


        return Inertia::render(
            'Dropshipping/Order',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('customer client')
                    ],
                    'actions' => []
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrderTabsEnum::navigation()
                ],


                'timelines'   => $finalTimeline,

                'address_management' => GetOrderAddressManagement::run(order: $order, isRetina:true),


                'box_stats'      => ShowOrder::make()->getOrderBoxStats($order),
                'currency'       => CurrencyResource::make($order->currency)->toArray(request()),
                'data'           => OrderResource::make($order),



                OrderTabsEnum::TRANSACTIONS->value => $this->tab == OrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                 OrderTabsEnum::INVOICES->value => $this->tab == OrderTabsEnum::INVOICES->value ?
                     fn () => InvoicesResource::collection(IndexInvoices::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                     : Inertia::lazy(fn () => InvoicesResource::collection(IndexInvoices::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                 OrderTabsEnum::DELIVERY_NOTES->value => $this->tab == OrderTabsEnum::DELIVERY_NOTES->value ?
                     fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                     : Inertia::lazy(fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

                 OrderTabsEnum::ATTACHMENTS->value => $this->tab == OrderTabsEnum::ATTACHMENTS->value ?
                     fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                     : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: OrderTabsEnum::TRANSACTIONS->value
                )
            )
            ->table(IndexInvoices::make()->tableStructure(
                parent: $order,
                prefix: OrderTabsEnum::INVOICES->value
            ))
            ->table(IndexAttachments::make()->tableStructure(
                prefix: OrderTabsEnum::ATTACHMENTS->value
            ))
            ->table(IndexDeliveryNotes::make()->tableStructure(
                parent: $order,
                prefix: OrderTabsEnum::DELIVERY_NOTES->value
            ));
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Order $order, array $routeParameters, string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($order->slug),
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        $order = Order::where('slug', $routeParameters['order'])->first();

        return array_merge(
            IndexRetinaDropshippingOrders::make()->getBreadcrumbs(),
            $headCrumb(
                $order,
                [
                    'index' => [
                        'name' => 'retina.dropshipping.orders.index',
                        'parameters' => []
                    ],
                    'model' => [
                        'name' => 'retina.dropshipping.orders.show',
                        'parameters' => [$order->slug]
                    ]
                ],
                $suffix
            ),
        );
    }
}
