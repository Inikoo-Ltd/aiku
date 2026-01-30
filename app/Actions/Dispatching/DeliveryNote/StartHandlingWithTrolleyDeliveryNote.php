<?php

/*
 * Author: Vika Aqordi
 * Created on 30-01-2026-15h-33m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StartHandlingWithTrolleyDeliveryNote extends OrgAction
{

    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $trolley = data_get($modelData, 'trolley');
        dd("maybe can copy from StartHandlingDeliveryNote. selected trolley: $trolley");
    }

    
    public function rules(): array
    {
        return [
            'trolley' => ['required', 'string'],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }


}
