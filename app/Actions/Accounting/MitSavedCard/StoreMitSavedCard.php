<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 22:33:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\MitSavedCard;

use App\Actions\OrgAction;
use App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum;
use App\Models\Accounting\MitSavedCard;
use App\Models\CRM\Customer;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMitSavedCard extends OrgAction
{
    use AsAction;

    public function handle(Customer $customer, array $modelData): MitSavedCard
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'ulid', Str::ulid());

        $maxPriority = $customer->mitSavedCard()->where('state', MitSavedCardStateEnum::SUCCESS)->max('priority');
        data_set($modelData, 'priority', $maxPriority + 1);


        return $customer->mitSavedCard()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'payment_account_shop_id' => ['required', 'integer', Rule::exists('payment_account_shops', 'id')->where('shop_id', $this->shop->id)],
        ];
    }

    public function asAction(Customer $customer, array $modelData): MitSavedCard
    {
        $this->asAction = true;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $modelData);
    }


}
