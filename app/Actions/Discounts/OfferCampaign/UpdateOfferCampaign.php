<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 15:55:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\Search\OfferCampaignRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOfferCampaigns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOfferCampaigns;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\OfferCampaign\OfferCampaignOffersStateEnum;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOfferCampaign extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(OfferCampaign $offerCampaign, array $modelData): OfferCampaign
    {
        $offerCampaign = $this->update($offerCampaign, $modelData);

        OfferCampaignRecordSearch::dispatch($offerCampaign)->delay($this->hydratorsDelay);

        if ($offerCampaign->wasChanged(['state', 'status'])) {
            GroupHydrateOfferCampaigns::dispatch($offerCampaign->group)->delay($this->hydratorsDelay);
            OrganisationHydrateOfferCampaigns::dispatch($offerCampaign->organisation)->delay($this->hydratorsDelay);
            ShopHydrateOfferCampaigns::dispatch($offerCampaign->shop)->delay($this->hydratorsDelay);
        }

        return $offerCampaign;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("discounts.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'name'   => ['sometimes', 'required', 'max:250', 'string'],
            'status' => ['sometimes', 'required', 'boolean'],
            'offers_state'  => ['sometimes', 'required', Rule::enum(OfferCampaignOffersStateEnum::class)],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(OfferCampaign $offerCampaign, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): OfferCampaign
    {
        if (!$audit) {
            OfferCampaign::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($offerCampaign->shop, $modelData);

        return $this->handle($offerCampaign, $this->validatedData);
    }

    public function asController(OfferCampaign $offerCampaign, ActionRequest $request): OfferCampaign
    {
        $this->initialisationFromShop($offerCampaign->shop, $request);

        return $this->handle($offerCampaign, $this->validatedData);
    }


}
