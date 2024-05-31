<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreement;

use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStatus;
use App\Actions\Fulfilment\RentalAgreementClause\UpdateRentalAgreementClause;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RentalAgreement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class UpdateRentalAgreement extends OrgAction
{
    use WithActionUpdate;

    private FulfilmentCustomer $parent;

    public function handle(RentalAgreement $rentalAgreement, array $modelData): RentalAgreement
    {

        $oldData = [
            'billing_cycle' => $rentalAgreement->billing_cycle,
            'pallets_limit' => $rentalAgreement->pallets_limit,
        ];

        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this->update($rentalAgreement, Arr::except($modelData, ['rental']));


        if ($rentalAgreement->wasChanged()) {
            $newData = [
                'billing_cycle' => $rentalAgreement->billing_cycle,
                'pallets_limit' => $rentalAgreement->pallets_limit,
            ];



            foreach($oldData as $key=>$value) {
                if($oldData[$key] == $newData[$key]) {
                    data_forget($oldData, $key);
                    data_forget($newData, $key);
                }
            }


            $customer                 =$rentalAgreement->fulfilmentCustomer->customer;
            $customer->auditEvent     = 'update';
            $customer->isCustomEvent  = true;
            $customer->auditCustomOld =$oldData;
            $customer->auditCustomNew = $newData;
            Event::dispatch(AuditCustom::class, [$customer]);

        }

        $causes=Arr::get($modelData, 'rental', []);
        // dd($causes);
        foreach ($rentalAgreement->clauses as $clause) {
            $productId = $clause->product_id;

            if (isset($modelData['rental']['rentals'])) {
                foreach ($modelData['rental']['rentals'] as $rentalData) {
                    if ($rentalData['product_id'] === $productId) {
                        UpdateRentalAgreementClause::run($clause, $rentalData);
                        break;
                    }
                }
            }

            if (isset($modelData['rental']['services'])) {
                foreach ($modelData['rental']['services'] as $serviceData) {
                    if ($serviceData['product_id'] === $productId) {
                        UpdateRentalAgreementClause::run($clause, $serviceData);
                        break;
                    }
                }
            }

            if (isset($modelData['rental']['physical_goods'])) {
                foreach ($modelData['rental']['physical_goods'] as $physicalGoodsData) {
                    if ($physicalGoodsData['product_id'] === $productId) {
                        UpdateRentalAgreementClause::run($clause, $physicalGoodsData);
                        break;
                    }
                }
            }
        }


        FulfilmentCustomerHydrateStatus::run($rentalAgreement->fulfilmentCustomer);

        return $rentalAgreement;
    }

    public function rules(): array
    {
        return [
            'billing_cycle'                  => ['sometimes', 'string', Rule::in(RentalAgreementBillingCycleEnum::values())],
            'pallets_limit'                  => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'rental'                         => ['sometimes', 'array'],
            'rental.rentals.*.product_id'    => ['sometimes',
                                         Rule::exists('products', 'id')

                ],
            'rental.rentals.*.agreed_price'  => ['sometimes', 'numeric', 'gt:0'],
            // 'rental.rentals.*.price'         => ['sometimes', 'numeric', 'gt:0'],
            'rental.services.*.product_id'    => ['sometimes',
                                         Rule::exists('products', 'id')

                ],
            'rental.services.*.agreed_price'  => ['sometimes', 'numeric', 'gt:0'],
            // 'rental.services.*.price'         => ['sometimes', 'numeric', 'gt:0'],
            'rental.physical_goods.*.product_id'    => ['sometimes',
                                         Rule::exists('products', 'id')

                ],
            'rental.physical_goods.*.agreed_price'  => ['sometimes', 'numeric', 'gt:0'],
            // 'rental.physical_goods.*.price'         => ['sometimes', 'numeric', 'gt:0'],
        ];
    }

    // public function afterValidator($validator)
    // {
    //     dd($validator);
    // }

    public function action(FulfilmentCustomer $fulfilmentCustomer, RentalAgreement $rentalAgreement, array $modelData): RentalAgreement
    {
        $this->asAction = true;
        $this->parent   = $fulfilmentCustomer;
        $this->initialisationFromShop($fulfilmentCustomer->fulfilment->shop, $modelData);

        return $this->handle($rentalAgreement, $this->validatedData);
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, RentalAgreement $rentalAgreement, ActionRequest $request): RentalAgreement
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromShop($fulfilmentCustomer->fulfilment->shop, $request);

        return $this->handle($rentalAgreement, $this->validatedData);
    }
}
