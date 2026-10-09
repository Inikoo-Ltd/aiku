<?php

namespace App\Actions\CRM\AppointmentStaff;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteAppointmentStaff extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, User $user): User
    {
        return UpdateAppointmentStaff::make()->handle($shop, $user, []);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, User $user, ActionRequest $request): User
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $user);
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
    public function action(Shop $shop, User $user): User
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, []);

        return $this->handle($shop, $user);
    }
}
