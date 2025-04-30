<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateRecurringBills;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateRecurringBills;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRecurringBills;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRecurringBills;
use App\Actions\Traits\WithGetRecurringBillEndDate;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RentalAgreement;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class StoreRecurringBill extends OrgAction
{
    use WithGetRecurringBillEndDate;

    public function handle(RentalAgreement $rentalAgreement, array $modelData): RecurringBill
    {
        if (!Arr::exists($modelData, 'tax_category_id')) {
            data_set(
                $modelData,
                'tax_category_id',
                GetTaxCategory::run(
                    country: $this->organisation->country,
                    taxNumber: $rentalAgreement->fulfilmentCustomer->customer->taxNumber,
                    billingAddress: $rentalAgreement->fulfilmentCustomer->customer->address,
                    deliveryAddress: $rentalAgreement->fulfilmentCustomer->customer->address,
                )->id
            );
        }


        data_set($modelData, 'currency_id', $rentalAgreement->fulfilment->shop->currency_id, overwrite: false);
        data_set($modelData, 'organisation_id', $rentalAgreement->organisation_id);
        data_set($modelData, 'group_id', $rentalAgreement->group_id);
        data_set($modelData, 'fulfilment_id', $rentalAgreement->fulfilment_id);
        data_set($modelData, 'fulfilment_customer_id', $rentalAgreement->fulfilment_customer_id);

        if (!Arr::exists($modelData, 'start_end')) {
            if (!$modelData['start_date'] instanceof Carbon) {
                $modelData['start_date'] = Carbon::parse($modelData['start_date']);
            }
            $endDate = $this->getEndDate(
                $modelData['start_date']->copy(),
                Arr::get(
                    $rentalAgreement->fulfilment->settings,
                    'rental_agreement_cut_off.'.$rentalAgreement->billing_cycle->value
                )
            );

            data_set($modelData, 'end_date', $endDate);
        }

        data_set(
            $modelData,
            'reference',
            GetSerialReference::run(
                container: $rentalAgreement->fulfilmentCustomer,
                modelType: SerialReferenceModelEnum::RECURRING_BILL
            )
        );


        /** @var RecurringBill $recurringBill */
        $recurringBill = $rentalAgreement->recurringBills()->create($modelData);
        $recurringBill->stats()->create();
        $recurringBill->refresh();

        $rentalAgreement->fulfilmentCustomer->update(
            [
                'current_recurring_bill_id' => $recurringBill->id
            ]
        );

        if ($this->strict) {
            FindStoredPalletsAndAttachThemToNewRecurringBill::make()->action($recurringBill);
            FindSpacesAndAttachThemToNewRecurringBill::make()->action($recurringBill);
        }

        GroupHydrateRecurringBills::dispatch($recurringBill->group)->delay($this->hydratorsDelay);
        OrganisationHydrateRecurringBills::dispatch($recurringBill->organisation)->delay($this->hydratorsDelay);
        FulfilmentHydrateRecurringBills::dispatch($recurringBill->fulfilment)->delay($this->hydratorsDelay);
        FulfilmentCustomerHydrateRecurringBills::dispatch($recurringBill->fulfilmentCustomer)->delay($this->hydratorsDelay);

        return $recurringBill;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date'   => ['sometimes', 'required', 'date', 'gte:start_date'],
        ];
    }

    public function action(RentalAgreement $rentalAgreement, array $modelData, int $hydratorsDelay = 0, bool $strict = false): RecurringBill
    {
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($rentalAgreement->fulfilment->shop, $modelData);

        return $this->handle($rentalAgreement, $this->validatedData);
    }


}
