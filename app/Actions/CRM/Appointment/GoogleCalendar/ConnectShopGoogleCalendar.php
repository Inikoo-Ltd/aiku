<?php

namespace App\Actions\CRM\Appointment\GoogleCalendar;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ConnectShopGoogleCalendar extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function handle(Shop $shop, int $userId, ?string $section = null, bool $returnToAppointments = false): RedirectResponse
    {
        if (blank(config('services.google_calendar.client_id')) || blank(config('services.google_calendar.client_secret'))) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Calendar is not set up'),
                'description' => __('The Google Calendar app credentials are missing on this server. Ask a developer to set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.'),
            ]);
        }

        $returnUrl = route($returnToAppointments ? 'grp.org.shops.show.crm.appointments.settings' : 'grp.org.shops.show.settings.edit', [$shop->organisation->slug, $shop->slug])
            .'?'.http_build_query(array_filter(['section' => $section, 'google_calendar_connected' => 1]));

        $state = Crypt::encryptString(json_encode([
            'shop_id' => $shop->id,
            'user_id' => $userId,
            'return'  => $returnUrl,
        ]));

        return Redirect::away(GoogleCalendarClient::authorizationUrl($state));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request->user()->id, $request->query('section'), $request->query('from') === 'appointments');
    }
}
