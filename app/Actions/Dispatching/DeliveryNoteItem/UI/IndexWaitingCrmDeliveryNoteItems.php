<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\WaitingDeliveryNoteItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use Closure;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWaitingCrmDeliveryNoteItems extends BaseIndexWaitingDeliveryNoteItems
{
    protected string $waitingType = 'crm';

    protected function getDeliveryNoteState(): DeliveryNoteStateEnum
    {
        return DeliveryNoteStateEnum::HANDLING_BLOCKED;
    }

    protected function getPageTitle(): string
    {
        return __('CRM Waiting items');
    }

    protected function getRouteName(): string
    {
        return 'grp.org.warehouses.show.dispatching.waiting_crm_items';
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $items = IndexWaitingDeliveryNoteItemsItemized::make()->handle(
            warehouse: $warehouse,
            waitingType: 'crm',
            state: $this->getDeliveryNoteState(),
            shopType: $this->shopType,
        );

        return Inertia::render('Org/Dispatching/WaitingCrmDeliveryNoteItems', [
            'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
            'title'       => $this->getPageTitle(),
            'pageHead'    => [
                'title' => $this->getPageTitle(),
                'icon'  => [
                    'icon'  => ['fal', 'fa-hourglass-start'],
                    'title' => __('CRM Waiting Items'),
                ],
            ],
            'items' => WaitingDeliveryNoteItemsResource::collection($items),
        ])->table($this->tableStructure());
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->withEmptyState([
                'title' => __('No CRM waiting items found'),
            ])->defaultSort('org_stock_code');

            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_waiting_crm', label: __('Qty Waiting'), canBeHidden: false, sortable: true);
        };
    }
}
