<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Nov 2025 19:12:53 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffers;
use App\Actions\Discounts\Offer\Search\OfferRecordSearch;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOffers;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteOffer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Offer $offer, bool $force = false): Offer
    {
        DB::transaction(function () use ($offer, $force) {
            foreach ($offer->offerAllowances()->withTrashed()->get() as $allowance) {
                if ($force) {
                    $allowance->stats()->delete();
                    $allowance->forceDelete();
                } else {
                    $allowance->delete();
                }
            }

            if ($force) {
                $offer->stats()->delete();
                $offer->forceDelete();
            } else {
                $offer->delete();
            }
        });

        GroupHydrateOffers::dispatch($offer->group)->delay($this->hydratorsDelay);
        OrganisationHydrateOffers::dispatch($offer->organisation)->delay($this->hydratorsDelay);
        ShopHydrateOffers::dispatch($offer->shop)->delay($this->hydratorsDelay);
        OfferCampaignHydrateOffers::dispatch($offer->offerCampaign)->delay($this->hydratorsDelay);
        if (!$force) {
            OfferRecordSearch::dispatch($offer)->delay($this->hydratorsDelay);
        }

        return $offer;
    }


    /**
     * @throws \Throwable
     */
    public function action(Offer $offer, bool $force = false): Offer
    {
        $this->asAction = true;
        $this->initialisationFromShop($offer->shop, []);

        return $this->handle($offer, $force);
    }

    public function getCommandSignature(): string
    {
        return 'offer:delete {slug} {--F|force}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $slug = $command->argument('slug');
        /** @var Offer $offer */
        $offer = Offer::withTrashed()->where('slug', $slug)->firstOrFail();

        $this->action($offer, $command->option('force'));

        $command->info("Offer $offer->name ($offer->code) deleted.");

        return 0;
    }
}
