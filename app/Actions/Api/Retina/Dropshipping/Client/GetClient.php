<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Client;

use App\Actions\Api\Group\Resources\CustomerClientApiResource;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerClient;
use Lorisleiva\Actions\ActionRequest;

class GetClient extends RetinaApiAction
{
    public function handle(CustomerClient $customerClient): CustomerClient
    {
        return $customerClient;
    }

    public function asController(CustomerClient $customerClient, ActionRequest $request): CustomerClient
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($customerClient);
    }

    public function jsonResponse(CustomerClient $customerClient): CustomerClientApiResource
    {
        return CustomerClientApiResource::make($customerClient);
    }
}
