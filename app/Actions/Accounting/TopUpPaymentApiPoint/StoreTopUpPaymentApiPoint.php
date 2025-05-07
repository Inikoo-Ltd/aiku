<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 18:11:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint;

use App\Actions\RetinaAction;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreTopUpPaymentApiPoint extends RetinaAction
{
    use AsObject;

    public function handle(Customer $customer, array $modelData): TopUpPaymentApiPoint
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'ulid', Str::ulid());

        return $customer->topUpPaymentApiPoint()->create($modelData);


    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric','gt:0','max:1000000'],
        ];
    }


    public function asController(ActionRequest $request): TopUpPaymentApiPoint
    {
        $this->initialisation($request);
        return $this->handle($this->customer, $this->validatedData);
    }


    public function htmlResponse(TopUpPaymentApiPoint $topUpPaymentApiPoint): RedirectResponse
    {
        return Inertia::location(route('retina.top_up.checkout', [
            'topUpPaymentApiPoint' => $topUpPaymentApiPoint->id
        ]));
    }






}
