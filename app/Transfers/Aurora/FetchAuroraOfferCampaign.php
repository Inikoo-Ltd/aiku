<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 15:36:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraOfferCampaign extends FetchAurora
{
    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Deal Campaign Code'} == '') {
            return;
        }

        if($shop->is_aiku){
            return;
        }

        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Deal Campaign Store Key'});

        $status = false;
        //enum('Suspended','Active','Finish','Waiting')
        $state = match ($this->auroraModelData->{'Deal Campaign Status'}) {
            'Waiting' => OfferCampaignStateEnum::IN_PROCESS,
            'Finish' => OfferCampaignStateEnum::FINISHED,
            'Suspended' => OfferCampaignStateEnum::SUSPENDED,
            default => OfferCampaignStateEnum::ACTIVE
        };

        if ($this->auroraModelData->{'Deal Campaign Status'} == 'Active') {
            $status = true;
        }

        $type = match ($this->auroraModelData->{'Deal Campaign Code'}) {
            'CA' => OfferCampaignTypeEnum::COLLECTION_OFFERS,
            'CU' => OfferCampaignTypeEnum::CUSTOMER_OFFERS,
            'FO' => OfferCampaignTypeEnum::FIRST_ORDER,
            'OR' => OfferCampaignTypeEnum::ORDER_RECURSION,
            'PO' => OfferCampaignTypeEnum::PRODUCT_OFFERS,
            'SO', 'VO' => OfferCampaignTypeEnum::SHOP_OFFERS,
            'VL' => OfferCampaignTypeEnum::VOLUME_DISCOUNT,
        };


        $this->parsedData['shop']           = $shop;
        $this->parsedData['type']           = $type;
        $this->parsedData['offer-campaign'] = [
            'name'            => $this->auroraModelData->{'Deal Campaign Name'},
            'status'          => $status,
            'state'           => $state,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Deal Campaign Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now()
        ];


        $createdBy = $this->auroraModelData->{'Deal Campaign Valid From'};

        if ($createdBy) {
            $this->parsedData['offer-campaign']['created_by'] = $createdBy;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Deal Campaign Dimension')
            ->where('Deal Campaign Key', $id)->first();
    }
}
