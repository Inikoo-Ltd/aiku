<?php

namespace App\Actions\Fulfilment\Space;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateSpaces;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateSpaces;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSpaces;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSpaces;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StopSpaceRental extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

    public function handle(Space $space, array $modelData): Space
    {
        if ($space->state !== SpaceStateEnum::RENTING) {
            abort(422);
        }

        $endAt = Carbon::parse($modelData['end_at']);

        $space->update([
            'state'  => SpaceStateEnum::FINISHED,
            'end_at' => $endAt,
        ]);
        $space->refresh();

        $fulfilmentCustomer = $space->fulfilmentCustomer;
        if ($fulfilmentCustomer) {
            FulfilmentCustomerHydrateSpaces::dispatch($fulfilmentCustomer);
            FulfilmentHydrateSpaces::dispatch($fulfilmentCustomer->fulfilment);
            OrganisationHydrateSpaces::dispatch($fulfilmentCustomer->organisation);
            GroupHydrateSpaces::dispatch($fulfilmentCustomer->group);
        }

        return $space;
    }

    public function rules(): array
    {
        return [
            'end_at' => ['required', 'date'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->has('end_at')) {
            $this->set('end_at', now());
        }
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, Space $space, ActionRequest $request): Space
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        if ($space->fulfilment_customer_id !== $fulfilmentCustomer->id) {
            abort(404);
        }

        return $this->handle($space, $this->validatedData);
    }

    public function htmlResponse(Space $space): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.spaces.show', [
            'organisation'       => $space->organisation->slug,
            'fulfilment'         => $space->fulfilment->slug,
            'fulfilmentCustomer' => $space->fulfilmentCustomer->slug,
            'space'              => $space->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Rental stopped.'),
        ]);
    }
}
