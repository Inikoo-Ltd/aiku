<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:04:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferAllowance;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreOfferAllowance extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;


    /**
     * @throws \Throwable
     */
    public function handle(Offer $offer, array $modelData): OfferAllowance
    {

        $modelData = $this->prepareOfferData($offer, $modelData);
        data_set($modelData, 'offer_campaign_id', $offer->offer_campaign_id);


        return DB::transaction(function () use ($offer, $modelData) {
            /** @var $offerAllowance OfferAllowance */
            $offerAllowance = $offer->offerAllowances()->create($modelData);
            $offerAllowance->stats()->create();

            return $offerAllowance;
        });
    }

    public function rules(): array
    {
        $rules = [
            'data'          => ['sometimes', 'required'],
            'start_at'      => ['sometimes', 'date'],
            'end_at'        => ['sometimes', 'nullable', 'date'],
            'trigger_scope' => ['required', 'max:250', 'string'],
            'duration'      => ['sometimes', OfferDurationEnum::class],
            'type'          => ['sometimes', Rule::enum(OfferAllowanceType::class)],
            'target_type'   => ['sometimes', Rule::enum(OfferAllowanceTargetTypeEnum::class)],
            'class'         => ['sometimes', Rule::enum(OfferAllowanceClass::class)],
        ];
        if (!$this->strict) {
            $rules['state']            = ['required', Rule::enum(OfferAllowanceStateEnum::class)];
            $rules['start_at']         = ['sometimes', 'nullable', 'date'];
            $rules['is_discretionary'] = ['sometimes', 'boolean'];
            $rules['is_locked']        = ['sometimes', 'boolean'];
            $rules['source_data']      = ['sometimes', 'array'];

            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Offer $offer, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): OfferAllowance
    {
        if (!$audit) {
            OfferAllowance::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($offer->shop, $modelData);

        return $this->handle($offer, $this->validatedData);
    }
}
