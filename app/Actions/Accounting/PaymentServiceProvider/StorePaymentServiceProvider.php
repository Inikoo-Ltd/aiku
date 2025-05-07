<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider;

use App\Actions\GrpAction;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePaymentServiceProvider extends GrpAction
{
    public function handle(Group $group, array $modelData): PaymentServiceProvider
    {
        /** @var PaymentServiceProvider $paymentServiceProvider */
        $paymentServiceProvider = $group->paymentServiceProviders()->create($modelData);
        $paymentServiceProvider->stats()->create();

        return $paymentServiceProvider;
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
                'required',
                'max:16',
                'alpha_dash',
                Rule::enum(PaymentServiceProviderEnum::class),
                new IUnique(
                    table: 'payment_service_providers',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'name'      => [
                'required',
                'max:255',
                'string',
            ],
            'type'      => ['required', Rule::enum(PaymentServiceProviderTypeEnum::class)],
        ];
    }

    public function action(Group $group, array $modelData): PaymentServiceProvider
    {
        $this->asAction = true;
        $this->initialisation($group, $modelData);
        return $this->handle($group, $this->validatedData);
    }



    public function htmlResponse(PaymentServiceProvider $paymentServiceProvider): RedirectResponse
    {
        return Redirect::route('grp.org.accounting.org_payment_service_providers.show', $paymentServiceProvider->slug);
    }
}
