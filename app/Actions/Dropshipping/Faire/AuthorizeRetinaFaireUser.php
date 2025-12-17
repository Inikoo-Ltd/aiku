<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Faire;

use App\Actions\Dropshipping\Faire\Traits\WithFaireApiRequest;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Lorisleiva\Actions\ActionRequest;

class AuthorizeRetinaFaireUser extends OrgAction
{
    use WithFaireApiRequest;
    use WithActionUpdate;

    public function handle(Customer $customer): string
    {
        return $this->getFaireAuthorizationUrl($customer->id);
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(ActionRequest $request): string
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer);
    }
}
