<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Client;

use App\Actions\Dropshipping\CustomerClient\DeleteCustomerClient;
use App\Actions\RetinaApiAction;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\CustomerClient;
use Lorisleiva\Actions\ActionRequest;

class DeleteApiCustomerClient extends RetinaApiAction
{
    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient, array $modelData = []): CustomerClient
    {
        return DeleteCustomerClient::make()->action($customerClient, $modelData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerClient $customerClient, ActionRequest $request): CustomerClient
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($customerClient, $this->validatedData);
    }

    public function jsonResponse(CustomerClient $customerClient): CustomerClientResource
    {
        return CustomerClientResource::make($customerClient)
            ->additional([
                'message' => __('Client deleted successfully'),
            ]);
    }
}
