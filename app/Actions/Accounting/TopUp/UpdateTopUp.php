<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUp;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopUps;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTopUps;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTopUps;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateTopUps;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\TopUp;
use Illuminate\Validation\Rule;

class UpdateTopUp extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(TopUp $topUp, array $modelData): TopUp
    {
        $this->update($topUp, $modelData);


        if ($topUp->wasChanged('status')) {
            GroupHydrateTopUps::dispatch($topUp->group)->delay($this->hydratorsDelay);
            OrganisationHydrateTopUps::dispatch($topUp->organisation)->delay($this->hydratorsDelay);
            ShopHydrateTopUps::dispatch($topUp->shop)->delay($this->hydratorsDelay);
            CustomerHydrateTopUps::dispatch($topUp->customer_id)->delay($this->hydratorsDelay);
        }

        return $topUp;
    }

    public function rules(): array
    {
        $rules = [
            'status' => ['sometimes', 'required', Rule::enum(TopUpStatusEnum::class)],

        ];
        if (!$this->strict) {
            $rules['amount']     = ['sometimes', 'numeric'];
            $rules['org_amount'] = ['sometimes', 'numeric'];
            $rules['grp_amount'] = ['sometimes', 'numeric'];
            $rules               = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(TopUp $topUp, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): TopUp
    {
        $this->strict = $strict;
        if (!$audit) {
            TopUp::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($topUp->organisation, $modelData);

        return $this->handle($topUp, $modelData);
    }
}
