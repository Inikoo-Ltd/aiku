<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Api\Order;

use App\Services\QueryBuilder;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrders
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $query = QueryBuilder::for($request->user()->orders());

        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $request->merge([
            'state' => $request->get('state'),
        ]);
    }

}
