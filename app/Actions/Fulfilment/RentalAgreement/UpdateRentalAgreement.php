<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreement;

use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStatus;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDeliveryFulfilmentTransactionClause;
use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturnFulfilmentTransactionClause;
use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionAmounts;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionCurrencyExchangeRates;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionDiscountPercentage;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionTemporalQuantity;
use App\Actions\Fulfilment\RentalAgreement\Hydrators\RentalAgreementHydrateClauses;
use App\Actions\Fulfilment\RentalAgreementClause\RemoveRentalAgreementClause;
use App\Actions\Fulfilment\RentalAgreementClause\StoreRentalAgreementClause;
use App\Actions\Fulfilment\RentalAgreementClause\UpdateRentalAgreementClause;
use App\Actions\Fulfilment\RentalAgreementSnapshot\StoreRentalAgreementSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Fulfilment\RentalAgreementClause\RentalAgreementCauseStateEnum;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RentalAgreement;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class UpdateRentalAgreement extends OrgAction
{
    use WithActionUpdate;

    private FulfilmentCustomer $parent;
    private WebUser $webUser;

    public function handle(RentalAgreement $rentalAgreement, array $modelData): RentalAgreement
    {
        $updateTrigger = Arr::get($modelData, 'update_all');
        data_forget($modelData, 'update_all');
        $oldData = [
            'billing_cycle' => $rentalAgreement->billing_cycle,
            'pallets_limit' => $rentalAgreement->pallets_limit,
        ];

        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this->update($rentalAgreement, Arr::except($modelData, ['clauses']));

        $wasUpdated = false;

        if ($rentalAgreement->wasChanged()) {
            $wasUpdated = true;
            $newData = [
                'billing_cycle' => $rentalAgreement->billing_cycle,
                'pallets_limit' => $rentalAgreement->pallets_limit,
            ];


            foreach ($oldData as $key => $value) {
                if ($oldData[$key] == $newData[$key]) {
                    data_forget($oldData, $key);
                    data_forget($newData, $key);
                }
            }


            $customer = $rentalAgreement->fulfilmentCustomer->customer;
            $customer->auditEvent = 'update';
            $customer->isCustomEvent = true;
            $customer->auditCustomOld = $oldData;
            $customer->auditCustomNew = $newData;
            Event::dispatch(new AuditCustom($customer));
        }


        $currentAssetsInClauses = $rentalAgreement->clauses()->pluck('id', 'asset_id')->toArray();


        $clausesUpdated = 0;
        $clausesAdded = 0;
        $clausesRemoved = 0;


        if (Arr::has($modelData, 'clauses')) {
            $clauses = Arr::get($modelData, 'clauses', []);


            foreach ($clauses as $clauseData) {
                foreach ($clauseData as $data) {
                    if (array_key_exists($data['asset_id'], $currentAssetsInClauses)) {
                        $clause = $rentalAgreement->clauses()->find($currentAssetsInClauses[$data['asset_id']]);
                        UpdateRentalAgreementClause::run($clause, $data);
                        unset($currentAssetsInClauses[$data['asset_id']]);
                        $clausesUpdated++;
                    } else {
                        $data['state'] = match ($rentalAgreement->state) {
                            RentalAgreementStateEnum::ACTIVE => RentalAgreementCauseStateEnum::ACTIVE,
                            default => RentalAgreementCauseStateEnum::DRAFT
                        };
                        StoreRentalAgreementClause::run($rentalAgreement, $data);
                        $clausesAdded++;
                    }
                }
            }

            foreach ($currentAssetsInClauses as $clauseId) {
                $clause = $rentalAgreement->clauses()->find($clauseId);
                RemoveRentalAgreementClause::run($clause);
                $clausesRemoved++;
            }


            if ($clausesAdded || $clausesUpdated || $clausesRemoved) {
                $wasUpdated = true;
                RentalAgreementHydrateClauses::run($rentalAgreement);
            }
        }

        if ($wasUpdated) {

            StoreRentalAgreementSnapshot::run(
                $rentalAgreement,
                false,
                [
                    'clauses_added' => $clausesAdded,
                    'clauses_updated' => $clausesUpdated,
                    'clauses_removed' => $clausesRemoved
                ]
            );

            if ($rentalAgreement->fulfilmentCustomer->currentRecurringBill) {
                foreach (RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->where('id', $rentalAgreement->fulfilmentCustomer->currentRecurringBill->id)->get() as $recurringBill) {

                    $transactions = $recurringBill->transactions()->get();

                    foreach ($transactions as $transaction) {
                        $transaction = CalculateRecurringBillTransactionDiscountPercentage::make()->action($transaction);
                        $transaction = CalculateRecurringBillTransactionTemporalQuantity::run($transaction);
                        $transaction = CalculateRecurringBillTransactionAmounts::run($transaction);
                        CalculateRecurringBillTransactionCurrencyExchangeRates::run($transaction);
                    }

                    CalculateRecurringBillTotals::make()->action($recurringBill);

                }

            }
        }

        UpdateWebUser::make()->action($this->webUser, Arr::only($modelData, ['username', 'email']));
        FulfilmentCustomerHydrateStatus::run($rentalAgreement->fulfilmentCustomer);

        $rentalAgreement->refresh();

        foreach ($rentalAgreement->fulfilmentCustomer->palletDeliveries as $delivery) {
            if ($delivery->state == PalletDeliveryStateEnum::IN_PROCESS) {
                UpdatePalletDeliveryFulfilmentTransactionClause::run($delivery);
            }
        }
        foreach ($rentalAgreement->fulfilmentCustomer->palletReturns as $return) {
            if ($return->state == PalletReturnStateEnum::IN_PROCESS) {
                UpdatePalletReturnFulfilmentTransactionClause::run($return);
            }
        }

        if ($updateTrigger === true && $rentalAgreement->state === RentalAgreementStateEnum::ACTIVE) {
            foreach ($rentalAgreement->fulfilmentCustomer->currentRecurringBill->palletDeliveries as $delivery) {
                UpdatePalletDeliveryFulfilmentTransactionClause::run($delivery);
            }
            foreach ($rentalAgreement->fulfilmentCustomer->currentRecurringBill->palletReturns as $return) {
                UpdatePalletReturnFulfilmentTransactionClause::run($return);
            }
            foreach ($rentalAgreement->fulfilmentCustomer->currentRecurringBill->transactions as $transaction) {
                CalculateRecurringBillTransactionDiscountPercentage::make()->action($transaction);
            }
        }

        return $rentalAgreement;
    }

    public function rules(): array
    {
        return [
            'state' => ['sometimes', Rule::enum(RentalAgreementStateEnum::class)],
            'update_all' => ['sometimes'],
            'billing_cycle' => ['sometimes', Rule::enum(RentalAgreementBillingCycleEnum::class)],
            'pallets_limit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'clauses' => ['sometimes', 'array'],
            'clauses.rentals.*.asset_id' => [
                'sometimes',
                Rule::exists('assets', 'id')

            ],
            'clauses.rentals.*.percentage_off' => ['sometimes', 'numeric', 'gt:0', 'lte:100'],
            'clauses.services.*.asset_id' => [
                'sometimes',
                Rule::exists('assets', 'id')

            ],
            'clauses.services.*.percentage_off' => ['sometimes', 'numeric', 'gt:0', 'lte:100'],
            'clauses.physical_goods.*.asset_id' => [
                'sometimes',
                Rule::exists('assets', 'id')

            ],
            'clauses.physical_goods.*.percentage_off' => ['sometimes', 'numeric', 'gt:0', 'lte:100'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->shop->website->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->webUser->id, 'operator' => '!='],
                    ]
                ),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'max:255',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->shop->website->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->webUser->id, 'operator' => '!='],
                    ]
                ),
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $clauses = $this->get('clauses', []);
        foreach ($clauses as $clauseType => $clauseData) {
            foreach ($clauseData as $key => $clause) {
                if (!Arr::get($clause, 'percentage_off', 0)) {
                    unset($clauses[$clauseType][$key]);
                }
            }
        }
        $this->set('clauses', $clauses);
    }

    public function action(RentalAgreement $rentalAgreement, array $modelData): RentalAgreement
    {
        /** @var WebUser $webUser */
        $webUser = $rentalAgreement
            ->fulfilmentCustomer
            ->customer
            ->webUsers()
            ->first();
        $this->webUser = $webUser;

        $this->asAction = true;
        $this->initialisationFromShop($rentalAgreement->fulfilment->shop, $modelData);

        return $this->handle($rentalAgreement, $this->validatedData);
    }

    public function asController(RentalAgreement $rentalAgreement, ActionRequest $request): RentalAgreement
    {
        /** @var WebUser $webUser */
        $webUser = $rentalAgreement
            ->fulfilmentCustomer
            ->customer
            ->webUsers()
            ->first();
        $this->webUser = $webUser;
        $fulfilmentCustomer = $rentalAgreement->fulfilmentCustomer;
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromShop($fulfilmentCustomer->fulfilment->shop, $request);

        return $this->handle($rentalAgreement, $this->validatedData);
    }
}
