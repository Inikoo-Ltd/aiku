<?php

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Discounts\Offer;
use App\Models\Ordering\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddVoucherToOrder extends OrgAction
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, array $modelData): array
    {
        $voucherCode = Str::lower(trim(data_get($modelData, 'voucher')));

        $offer = Offer::where('shop_id', $order->shop_id)
            ->where('voucher', $voucherCode)
            ->first();

        if (!$offer) {
            throw ValidationException::withMessages([
                'voucher' => __('Voucher not found.')
            ]);
        }

        if ($offer->end_at && $offer->end_at->lt(now())) {
            throw ValidationException::withMessages([
                'voucher' => __('Voucher already expired.')
            ]);
        }

        if ($offer->start_at && $offer->start_at->gt(now())) {
            throw ValidationException::withMessages([
                'voucher' => __('Offer has not started yet.')
            ]);
        }

        if (!$offer->status) {
            throw ValidationException::withMessages([
                'voucher' => __('Voucher is not active.')
            ]);
        }


        $canCustomerReuse = data_get($offer->settings, 'can_customer_reuse', false);

        if (!$canCustomerReuse) {
            $previousUse = Order::where('customer_id', $order->customer_id)
                ->where('offer_voucher_id', $offer->id)
                ->where('id', '!=', $order->id)
                ->where('state', '<>', OrderStateEnum::CANCELLED)
                ->exists();

            if ($previousUse) {
                throw ValidationException::withMessages([
                    'voucher' => __('This voucher has already been used.')
                ]);
            }
        }

        $order->update(
            [
                'offer_voucher_id' => $offer->id
            ]
        );

        CalculateOrderDiscounts::run($order);

        return GetVoucherData::run($offer->id);
    }

    public function rules(): array
    {
        return [
            'voucher' => ['required', 'string', 'max:32']
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, Request $request): array
    {
        return $this->handle($order, $request->validate($this->rules()));
    }
}
