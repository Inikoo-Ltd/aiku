<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The intercompany customer discount (e.g. everything 45% off) is the seller's standing
 * customer offer to the partner, the same offer that discounts the seller's order. Partners
 * without one yet fall back to the customer's recent order history, where Aurora applied it.
 */
class GetPartnerCustomerDiscount
{
    use AsObject;

    /**
     * @return float factor to multiply a list price by, e.g. 0.55 for 45% off
     */
    public function standingPercentageOff(Customer $customer): ?float
    {
        $percentageOff = DB::table('offers')
            ->join('offer_allowances', 'offer_allowances.offer_id', 'offers.id')
            ->where('offers.customer_id', $customer->id)
            ->where('offers.type', OfferTypeEnum::CUSTOMER_ANY_ORDER->value)
            ->where('offers.state', OfferStateEnum::ACTIVE->value)
            ->where('offer_allowances.state', OfferAllowanceStateEnum::ACTIVE->value)
            ->where('offer_allowances.target_type', OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER->value)
            ->where('offer_allowances.type', OfferAllowanceType::PERCENTAGE_OFF->value)
            ->max(DB::raw("(offer_allowances.data->>'percentage_off')::numeric"));

        return $percentageOff === null ? null : (float) $percentageOff;
    }

    public function handle(Customer $customer): float
    {
        return Cache::remember("partner_customer_discount_factor_$customer->id", now()->addHours(6), function () use ($customer) {
            $percentageOff = $this->standingPercentageOff($customer);
            if ($percentageOff !== null) {
                return max(0.0, min(1.0, round(1 - $percentageOff, 4)));
            }

            $factors = $customer->orders()
                ->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])
                ->where('gross_amount', '>', 0)
                ->orderByDesc('id')
                ->limit(10)
                ->get(['gross_amount', 'net_amount'])
                ->map(fn ($order) => (float) $order->net_amount / (float) $order->gross_amount)
                ->sort()
                ->values();

            if ($factors->isEmpty()) {
                return 1.0;
            }

            $factor = $factors->get(intdiv($factors->count(), 2));

            return max(0.0, min(1.0, round($factor, 4)));
        });
    }
}
