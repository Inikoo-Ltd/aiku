<?php

namespace App\Actions\CRM\Appointment\GoogleCalendar;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CallbackShopGoogleCalendar extends OrgAction
{
    use WithActionUpdate;

    /**
     * @param array{shop_id: int, user_id: int, return: string} $state
     *
     * @throws RequestException
     */
    public function handle(Shop $shop, string $code, array $state): Shop
    {
        $tokens       = GoogleCalendarClient::exchangeCode($code);
        $refreshToken = Arr::get($tokens, 'refresh_token');

        if (!$refreshToken) {
            throw ValidationException::withMessages([
                'google_calendar' => __('Google did not grant offline access. Remove Aiku from the Google account permissions and connect again.'),
            ]);
        }

        $settings = $shop->settings ?? [];
        data_set($settings, GoogleCalendarClient::SETTINGS_KEY, [
            'calendar'             => GoogleCalendarClient::calendarName((string) Arr::get($tokens, 'access_token')),
            'refresh_token'        => Crypt::encryptString($refreshToken),
            'connected_at'         => now()->toIso8601String(),
            'connected_by_user_id' => $state['user_id'],
        ]);

        $shop = $this->update($shop, ['settings' => $settings]);

        Cache::forget(GoogleCalendarClient::accessTokenCacheKey($shop));

        $shop->appointments()
            ->whereIn('state', AppointmentStateEnum::holdingSlot())
            ->where('ends_at', '>', now())
            ->pluck('id')
            ->each(fn (int $appointmentId, int $index) => SyncAppointmentGoogleCalendarEvent::dispatch($appointmentId)->delay(now()->addSeconds($index)));

        return $shop;
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return Redirect::route('grp.dashboard.show')->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Calendar not connected'),
                'description' => (string) $request->query('error'),
            ]);
        }

        $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);
        $shop  = Shop::findOrFail($state['shop_id']);

        $this->initialisationFromShop($shop, $request);

        try {
            $this->handle($shop, (string) $request->query('code'), $state);
        } catch (RequestException $exception) {
            return Redirect::to($state['return'])->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Calendar not connected'),
                'description' => Arr::get($exception->response->json(), 'error.message') ?? Arr::get($exception->response->json(), 'error_description') ?? $exception->getMessage(),
            ]);
        } catch (ValidationException $exception) {
            return Redirect::to($state['return'])->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Calendar not connected'),
                'description' => collect($exception->errors())->flatten()->first(),
            ]);
        }

        return Redirect::to($state['return'])->with('notification', [
            'status'      => 'success',
            'title'       => __('Google Calendar connected'),
            'description' => __('Appointments of this shop are now written into this calendar.'),
        ]);
    }
}
