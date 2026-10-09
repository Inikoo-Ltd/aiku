<?php

namespace App\Actions\CRM\AppointmentType;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreAppointmentType extends OrgAction
{
    use WithAppointmentTypeRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): AppointmentType
    {
        $availability = $this->pullAvailability($modelData);

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        return DB::transaction(function () use ($shop, $modelData, $availability) {
            /** @var AppointmentType $appointmentType */
            $appointmentType = $shop->appointmentTypes()->create($modelData);

            $this->saveDates($appointmentType, $availability);

            return $appointmentType->refresh();
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return $this->appointmentTypeRules(isUpdate: false);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): AppointmentType
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(AppointmentType $appointmentType): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.appointments.types.index', [
            'organisation' => $appointmentType->organisation->slug,
            'shop'         => $appointmentType->shop->slug,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData): AppointmentType
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
