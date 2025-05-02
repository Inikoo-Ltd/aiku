<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jan 2025 18:10:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Space;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateSpaces;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateSpaces;
use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSpaces;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSpaces;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Billables\Rental\RentalTypeEnum;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Space;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreSpace extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithActionUpdate;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): Space
    {
        data_set($modelData, 'organisation_id', $fulfilmentCustomer->organisation_id);
        data_set($modelData, 'group_id', $fulfilmentCustomer->group_id);
        data_set($modelData, 'fulfilment_id', $fulfilmentCustomer->fulfilment_id);

        $state = SpaceStateEnum::RESERVED;

        $startAt = Carbon::parse(Arr::get($modelData, 'start_at', now()));

        $dateWithoutTime = Carbon::parse($startAt)->startOfDay();
        $today           = Carbon::today();

        if ($dateWithoutTime->isBefore($today)) {
            $state = SpaceStateEnum::RENTING;
        }

        data_set($modelData, 'state', $state);
        /** @var Space $space */
        $space = $fulfilmentCustomer->spaces()->create($modelData);

        FulfilmentCustomerHydrateSpaces::dispatch($fulfilmentCustomer);
        FulfilmentHydrateSpaces::dispatch($fulfilmentCustomer->fulfilment);
        OrganisationHydrateSpaces::dispatch($fulfilmentCustomer->organisation);
        GroupHydrateSpaces::dispatch($fulfilmentCustomer->group);

        if ($space->state === SpaceStateEnum::RENTING) {
            $currentRecurringBill = $fulfilmentCustomer->currentRecurringBill;
            if (!$currentRecurringBill) {
                $currentRecurringBill = StoreRecurringBill::make()->action(
                    rentalAgreement: $fulfilmentCustomer->rentalAgreement,
                    modelData: [
                        'start_date' => now(),
                    ],
                    strict: true
                );
                $fulfilmentCustomer->update(
                    [
                        'current_recurring_bill_id' => $currentRecurringBill->id
                    ]
                );
            }

            $this->update($space, [
                'current_recurring_bill_id' => $currentRecurringBill->id
            ]);

            StoreRecurringBillTransaction::make()->action(
                $currentRecurringBill,
                $space,
                [
                    'start_date' => $space->start_at,
                    'end_date'   => $currentRecurringBill->end_date,
                    'quantity'   => 1,
                ]
            );
        }

        return $space;
    }

    public function rules(): array
    {
        return [
            'reference'       => ['required', 'max:64', 'string'],
            'exclude_weekend' => ['required', 'bool'],
            'start_at'        => ['required', 'date'],
            'end_at'          => ['nullable', 'date'],
            'rental_id'       => [
                'required',
                'integer',
                Rule::exists('rentals', 'id')
                    ->where('fulfilment_id', $this->fulfilment->id)
                    ->where('type', RentalTypeEnum::SPACE)
            ],
        ];
    }

    public function htmlResponse(Space $space): Response
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.spaces.index', [
            'organisation'       => $space->organisation->slug,
            'fulfilment'         => $space->fulfilment->slug,
            'fulfilmentCustomer' => $space->fulfilmentCustomer->slug
        ]);
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Space
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): Space
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }
}
