<?php

namespace App\Actions\CRM\AppointmentStaff;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentType;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateAppointmentStaff extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, User $user, array $appointmentTypeIds): User
    {
        $appointmentTypeIds = array_map('intval', $appointmentTypeIds);

        DB::transaction(function () use ($shop, $user, $appointmentTypeIds) {
            $shop->appointmentTypes()->each(function (AppointmentType $appointmentType) use ($user, $appointmentTypeIds) {
                if (in_array($appointmentType->id, $appointmentTypeIds, true)) {
                    $appointmentType->attendees()->syncWithoutDetaching([$user->id]);
                } else {
                    $appointmentType->attendees()->detach($user->id);
                }
            });
        });

        return $user;
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
            'appointment_types'   => ['present', 'array'],
            'appointment_types.*' => [
                'integer',
                Rule::exists('appointment_types', 'id')->where('shop_id', $this->shop->id)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, User $user, ActionRequest $request): User
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $user, $this->validatedData['appointment_types']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.appointments.staff.index', [
            'organisation' => $this->shop->organisation->slug,
            'shop'         => $this->shop->slug,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, User $user, array $modelData): User
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $user, $this->validatedData['appointment_types']);
    }
}
