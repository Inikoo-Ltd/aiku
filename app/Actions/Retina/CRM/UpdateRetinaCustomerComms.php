<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Oct 2025 12:28:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerComms;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerComms extends RetinaAction
{
    use WithActionUpdate;


    public function handle(CustomerComms $customerComms, array $modelData): CustomerComms
    {
        return UpdateCustomerComms::run($customerComms, $modelData);
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


    public function asController(ActionRequest $request): CustomerComms
    {
        $this->initialisation($request);

        return $this->handle($this->customer->comms, $this->validatedData);
    }
}
