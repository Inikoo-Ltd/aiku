<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\CRM\Appointment\Widget\AppointmentWidgetKey;
use App\Models\Catalogue\Shop;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithAppointmentSettingsBlueprint
{
    /**
     * @return array<int, array{label: string, icon: string, fields: array<string, mixed>}>
     */
    public function appointmentSettingsBlueprint(Shop $shop, ActionRequest $request): array
    {
        return [
            [
                'label'  => __('Appointment settings'),
                'icon'   => 'fal fa-calendar-alt',
                'fields' => [
                    'appointment_widget_enabled' => [
                        'type'        => 'toggle',
                        'label'       => __('Booking widget for other websites'),
                        'value'       => (bool) Arr::get($shop->settings, 'appointments.widget_enabled', false),
                        'information' => __('Lets customers book appointments from a form placed on another website, such as WordPress or Shopify.'),
                    ],
                    ...Arr::get($shop->settings, 'appointments.widget_enabled', false) && Arr::get($shop->settings, 'appointments.widget_key') ? [
                        'appointment_widget_embed' => [
                            'type'        => 'textarea',
                            'disabled'    => true,
                            'rows'        => 3,
                            'label'       => __('Embed code'),
                            'value'       => AppointmentWidgetKey::make()->embedSnippet(Arr::get($shop->settings, 'appointments.widget_key')),
                            'information' => __('Paste this where the booking form should appear: a Custom HTML block in WordPress, a Custom Liquid section in Shopify.'),
                        ],
                    ] : [],
                    'appointment_notify_shop'    => [
                        'type'        => 'toggle',
                        'label'       => __('Email new bookings to the shop'),
                        'value'       => (bool) Arr::get($shop->settings, 'appointments.notify_shop', false),
                        'information' => __('Sends an email to the shop for every appointment booked on the website or through the booking widget. When off, the shop only sees new bookings in Aiku and in the connected Google Calendar.'),
                    ],
                    'appointment_notify_email'   => [
                        'type'        => 'input',
                        'label'       => __('Email for new bookings'),
                        'placeholder' => $shop->email,
                        'value'       => Arr::get($shop->settings, 'appointments.notify_email', ''),
                        'information' => __('Leave empty to use the shop email.'),
                    ],
                    'appointment_notify_staff'   => [
                        'type'        => 'toggle',
                        'label'       => __('Email appointments to the staff who arrange them'),
                        'value'       => (bool) Arr::get($shop->settings, 'appointments.notify_staff', true),
                        'information' => __('Emails the member of staff an appointment is given to, or everyone who arranges its type while it waits for an answer, when a customer books it and when it is given to them, moved or cancelled. They get a task for it either way.'),
                    ],
                ],
            ],
            [
                'label'  => __('Zoom'),
                'icon'   => 'fal fa-video',
                'fields' => [
                    'zoom_account_id'    => [
                        'type'        => 'input',
                        'label'       => __('Zoom account ID'),
                        'value'       => Arr::get($shop->settings, 'zoom.account_id', ''),
                        'information' => __('From the Server-to-Server OAuth app in the Zoom App Marketplace. Video call appointment types with Zoom as media get their own Zoom meeting when they are accepted.'),
                    ],
                    'zoom_client_id'     => [
                        'type'  => 'input',
                        'label' => __('Zoom client ID'),
                        'value' => Arr::get($shop->settings, 'zoom.client_id', ''),
                    ],
                    'zoom_client_secret' => [
                        'type'        => 'password',
                        'label'       => __('Zoom client secret'),
                        'value'       => '',
                        'information' => Arr::get($shop->settings, 'zoom.client_secret') ? __('A secret is saved. Enter a new one only to replace it.') : __('Saved encrypted.'),
                    ],
                    'zoom_host_email'    => [
                        'type'        => 'input',
                        'label'       => __('Zoom host email'),
                        'value'       => Arr::get($shop->settings, 'zoom.host_email', ''),
                        'information' => __('The Zoom user who hosts the meetings. Leave empty to use the user who created the Zoom app.'),
                    ],
                ],
            ],
            [
                'label'  => __('Google Calendar & Meet'),
                'icon'   => 'fab fa-google',
                'fields' => [
                    'google_calendar' => [
                        'type'         => 'mailbox_connect',
                        'noTitle'      => true,
                        'noSaveButton' => true,
                        'value'        => [
                            'connected'        => filled(Arr::get($shop->settings, GoogleCalendarClient::SETTINGS_KEY.'.refresh_token')),
                            'email'            => Arr::get($shop->settings, GoogleCalendarClient::SETTINGS_KEY.'.calendar'),
                            'connected_at'     => Arr::get($shop->settings, GoogleCalendarClient::SETTINGS_KEY.'.connected_at'),
                            'revoked_at'       => Arr::get($shop->settings, GoogleCalendarClient::SETTINGS_KEY.'.revoked_at'),
                            'connect_url'      => route('grp.org.shops.show.settings.google_calendar.connect', [
                                $shop->organisation->slug,
                                $shop->slug,
                                ...array_filter([
                                    'section' => $request->query('section'),
                                    'from'    => $request->routeIs('grp.org.shops.show.crm.appointments.settings') ? 'appointments' : null,
                                ]),
                            ]),
                            'disconnect_route' => [
                                'name'       => 'grp.org.shops.show.settings.google_calendar.disconnect',
                                'parameters' => [$shop->organisation->slug, $shop->slug],
                            ],
                            'sync_route'       => [
                                'name'       => 'grp.org.shops.show.settings.google_calendar.sync',
                                'parameters' => [$shop->organisation->slug, $shop->slug],
                            ],
                            'connected_query'  => 'google_calendar_connected',
                            'texts'            => [
                                'connected'           => __('Every appointment of this shop is written into this calendar, and time already taken in it is not offered to visitors on the website.'),
                                'not_connected'       => __('Sign in with the Google account whose main calendar the shop uses for appointments. Bookings are written into it, and its busy times are kept free of website bookings.'),
                                'not_connected_title' => __('No calendar connected'),
                                'success'             => __('Upcoming appointments are being written into the calendar now, and time already taken in it is no longer offered on the website.'),
                                'success_title'       => __('Google Calendar connected'),
                                'confirm_disconnect'  => __('Disconnect this calendar? New appointments will no longer be written into it, and its busy times will stop blocking bookings.'),
                                'connect'             => __('Connect Google Calendar'),
                                'reconnect'           => __('Reconnect Google Calendar'),
                                'revoked'             => __('Appointments are not written into it until it is reconnected.'),
                                'sync'                => __('Sync now'),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
