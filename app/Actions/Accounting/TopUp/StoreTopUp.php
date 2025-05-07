<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUp;

use App\Actions\Accounting\TopUp\Search\TopUpRecordSearch;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopUps;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTopUps;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTopUps;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateTopUps;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\TopUp;
use Illuminate\Validation\Rule;

class StoreTopUp extends OrgAction
{
    use WithNoStrictRules;

    public function handle(Payment $payment, array $modelData): TopUp
    {
        data_set($modelData, 'group_id', $payment->group_id);
        data_set($modelData, 'organisation_id', $payment->organisation_id);
        data_set($modelData, 'currency_id', $payment->currency_id);
        data_set($modelData, 'customer_id', $payment->customer_id);
        data_set($modelData, 'shop_id', $payment->shop_id);


        data_set(
            $modelData,
            'org_amount',
            GetCurrencyExchange::run($payment->currency, $payment->organisation->currency) * $modelData['amount'],
            false
        );

        data_set(
            $modelData,
            'grp_amount',
            GetCurrencyExchange::run($payment->currency, $payment->group->currency) * $modelData['amount'],
            false
        );


        /** @var TopUp $topUp */
        $topUp = $payment->topUp()->create($modelData);


        CustomerHydrateTopUps::dispatch($topUp->customer)->delay($this->hydratorsDelay);
        ShopHydrateTopUps::dispatch($topUp->shop)->delay($this->hydratorsDelay);
        OrganisationHydrateTopUps::dispatch($topUp->organisation)->delay($this->hydratorsDelay);
        GroupHydrateTopUps::dispatch($topUp->group)->delay($this->hydratorsDelay);

        TopUpRecordSearch::dispatch($topUp);

        return $topUp;
    }

    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'numeric'],
            'status' => ['sometimes', 'required', Rule::enum(TopUpStatusEnum::class)],
            'reference' => ['sometimes', 'required'],

        ];
        if (!$this->strict) {
            $rules['org_amount'] = ['required', 'numeric'];
            $rules['grp_amount'] = ['required', 'numeric'];
            $rules               = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Payment $payment, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): TopUp
    {
        if (!$audit) {
            TopUp::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($payment->organisation, $modelData);

        return $this->handle($payment, $modelData);
    }

}
