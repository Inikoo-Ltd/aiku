<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateInvoices;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffers;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffersState;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOrders;
use App\Actions\Discounts\OfferCampaign\StoreOfferCampaign;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MoveStepOffersToStepCampaign
{
    use AsAction;

    public string $commandSignature = 'repair:move_step_offers_to_step_campaign {--live}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $live = $command->option('live');

        $shopIds = Offer::where('code', 'like', 'po-step-%')->distinct()->pluck('shop_id');

        foreach (Shop::whereIn('id', $shopIds)->get() as $shop) {
            $productOffersCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::PRODUCT_OFFERS)->first();
            if (!$productOffersCampaign) {
                continue;
            }

            $offers = Offer::where('offer_campaign_id', $productOffersCampaign->id)->where('code', 'like', 'po-step-%')->get();
            $command->info("$shop->slug: {$offers->count()} step offers to move");
            if (!$live) {
                continue;
            }

            $stepCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::STEP_OFFERS)->first();
            if (!$stepCampaign) {
                $case         = OfferCampaignTypeEnum::STEP_OFFERS;
                $stepCampaign = StoreOfferCampaign::make()->action(
                    $shop,
                    [
                        'code'   => $case->codes()[$case->value],
                        'name'   => $case->labels()[$case->value],
                        'type'   => $case,
                        'status' => $case->defaultStatus(),
                    ]
                );
            }

            DB::transaction(function () use ($offers, $stepCampaign) {
                foreach ($offers as $offer) {
                    $newCode = 'st-'.substr($offer->code, strlen('po-step-'));
                    $offer->update([
                        'offer_campaign_id' => $stepCampaign->id,
                        'code'              => $newCode,
                    ]);

                    foreach (
                        [
                            'offer_allowances',
                            'transaction_has_offer_allowances',
                            'invoice_transaction_has_offer_allowances',
                            'order_has_no_transaction_offer_allowances',
                            'invoice_has_no_invoice_transaction_offer_allowances',
                        ] as $table
                    ) {
                        DB::table($table)->where('offer_id', $offer->id)->update(['offer_campaign_id' => $stepCampaign->id]);
                    }
                }
            });

            foreach ([$productOffersCampaign, $stepCampaign] as $offerCampaign) {
                OfferCampaignHydrateOffers::run($offerCampaign);
                OfferCampaignHydrateOffersState::run($offerCampaign);
                OfferCampaignHydrateOrders::run($offerCampaign);
                OfferCampaignHydrateInvoices::run($offerCampaign);
            }

            $command->info("$shop->slug: moved to campaign $stepCampaign->code");
        }

        if (!$live) {
            $command->info('Dry run, nothing written. Use --live to apply.');
        }

        return 0;
    }
}
