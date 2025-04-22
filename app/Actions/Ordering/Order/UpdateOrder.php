<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateOrders;
use App\Actions\Ordering\Order\Search\OrderRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrder extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        $oldPlatform   = $order->platform;
        $order         = $this->update($order, $modelData, ['data']);
        $changedFields = $order->getChanges();
        $order->refresh();

        $changes = Arr::except($order->getChanges(), ['updated_at', 'last_fetched_at']);

        if (count($changes) > 0) {
            if (array_key_exists('state', $changedFields)) {
                $this->orderHydrators($order);
            }
            if (array_key_exists('platform_id', $changedFields)) {
                if ($order->platform) {
                    PlatformHydrateOrders::dispatch($order->platform)->delay($this->hydratorsDelay);
                } elseif ($oldPlatform) {
                    PlatformHydrateOrders::dispatch($oldPlatform)->delay($this->hydratorsDelay);
                }
            }

            if (Arr::hasAny($changedFields, ['reference', 'state', 'net_amount', 'payment_amount', 'date'])) {
                OrderRecordSearch::dispatch($order);
            }
        }

        return $order;
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'string',
                'max:64',
                new IUnique(
                    table: 'orders',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'value' => $this->order->id, 'operator' => '!=']
                    ]
                ),
            ],

            'in_warehouse_at'     => ['sometimes', 'date'],
            'delivery_address_id' => ['sometimes', Rule::exists('addresses', 'id')],
            'public_notes'        => ['sometimes', 'nullable', 'string', 'max:4000'],
            'internal_notes'      => ['sometimes', 'nullable', 'string', 'max:4000'],
            'state'               => ['sometimes', Rule::enum(OrderStateEnum::class)],
            'sales_channel_id'    => [
                'sometimes',
                'required',
                Rule::exists('sales_channels', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],
        ];


        if (!$this->strict) {
            $rules = $this->orderNoStrictFields($rules);
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Order $order, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Order
    {
        if (!$audit) {
            Order::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->order          = $order;

        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
