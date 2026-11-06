<?php

namespace App\Actions\CRM\AppointmentType;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\AppointmentType;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateAppointmentType extends OrgAction
{
    use WithActionUpdate;
    use WithAppointmentTypeRules;

    private AppointmentType $appointmentType;

    /**
     * @throws \Throwable
     */
    public function handle(AppointmentType $appointmentType, array $modelData): AppointmentType
    {
        $availability = $this->pullAvailability($modelData);
        $modelData    = $this->applyStartTimes($this->applyMeetingPlace($modelData, $appointmentType));

        $previousMeetingMode = $appointmentType->meeting_mode;

        return DB::transaction(function () use ($appointmentType, $modelData, $availability, $previousMeetingMode) {
            $appointmentType = $this->update($appointmentType, $modelData);

            $this->saveDates($appointmentType, $availability);

            if ($appointmentType->meeting_mode !== $previousMeetingMode) {
                $this->syncStaffOfMeetingMode($appointmentType);
            }

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
        return $this->appointmentTypeRules(isUpdate: true, appointmentType: $this->appointmentType);
    }

    /**
     * @throws \Throwable
     */
    public function asController(AppointmentType $appointmentType, ActionRequest $request): AppointmentType
    {
        $this->appointmentType = $appointmentType;
        $this->initialisationFromShop($appointmentType->shop, $request);

        return $this->handle($appointmentType, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(AppointmentType $appointmentType, array $modelData): AppointmentType
    {
        $this->asAction        = true;
        $this->appointmentType = $appointmentType;
        $this->initialisationFromShop($appointmentType->shop, $modelData);

        return $this->handle($appointmentType, $this->validatedData);
    }
}
