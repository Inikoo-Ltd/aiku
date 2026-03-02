<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 15:04:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\Search\OfferCampaignRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOfferCampaigns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOfferCampaigns;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOfferCampaign extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Shop $shop, array $modelData): OfferCampaign
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        /** @var OfferCampaign $offerCampaign */
        $offerCampaign = $shop->offerCampaigns()->create($modelData);
        $offerCampaign->stats()->create();

        GroupHydrateOfferCampaigns::dispatch($offerCampaign->group);
        OrganisationHydrateOfferCampaigns::dispatch($offerCampaign->organisation);
        ShopHydrateOfferCampaigns::dispatch($shop);
        OfferCampaignRecordSearch::dispatch($offerCampaign);

        return $offerCampaign;
    }

    public function rules(): array
    {
        return [
            'code'   => [
                'required',
                new IUnique(
                    table: 'offer_campaigns',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),

                'max:64',
                'alpha_dash'
            ],
            'name'   => ['required', 'max:250', 'string'],
            'data'   => ['sometimes', 'required'],
            'type'   => ['required', Rule::enum(OfferCampaignTypeEnum::class)],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function action(Shop $shop, array $modelData): OfferCampaign
    {
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
