<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 14:28:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Http\Resources\Accounting\PaymentServiceProviderResource;
use App\Models\Accounting\PaymentServiceProvider;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePaymentServiceProvider extends OrgAction
{
    use WithActionUpdate;

    private PaymentServiceProvider $paymentServiceProvider;

    public function handle(PaymentServiceProvider $paymentServiceProvider, array $modelData): PaymentServiceProvider
    {
        return $this->update($paymentServiceProvider, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("sysadmin.edit");
    }

    public function rules(): array
    {
        return [
            'code'      => [
                'sometimes',
                'required',
                'max:16',
                'alpha_dash',
                Rule::enum(PaymentServiceProviderEnum::class),
                new IUnique(
                    table: 'payment_service_providers',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->paymentServiceProvider->id
                            ]
                    ]
                ),
            ],
            'name'      => [
                'sometimes',
                'required',
                'max:255',
                'string',
            ],
            'type'      => [ 'sometimes','required', Rule::enum(PaymentServiceProviderTypeEnum::class)],

        ];
    }



    public function action(PaymentServiceProvider $paymentServiceProvider, $modelData): PaymentServiceProvider
    {
        $this->asAction               = true;
        $this->paymentServiceProvider = $paymentServiceProvider;
        $this->initialisationFromGroup($paymentServiceProvider->group, $modelData);

        return $this->handle($paymentServiceProvider, $this->validatedData);
    }

    public function jsonResponse(PaymentServiceProvider $paymentServiceProvider): PaymentServiceProviderResource
    {
        return new PaymentServiceProviderResource($paymentServiceProvider);
    }
}
