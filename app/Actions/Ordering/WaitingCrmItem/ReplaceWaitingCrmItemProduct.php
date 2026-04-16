<?php

/*
 * Author: Vika Aqordi
 * Created on 13-04-2026-15h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Ordering\WaitingCrmItem;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ReplaceWaitingCrmItemProduct extends OrgAction
{
    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): void
    {
        // dd($modelData);
    }

    public function rules(): array
    {
        return [
            'products'            => ['required', 'array', 'min:1'],
            'products.*.id'       => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
