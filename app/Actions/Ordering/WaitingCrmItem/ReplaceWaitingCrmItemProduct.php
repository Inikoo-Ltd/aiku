<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 14:00:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\WaitingCrmItem;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ReplaceWaitingCrmItemProduct extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): void
    {
        DB::transaction(function () use ($deliveryNoteItem, $modelData) {

            $deliveryNoteItem->update([
                'quantity_waiting_crm' => 0,
                'has_waiting_crm'      => false,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);


        });
    }

    public function rules(): array
    {
        return [
            'products'            => ['required', 'array', 'min:1'],
            'products.*.id'       => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNoteItem->deliveryNote->shop, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
