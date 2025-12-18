<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: UI action to display a single Return
 */

namespace App\Actions\Dispatching\Return\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Return\ReturnStateEnum;
use App\Http\Resources\Dispatching\OrderReturnResource;
use App\Models\Dispatching\OrderReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowReturn extends OrgAction
{
    public function handle(OrderReturn $return): OrderReturn
    {
        return $return;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrderReturn $return, ActionRequest $request): OrderReturn
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($return);
    }

    public function htmlResponse(OrderReturn $return, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Incoming/Return',
            [
                'title'       => __('Return') . ': ' . $return->reference,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $return,
                    $request->route()->originalParameters()
                ),
                'navigation'  => [],
                'pageHead'    => [
                    'title' => $return->reference,
                    'model' => __('Return'),
                    'icon'  => [
                        'icon'  => 'fal fa-undo-alt',
                        'title' => __('Return'),
                    ],
                    'actions' => $this->getActions($return),
                ],
                'return'      => OrderReturnResource::make($return)->toArray(request()),
                'box_stats'   => $this->getBoxStats($return),
            ]
        );
    }

    protected function getActions(OrderReturn $return): array
    {
        $actions = [];

        if ($return->state === ReturnStateEnum::WAITING_TO_RECEIVE) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'primary',
                'label'   => __('Mark as Received'),
                'key'     => 'mark_received',
                'icon'    => 'fal fa-inbox-in',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.return.receive',
                    'parameters' => ['return' => $return->id],
                ],
            ];
        }

        if ($return->state === ReturnStateEnum::RECEIVED) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'primary',
                'label'   => __('Start Inspection'),
                'key'     => 'start_inspection',
                'icon'    => 'fal fa-search',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.return.inspect',
                    'parameters' => ['return' => $return->id],
                ],
            ];
        }

        return $actions;
    }

    protected function getBoxStats(OrderReturn $return): array
    {
        return [
            'state'       => $return->state,
            'state_icon'  => ReturnStateEnum::stateIcon()[$return->state->value],
            'state_label' => $return->state->labels()[$return->state->value],
            'customer'    => [
                'name' => $return->customer->name ?? '-',
                'route' => $return->customer ? [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [
                        'organisation' => $return->organisation->slug,
                        'shop'         => $return->shop->slug,
                        'customer'     => $return->customer->slug,
                    ],
                ] : null,
            ],
            'order' => $return->orders->first() ? [
                'reference' => $return->orders->first()->reference,
                'route'     => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show',
                    'parameters' => [
                        'organisation' => $return->organisation->slug,
                        'shop'         => $return->shop->slug,
                        'order'        => $return->orders->first()->slug,
                    ],
                ],
            ] : null,
            'items'       => [
                'total'    => $return->number_items,
                'pending'  => $return->stats->number_items_state_pending ?? 0,
                'received' => $return->stats->number_items_state_received ?? 0,
                'accepted' => $return->stats->number_items_state_accepted ?? 0,
                'rejected' => $return->stats->number_items_state_rejected ?? 0,
            ],
            'dates'       => [
                'created'    => $return->created_at,
                'received'   => $return->received_at,
                'processed'  => $return->processed_at,
            ],
        ];
    }

    public function getBreadcrumbs(OrderReturn $return, array $routeParameters): array
    {
        return array_merge(
            IndexReturns::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.incoming.returns.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $return->reference,
                    ],
                ],
            ]
        );
    }
}
