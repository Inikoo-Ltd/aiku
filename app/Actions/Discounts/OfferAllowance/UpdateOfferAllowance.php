<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:04:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferAllowance;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Discounts\OfferAllowance;
use Lorisleiva\Actions\ActionRequest;

class UpdateOfferAllowance extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    private OfferAllowance $offerAllowance;

    public function handle(OfferAllowance $offerAllowance, array $modelData): OfferAllowance
    {
        return $this->update($offerAllowance, $modelData);
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
            'data' => ['sometimes', 'required']
        ];

        if (!$this->strict) {
            $rules                  = $this->noStrictUpdateRules($rules);
            $rules['trigger_scope'] = ['sometimes', 'string'];
            $rules['target_type']   = ['sometimes', 'string'];
            $rules['start_at']      = ['sometimes', 'nullable', 'date'];
        }

        return $rules;
    }

    public function action(OfferAllowance $offerAllowance, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): OfferAllowance
    {
        if (!$audit) {
            OfferAllowance::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->offerAllowance = $offerAllowance;
        $this->initialisationFromShop($offerAllowance->shop, $modelData);

        return $this->handle($offerAllowance, $this->validatedData);
    }


}
