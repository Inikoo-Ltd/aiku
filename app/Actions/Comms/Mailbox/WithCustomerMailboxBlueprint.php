<?php

namespace App\Actions\Comms\Mailbox;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerMailboxBlueprint
{
    /**
     * @return array{label: string, icon: string, fields: array<string, mixed>}
     */
    public function customerMailboxBlueprint(Shop $shop, ActionRequest $request): array
    {
        return [
            'label'  => __('Customer mailbox'),
            'icon'   => 'fa-light fa-envelope',
            'fields' => [
                'mailbox'             => [
                    'type'         => 'mailbox_connect',
                    'noTitle'      => true,
                    'noSaveButton' => true,
                    'value'        => [
                        'connected'        => filled(Arr::get($shop->settings, 'gmail.email')),
                        'email'            => Arr::get($shop->settings, 'gmail.email'),
                        'connected_at'     => Arr::get($shop->settings, 'gmail.connected_at'),
                        'revoked_at'       => Arr::get($shop->settings, 'gmail.revoked_at'),
                        'connect_url'      => route('grp.org.shops.show.settings.mailbox.connect', [
                            $shop->organisation->slug,
                            $shop->slug,
                            ...array_filter([
                                'section' => $request->query('section'),
                                'from'    => $request->routeIs('grp.org.shops.show.crm.appointments.settings') ? 'appointments' : null,
                            ]),
                        ]),
                        'disconnect_route' => [
                            'name'       => 'grp.org.shops.show.settings.mailbox.disconnect',
                            'parameters' => [$shop->organisation->slug, $shop->slug],
                        ],
                        'inbox_url'        => route('grp.org.shops.show.chat.inbox', [$shop->organisation->slug, $shop->slug]),
                    ],
                ],
                'mailbox_sender_name' => [
                    'type'        => 'input',
                    'label'       => __('Sender name'),
                    'placeholder' => $shop->name,
                    'value'       => Arr::get($shop->settings, 'gmail.sender_name', ''),
                ],
            ],
        ];
    }
}
