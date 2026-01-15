<?php

/*
 * Author: Vika Aqordi
 * Created on 15-01-2026-15h-54m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Ordering\Transaction;

use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInOrders;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInOrders;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateOrders;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateShipments;
use App\Actions\Ordering\Order\Search\OrderRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\UpdateOrderNotesEvent;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransactionDiscretionaryDiscount extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Transaction $transaction;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        dd('xxxxxx', $modelData);

        return $transaction;
    }

    public function rules(): array
    {
        $rules = [
            'discretionary_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
        ];


        return $rules;
    }

    public function action(Transaction $transaction, array $modelData): Transaction
    {

        $this->initialisationFromShop($transaction->shop, $modelData);

        return $this->handle($transaction, $this->validatedData);
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->transaction = $transaction;
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction, $this->validatedData);
    }
}
