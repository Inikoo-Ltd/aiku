<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: UI action to render the create Return page from an Order
 */

namespace App\Actions\Dispatching\Return\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItems;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Closure;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateReturn extends OrgAction
{
    use AsAction;
    use WithInertia;

    private Order $parent;

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
                    'parameters' => array_values($request->route()->originalParameters()),
                ],
            ],
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Create Return'),
                'label'   => __('Create Return'),
                'key'     => 'action_create_return',
                'icon'    => 'fas fa-undo-alt',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.order.return.store',
                    'parameters' => [
                        'order' => $deliveryNote->orders->first()->id,
                    ],
                ],
            ],
        ];
    }

    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->first();
        $actions = $this->getActions($deliveryNote, $request);

        $props = [
            'title'       => __('Create Return'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $order,
                $request->route()->getName(),
                $request->route()->originalParameters(),
            ),
            'navigation'  => [],
            'pageHead'    => [
                'title' => $order->reference,
                'model' => __('Create Return'),
                'icon'  => [
                    'icon'  => 'fal fa-undo-alt',
                    'title' => __('Create Return'),
                ],
                'actions' => $actions,
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => DeliveryNoteTabsEnum::navigation($deliveryNote),
            ],
            'delivery_note' => [
                'id'        => $deliveryNote->id,
                'reference' => $deliveryNote->reference,
                'state'     => $deliveryNote->state,
            ],
            'order'       => [
                'id'        => $order->id,
                'reference' => $order->reference,
            ],
            'customer'    => CustomerResource::make($order->customer)->getArray(),
            'address'     => [
                'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run(),
                ],
            ],
            'routes'      => [
                'store' => [
                    'name'       => 'grp.models.order.return.store',
                    'parameters' => [
                        'order' => $order->id,
                    ],
                ],
            ],
            'environment' => config('app.env'),
        ];

        $props = array_merge($props, $this->getItems($deliveryNote));

        $inertiaResponse = Inertia::render(
            'Org/Dispatching/CreateReturn',
            $props,
        );

        $inertiaResponse->table($this->tableStructure(parent: $deliveryNote, prefix: DeliveryNoteTabsEnum::ITEMS->value));

        return $inertiaResponse;
    }

    public function tableStructure(DeliveryNote $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState([
                    'title' => __('No items found'),
                ]);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_dispatched', label: __('Qty Dispatched'), canBeHidden: false, sortable: true, align: 'right');
            $table->column(key: 'quantity_to_return', label: __('Qty Return'), canBeHidden: false, align: 'right');
        };
    }

    public function getItems(DeliveryNote $deliveryNote): array
    {
        return [
            DeliveryNoteTabsEnum::ITEMS->value => fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote)),
        ];
    }

    public function getBreadcrumbs(Order $order, string $routeName, array $routeParameters): array
    {
        return ShowOrder::make()->getBreadcrumbs(
            order: $order,
            routeName: preg_replace('/return.create$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Creating return').')'
        );
    }
}
