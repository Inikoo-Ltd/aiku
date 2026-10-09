<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\OrgAction;
use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreAppointment extends OrgAction
{
    use WithAppointmentRules;

    public function handle(Shop $shop, array $modelData): Appointment
    {
        $modelData = $this->prepareAppointmentData($shop, $modelData);

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'state', AppointmentStateEnum::BOOKED, overwrite: false);
        data_set($modelData, 'source', AppointmentSourceEnum::STAFF, overwrite: false);

        /** @var Appointment $appointment */
        $appointment = $shop->appointments()->create($modelData);

        return $appointment;
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
        return [
            ...$this->appointmentRules(isUpdate: false),
            'source'             => ['sometimes', Rule::enum(AppointmentSourceEnum::class)],
            'created_by_user_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction) {
            $this->set('created_by_user_id', $request->user()->id);
        }
    }

    public function asController(Shop $shop, ActionRequest $request): Appointment
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Appointment $appointment): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.appointments.index', [
            'organisation' => $appointment->organisation->slug,
            'shop'         => $appointment->shop->slug,
        ]);
    }

    public function action(Shop $shop, array $modelData): Appointment
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
