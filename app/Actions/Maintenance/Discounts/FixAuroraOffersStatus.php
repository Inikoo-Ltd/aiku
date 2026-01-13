<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Dec 2025 22:48:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FixAuroraOffersStatus
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:aurora_offers_status {shop}';

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $organisation=$shop->organisation;

        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $offers = Offer::whereNotNull('source_id')->where('shop_id', $shop->id)->get();

        $bar = $command->getOutput()->createProgressBar($offers->count());
        $bar->setFormat('debug');
        $bar->start();

        /** @var Offer $offer */
        foreach ($offers as $offer) {
            $sourceData = explode(':', $offer->source_id);

            $auroraDealData          = DB::connection('aurora')->table('Deal Dimension')->where('Deal Key', $sourceData[1])->first();


            if ($auroraDealData) {
                $auroraStatus               = $auroraDealData->{'Deal Status'};
                $expirationAuroraParsedDate = null;
                $expirationAuroraDate       = $auroraDealData->{'Deal Expiration Date'};

                if ($expirationAuroraDate == '0000-00-00 00:00:00') {
                    $expirationAuroraDate = null;
                }

                if ($expirationAuroraDate) {
                    $expirationAuroraParsedDate = Carbon::parse($auroraDealData->{'Deal Expiration Date'});
                }
            } else {
                $auroraStatus = 'Finished';
                $expirationAuroraParsedDate = null;
                $expirationAuroraDate = null;
            }




            if ($auroraStatus == 'Active') {
                $offer->update([
                    'state'  => OfferStateEnum::ACTIVE,
                    'status' => true,
                ]);

                if ($expirationAuroraDate) {
                    $offer->update([
                        'end_at' => $expirationAuroraParsedDate
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
                if ($auroraStatus == 'Suspended') {
                    $state = OfferStateEnum::SUSPENDED;
                } elseif ($auroraStatus == 'Waiting') {
                    $state = OfferStateEnum::IN_PROCESS;
                }

                $offer->update([
                    'state'  => $state,
                    'status' => false,
                ]);

                if ($expirationAuroraDate) {
                    $offer->update([
                        'end_at' => $expirationAuroraParsedDate
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

            $bar->advance();
        }

        $bar->finish();
        $command->getOutput()->newLine();
    }


}
