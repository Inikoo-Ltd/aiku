<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Oct 2025 12:28:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerComms;

use App\Actions\CRM\Customer\SaveCustomerInAurora;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\CustomerComms;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomerComms extends OrgAction
{
    use WithActionUpdate;
    use WithCRMEditAuthorisation;


    public function handle(CustomerComms $customerComms, array $modelData, bool $updateAiku = true): CustomerComms
    {
        $this->update($customerComms, $modelData);

        $changes = Arr::except($customerComms->getChanges(), ['updated_at', 'last_fetched_at']);

        if (Arr::hasAny($changes, [
                'is_subscribed_to_newsletter',
                'is_subscribed_to_marketing',
                'is_subscribed_to_abandoned_cart',
                'is_subscribed_to_reorder_reminder',
                'is_subscribed_to_basket_low_stock',
                'is_subscribed_to_basket_reminder',
            ])
            && $customerComms->customer->shop->is_aiku
            && $updateAiku) {
            //SaveCustomerInAurora::dispatch($customerComms->customer);
        }

        return $customerComms;
    }

    public function rules(): array
    {
        return [
            'is_subscribed_to_newsletter'       => ['sometimes', 'boolean'],
            'is_subscribed_to_marketing'        => ['sometimes', 'boolean'],
            'is_subscribed_to_abandoned_cart'   => ['sometimes', 'boolean'],
            'is_subscribed_to_reorder_reminder' => ['sometimes', 'boolean'],
            'is_subscribed_to_basket_low_stock' => ['sometimes', 'boolean'],
            'is_subscribed_to_basket_reminder'  => ['sometimes', 'boolean'],
        ];
    }


    public function asController(CustomerComms $customerComms, ActionRequest $request): CustomerComms
    {
        $this->initialisationFromShop($customerComms->customer->shop, $request);

        return $this->handle($customerComms, $this->validatedData);
    }

}
