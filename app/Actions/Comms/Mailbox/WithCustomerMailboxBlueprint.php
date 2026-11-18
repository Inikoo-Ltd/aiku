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
            'label'  => __('Gmail inbox'),
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
                                'from'    => match (true) {
                                    $request->routeIs('grp.org.shops.show.crm.appointments.settings') => 'appointments',
                                    $request->routeIs('grp.org.fulfilments.show.settings.edit')       => 'fulfilment',
                                    default                                                            => null,
                                },
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
                'chat_email_split_new_threads' => [
                    'type'        => 'toggle',
                    'label'       => __('Each new email is its own conversation'),
                    'information' => __('Off: a new email from a customer who already has an open conversation joins that conversation. On: it starts a new conversation, and only replies stay in the conversation they answer.'),
                    'value'       => (bool) Arr::get($shop->settings, 'chat.email_split_new_threads', false),
                ],
                'chat_unclaimed_email_hours' => [
                    'type'        => 'input_number',
                    'bind'        => ['step' => '0.5', 'maxFractionDigits' => 1, 'min' => 0, 'max' => 168],
                    'label'       => __('Email unclaimed after (hours)'),
                    'information' => __('How long an email conversation may sit with nobody holding it before it joins the unclaimed queue everybody sees. Leave empty or zero to follow the group default.'),
                    'value'       => ($seconds = Arr::get($shop->settings, 'chat.unclaimed_after_seconds.email')) ? round($seconds / 3600, 1) : '',
                ],
                'gmail_showroom_senders' => [
                    'type'        => 'tags',
                    'label'       => __('Showroom booking senders'),
                    'placeholder' => '@calendly.com',
                    'information' => __('Emails from these addresses, or from a whole domain written as @domain, are filed unread under the Gmail "aiku/showroom" label and never become a chat, even if the sender was once marked as spam. Press Enter to add each one.'),
                    'value'       => array_keys(array_filter(
                        Arr::get($shop->settings, 'gmail.labeled_senders') ?? [],
                        fn ($label) => $label === 'aiku/showroom'
                    )),
                ],
            ],
        ];
    }
}
