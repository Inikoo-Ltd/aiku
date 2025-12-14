<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 09 Sep 2025 11:57:07 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItems;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\OrgAction;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Http\Resources\Helpers\AddressResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Closure;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateReplacementDeliveryNote extends OrgAction
{
    use AsAction;
    use WithInertia;
    use GetPlatformLogo;
    use IsOrder;

    private Order|Shop|Warehouse|Customer $parent;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Order
    {
        $this->parent = $order;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($order);
    }


    public function getActions(DeliveryNote $deliveryNote, ActionRequest $request): array
    {
        return [
            [
                'type'  => 'button',
                'style' => 'exitEdit',
                'label' => __('Back'),
                'route' => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show',
                    'parameters' => array_values($request->route()->originalParameters())
                ],
            ],
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Save'),
                'label'   => __('Save'),
                'key'     => 'action_replacement',
                'icon'    => 'fas fa-save',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.order.replacement_delivery_note.store',
                    'parameters' => [
                        'order' => $deliveryNote->orders->first()->id
                    ]
                ]
            ]
        ];
    }

    public function getInvoiceButton(DeliveryNote $deliveryNote): array
    {
        $invoiceButton        = [];
        $generateInvoiceLabel = __('Generate Invoice');

        if (($deliveryNote->state == DeliveryNoteStateEnum::FINALISED || $deliveryNote->state == DeliveryNoteStateEnum::DISPATCHED) && $deliveryNote->orders->first()->invoices->count() == 0) {
            $invoiceButton = [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => $generateInvoiceLabel,
                    'label'   => $generateInvoiceLabel,
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.dispatched',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ]
            ];
        }

        return $invoiceButton;
    }

    public function getBoxStats(DeliveryNote $deliveryNote): array
    {
        $estWeight = ($deliveryNote->estimated_weight ?? 0) / 1000;
        $order     = $deliveryNote->orders->first();

        return [
            'state'                 => $deliveryNote->state,
            'state_icon'            => DeliveryNoteStateEnum::stateIcon()[$deliveryNote->state->value],
            'state_label'           => $deliveryNote->state->labels()[$deliveryNote->state->value],
            'is_collection'         => (bool)$deliveryNote->orders()->first()->collection_address_id,
            'is_replacement'        => true,
            'is_create_replacement' => true,
            'delivery_note'         => [
                'reference' => $deliveryNote->reference,
                'route'     => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show.delivery-note',
                    'parameters' => [
                        'organisation' => $order->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'order'        => $order->slug,
                        'deliveryNote' => $deliveryNote->slug
                    ]
                ],
            ],
            'customer'              => array_merge(
                CustomerResource::make($deliveryNote->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                    ],
                    'route'     => [
                        'name'       => 'grp.org.shops.show.crm.customers.show',
                        'parameters' => [
                            'organisation' => $deliveryNote->organisation->slug,
                            'shop'         => $deliveryNote->shop->slug,
                            'customer'     => $deliveryNote->customer->slug
                        ]
                    ]
                ]
            ),
            'customer_client'       => $deliveryNote->customerClient,
            'platform'              => [
                'name' => $deliveryNote->platform?->name,
                'logo' => $deliveryNote->customerSalesChannel?->platform?->code ? $this->getPlatformLogo($deliveryNote->customerSalesChannel->platform->code) : null,
            ],
            'products'              => [
                'estimated_weight' => $estWeight,
                'number_items'     => $deliveryNote->number_items,
            ],
            'order'                 => [
                'reference' => $order->reference,
                'route'     => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show',
                    'parameters' => [
                        'organisation' => $order->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'order'        => $order->slug
                    ]
                ],
            ],
            'address'               => [
                'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],
            'delivery_address'      => AddressResource::make($deliveryNote->deliveryAddress)
        ];
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->first();
        $actions      = $this->getActions($deliveryNote, $request);

        $warning = null;


        $props = [
            'title'         => __('Replacement'),
            'breadcrumbs'   => $this->getBreadcrumbs(
                $order,
                $request->route()->getName(),
                $request->route()->originalParameters(),
            ),
            'navigation'    => [],
            'pageHead'      => [
                'title'   => $order->reference,
                'model'   => __('Replacement'),
                'icon'    => [
                    'icon'  => 'fal fa-truck',
                    'title' => __('Replacement')
                ],
                'actions' => $actions,
                $this->getInvoiceButton($deliveryNote)
            ],
            'warning'       => $warning,
            'tabs'          => [
                'current'    => $this->tab,
                'navigation' => DeliveryNoteTabsEnum::navigation($deliveryNote)
            ],
            'delivery_note' => DeliveryNoteResource::make($deliveryNote)->toArray(request()),

            'address' => [
                'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],

            'box_stats' => $this->getBoxStats($deliveryNote),
            'notes'     => ShowDeliveryNote::make()->getDeliveryNoteNotes($deliveryNote),
            'routes'    => [
                'update' => [
                    'name'       => 'grp.models.delivery_note.update',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],

            ],


            'warehouse' => [
                'slug' => $deliveryNote->warehouse->slug,
            ],


        ];


        $props = array_merge($props, $this->getItems($deliveryNote));


        $inertiaResponse = Inertia::render(
            'Org/Dispatching/CreateReplacement',
            $props,
        );

        $inertiaResponse->table($this->tableStructure(parent: $deliveryNote, prefix: DeliveryNoteTabsEnum::ITEMS->value));

        return $inertiaResponse;
    }

    public function tableStructure(DeliveryNote $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withEmptyState(
                    [
                        'title' => __("No items found"),
                    ]
                );

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_dispatched', label: __('Quantity Dispatched'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'quantity_to_resend', label: __('Quantity Resend'), canBeHidden: false, searchable: true, align: 'right');
        };
    }

    public function getItems(DeliveryNote $deliveryNote): array
    {
        return [
            DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                fn() => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote))
                : Inertia::lazy(fn() => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote))),

        ];
    }


    public function getBreadcrumbs(Order $order, string $routeName, array $routeParameters): array
    {
        return ShowOrder::make()->getBreadcrumbs(
            order: $order,
            routeName: preg_replace('/replacement$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Creating replacement').')'
        );
    }


}
