<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount\Types;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateBankPaymentAccount extends OrgAction
{
    use WithActionUpdate;

    public OrgPaymentServiceProvider|PaymentServiceProvider $parent;

    public function handle(PaymentAccount $paymentAccount, array $modelData): PaymentAccount
    {
        foreach ($modelData as $key => $value) {
            data_set(
                $modelData,
                match ($key) {
                    'bank_name'         => 'data.bank.name',
                    'bank_account_name' => 'data.bank.recipient',
                    'bank_account_id'   => 'data.bank.account',
                    'bank_iban'         => 'data.bank.iban',
                    'bank_swift_code'   => 'data.bank.swift',
                    'bank_sort_code'    => 'data.bank.sort_code',
                    default             => $key
                },
                $value
            );
            Arr::forget($modelData, $key);
        }

        return $this->update($paymentAccount, Arr::only($modelData, 'data'), ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'bank_name'         => ['sometimes', 'string'],
            'bank_account_name' => ['sometimes', 'string'],
            'bank_account_id'   => ['sometimes', 'nullable', 'string'],
            'bank_iban'         => ['sometimes', 'nullable', 'string'],
            'bank_swift_code'   => ['sometimes', 'nullable', 'string'],
            'bank_sort_code'    => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function action(PaymentAccount $paymentAccount, array $modelData): PaymentAccount
    {
        $this->asAction = true;
        $this->initialisation($paymentAccount->organisation, $modelData);

        return $this->handle($paymentAccount, $this->validatedData);
    }
}
