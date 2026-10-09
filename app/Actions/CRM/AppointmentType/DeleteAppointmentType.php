<?php

namespace App\Actions\CRM\AppointmentType;

use App\Actions\OrgAction;
use App\Models\CRM\AppointmentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteAppointmentType extends OrgAction
{
    public function handle(AppointmentType $appointmentType): AppointmentType
    {
        $appointmentType->delete();

        return $appointmentType;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(AppointmentType $appointmentType, ActionRequest $request): AppointmentType
    {
        $this->initialisationFromShop($appointmentType->shop, $request);

        return $this->handle($appointmentType);
    }

    public function htmlResponse(AppointmentType $appointmentType): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.appointments.types.index', [
            'organisation' => $appointmentType->organisation->slug,
            'shop'         => $appointmentType->shop->slug,
        ]);
    }

    public function action(AppointmentType $appointmentType): AppointmentType
    {
        $this->asAction = true;
        $this->initialisationFromShop($appointmentType->shop, []);

        return $this->handle($appointmentType);
    }
}
