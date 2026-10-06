<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerListVouchersOverview
{
    use AsObject;

    private const int LIMIT = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(OfferCampaign $offerCampaign): array
    {
        $shop = $offerCampaign->shop;

        $vouchers = Offer::query()
            ->where('offer_campaign_id', $offerCampaign->id)
            ->where('settings->has_customer_list', true)
            ->withCount('customerList')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $usage = DB::table('orders')
            ->select([
                'offer_voucher_id',
                DB::raw('COUNT(DISTINCT customer_id) AS number_customers_used'),
                DB::raw('SUM(net_amount) AS sales'),
            ])
            ->whereIn('offer_voucher_id', $vouchers->pluck('id'))
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('deleted_at')
            ->groupBy('offer_voucher_id')
            ->get()
            ->keyBy('offer_voucher_id');

        return $vouchers->map(fn (Offer $voucher) => [
            'id'                    => $voucher->id,
            'name'                  => $voucher->name,
            'code'                  => $voucher->code,
            'has_unique_codes'      => $voucher->hasUniqueCustomerCodes(),
            'state'                 => OfferStateEnum::stateIcon()[$voucher->state->value],
            'start_at'              => $voucher->start_at,
            'end_at'                => $voucher->end_at,
            'number_customers'      => $voucher->customer_list_count,
            'number_customers_used' => (int) ($usage->get($voucher->id)?->number_customers_used ?? 0),
            'sales'                 => (float) ($usage->get($voucher->id)?->sales ?? 0),
            'route'                 => [
                'name'       => 'grp.org.shops.show.discounts.campaigns.offer.show',
                'parameters' => [
                    'organisation'  => $shop->organisation->slug,
                    'shop'          => $shop->slug,
                    'offerCampaign' => $offerCampaign->slug,
                    'offer'         => $voucher->slug,
                ],
            ],
            'mailshot_route'        => $voucher->state === OfferStateEnum::FINISHED ? null : [
                'method'     => 'post',
                'name'       => 'grp.models.offer.customer_list_mailshot.store',
                'parameters' => ['offer' => $voucher->id],
            ],
        ])->all();
    }
}
