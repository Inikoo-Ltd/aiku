<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount;

use App\Actions\Accounting\OrgPaymentServiceProvider\Hydrators\OrgPaymentServiceProviderHydratePaymentAccounts;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePaymentAccounts;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePaymentAccounts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePaymentAccounts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StorePaymentAccount extends OrgAction
{
    use WithNoStrictRules;
    public OrgPaymentServiceProvider|PaymentServiceProvider $parent;

    /**
     * @throws \Throwable
     */
    public function handle(OrgPaymentServiceProvider|PaymentServiceProvider $parent, array $modelData): PaymentAccount
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);

        if ($parent instanceof OrgPaymentServiceProvider) {
            data_set($modelData, 'payment_service_provider_id', $parent->payment_service_provider_id);
        }

        $paymentAccount = DB::transaction(function () use ($parent, $modelData) {
            /** @var PaymentAccount $paymentAccount */
            $paymentAccount = $parent->accounts()->create($modelData);
            $paymentAccount->stats()->create();

            if ($paymentAccount->type == PaymentAccountTypeEnum::ACCOUNT) {
                $paymentAccount->serialReferences()->create(
                    [
                        'model'           => SerialReferenceModelEnum::PAYMENT,
                        'organisation_id' => $paymentAccount->organisation->id,
                        'format'          => $paymentAccount->slug.'-%04d'
                    ]
                );
            }

            return $paymentAccount;
        });

        PaymentServiceProviderHydratePaymentAccounts::dispatch($parent->paymentServiceProvider)->delay($this->hydratorsDelay);
        OrganisationHydratePaymentAccounts::dispatch($parent->organisation)->delay($this->hydratorsDelay);
        GroupHydratePaymentAccounts::dispatch($parent->group)->delay($this->hydratorsDelay);
        OrgPaymentServiceProviderHydratePaymentAccounts::dispatch($parent)->delay($this->hydratorsDelay);

        return $paymentAccount;
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
        $rules = [
            'type'        => ['required', Rule::enum(PaymentAccountTypeEnum::class)],
            'code'        => [
                'required',
                'max:16',
                'alpha_dash',
                new IUnique(
                    table: 'payment_accounts',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id
                        ],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'is_accounts' => ['sometimes', 'boolean'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);

            $rules['data'] = ['sometimes', 'array'];
        }
        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): PaymentAccount
    {
        $this->asAction = true;
        $this->initialisation($organisation, $request);

        /** @var PaymentServiceProvider $paymentServiceProvider */
        $paymentServiceProvider = PaymentServiceProvider::where('code', $organisation->slug.'-'.Str::replace('-', '', $request->input('type')))
            ->first();

        return $this->handle($paymentServiceProvider, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function action(OrgPaymentServiceProvider $orgPaymentServiceProvider, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): PaymentAccount
    {
        if (!$audit) {
            PaymentAccount::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($orgPaymentServiceProvider->organisation, $modelData);

        return $this->handle($orgPaymentServiceProvider, $this->validatedData);
    }

    public string $commandSignature = 'payment-account:create {provider} {type} {code}';

    public function asCommand(Command $command): int
    {
        try {
            $provider = OrgPaymentServiceProvider::where('slug', $command->argument('provider'))->firstOrFail();
        } catch (Exception) {
            $command->error('Provider not found');

            return 1;
        }


        $this->setRawAttributes(
            [
                'code' => $command->argument('code'),
                'type' => $command->argument('type')
            ]
        );

        try {
            $validatedData = $this->validateAttributes();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        try {
            $this->handle($provider, $validatedData);
        } catch (Exception|Throwable) {
            $command->error('Error creating payment account');

            return 1;
        }


        return 0;
    }
}
