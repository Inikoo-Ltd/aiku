<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetApiToken extends RetinaAction
{
    use AsAction;


    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        $existingToken = $customerSalesChannel->tokens()->where('name', 'api-token')->first();

        if ($existingToken) {
            $existingToken->delete();

            $newToken = $customerSalesChannel->createToken('api-token', ['retina']);

            return [
                'token' => $newToken->plainTextToken,
            ];
        }

        $token = $customerSalesChannel->createToken('api-token', ['retina']);

        return [
            'token' => $token->plainTextToken,
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

    public function jsonResponse(array $data): array
    {
        return $data;
    }
}
