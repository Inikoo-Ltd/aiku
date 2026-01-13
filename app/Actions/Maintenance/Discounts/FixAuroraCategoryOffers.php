<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Dec 2025 22:48:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Discounts\Offer\UpdateOfferAllowanceSignature;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FixAuroraCategoryOffers
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:aurora_category_offers {shop}';

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $organisation       = $shop->organisation;
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);

        $offerCampaigns = OfferCampaign::where('shop_id', $shop->id)
            ->where('type', OfferCampaignTypeEnum::CATEGORY_OFFERS)
            ->pluck('id');

        $offers = Offer::whereIn('offer_campaign_id', $offerCampaigns)
            ->whereNotNull('source_id')
            ->get();

        $progressBar = $command->getOutput()->createProgressBar($offers->count());

        /** @var Offer $offer */
        foreach ($offers as $offer) {

            $progressBar->advance();
            $sourceData = explode(':', $offer->source_id);

            $auroraDealData          = DB::connection('aurora')->table('Deal Dimension')->where('Deal Key', $sourceData[1])->first();
            if (!$auroraDealData) {
                continue;
            }

            $auroraDealComponentData = DB::connection('aurora')->table('Deal Component Dimension')->where('Deal Component Deal Key', $auroraDealData->{'Deal Key'})->first();


            $discount = $auroraDealComponentData->{'Deal Component Allowance'};

            if ($offer->status != OfferStateEnum::ACTIVE) {
                $offer->update([
                    'status' => false
                ]);
                foreach ($offer->offerAllowances as $offerAllowance) {
                    $offerAllowance->update([
                        'state'  => $offer->state->value,
                        'status' => false
                    ]);
                }
            }

            if ($auroraDealData->{'Deal Status'} == 'Active') {
                $offer->update([
                    'state'  => OfferStateEnum::ACTIVE,
                    'status' => true,
                ]);

                if ($auroraDealData->{'Deal Expiration Date'} != '') {
                    $offer->update([
                        'end_at' => Carbon::parse($auroraDealData->{'Deal Expiration Date'})
                    ]);
                }

                foreach ($offer->offerAllowances as $offerAllowance) {
                    $offerAllowance->update([
                        'state'  => $offer->state->value,
                        'status' => $offer->status,
                        'end_at' => $offer->end_at
                    ]);
                }
            } else {

                $state = OfferStateEnum::FINISHED;
                if ($auroraDealData->{'Deal Status'} == 'Suspended') {
                    $state = OfferStateEnum::SUSPENDED;
                } elseif ($auroraDealData->{'Deal Status'} == 'Waiting') {
                    $state = OfferStateEnum::IN_PROCESS;
                }

                $offer->update([
                    'state'  => $state,
                    'status' => false,
                ]);

                if ($auroraDealData->{'Deal Expiration Date'} != '') {
                    $offer->update([
                        'end_at' => Carbon::parse($auroraDealData->{'Deal Expiration Date'})
                    ]);
                }

                foreach ($offer->offerAllowances as $offerAllowance) {
                    $offerAllowance->update([
                        'state'  => $offer->state->value,
                        'status' => false,
                        'end_at' => $offer->end_at
                    ]);
                }
            }



            if ($offer->type == 'Category Quantity Ordered' || $offer->type == 'Category Ordered') {
                if (!$offer->trigger) {
                    $offer->update([
                        'status' => false,
                    ]);
                    if ($offer->state == OfferStateEnum::ACTIVE) {
                        $offer->update(
                            [
                                'state' => OfferStateEnum::SUSPENDED
                            ]
                        );
                        foreach ($offer->offerAllowances as $offerAllowance) {
                            $offerAllowance->update([
                                'state'  => OfferAllowanceStateEnum::SUSPENDED,
                                'status' => false
                            ]);
                        }
                    } else {
                        foreach ($offer->offerAllowances as $offerAllowance) {
                            $offerAllowance->update([
                                'state'  => $offer->state->value,
                                'status' => false
                            ]);
                        }
                    }
                } else {
                    /** @var ProductCategory $family */
                    $family = $offer->trigger;

                    $offer->update([
                        'type'         => 'Category Ordered',
                        'trigger_data' => [
                            'item_quantity' => 1
                        ]
                    ]);


                    $offerAllowance = $offer->offerAllowances()->first();

                    $offerAllowance->update([
                        'data'          => [
                            'category_id'    => $family->id,
                            'category_type'  => $family->type,
                            'percentage_off' => (float)$discount,
                        ],
                        'class'         => 'discount',
                        'type'          => 'percentage_off',
                        'target_id'     => $family->id,
                        'target_data'   => [],
                        'target_type'   => 'all_products_in_product_category',
                        'trigger_id'    => null,
                        'trigger_type'  => null,
                        'trigger_scope' => null,
                    ]);

                    $offer->refresh();
                    UpdateOfferAllowanceSignature::run($offer);
                }
            }
        }

        $progressBar->finish();
        $command->getOutput()->newLine();
    }


}
