<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerSettings extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): Customer
    {
        return $this->update($customer, $modelData, ['settings']);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function rules(): array
    {
        return [];
    }

    public function asController(ActionRequest $request): Customer
    {

        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
