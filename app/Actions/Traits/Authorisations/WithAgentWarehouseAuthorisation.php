<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 15:41
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithAgentWarehouseAuthorisation
{
    use WithAgentAreaAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->authorizeAgentArea($request, 'warehouse');
    }
}
