<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreement;

use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStatus;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RentalAgreement;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRentalAgreement extends OrgAction
{
    use WithActionUpdate;

    private FulfilmentCustomer $parent;

    public function handle(RentalAgreement $rentalAgreement, array $modelData): RentalAgreement
    {
        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this->update($rentalAgreement, Arr::except($modelData, ['rental']));
        FulfilmentCustomerHydrateStatus::run($rentalAgreement->fulfilmentCustomer);
        return $rentalAgreement;
    }

    public function rules(): array
    {
        return [
            'billing_cycle'             => ['sometimes','string', Rule::in(RentalAgreementBillingCycleEnum::values())],
            'pallets_limit'             => ['sometimes','integer','min:1','max:10000'],
            'rental'                    => ['sometimes', 'array'],
            'rental.*.rental'           => ['sometimes', 'exists:rentals,id'],
            'rental.*.agreed_price'     => ['sometimes', 'numeric', 'gt:0'],
            'rental.*.price'            => ['sometimes', 'numeric', 'gt:0'],
        ];
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, RentalAgreement $rentalAgreement, array $modelData): RentalAgreement
    {
        $this->asAction       = true;
        $this->parent         = $fulfilmentCustomer;
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
