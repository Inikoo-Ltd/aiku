<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOutboxShowcase
{
    use AsObject;

    public function handle(Outbox $outbox): array
    {
        $stats       = [];
        $outboxStats = $outbox->stats;
        foreach (DispatchedEmailStateEnum::cases() as $state) {
            $stats[] = [
                'label' => $state::labels()[$state->value],
                'key'   => 'dispatched_emails_state_'.$state->value,
                'icon'  => $state::stateIcon()[$state->value]['icon'],
                'value' => $outboxStats->{'number_dispatched_emails_state_'.$state->value},
            ];
        }


        $userSubscribers = null;

        if ($outbox->type == OutboxTypeEnum::USER_NOTIFICATION) {
            $userSubscribers = [
                'data' => $outbox->subscribedUsers->map(function ($subscribedUser) {
                    return $subscribedUser->user
                        ? [
                            'user_id'       => $subscribedUser->user->id,
                            'subscriber_id' => $subscribedUser->id,
                            'username'      => $subscribedUser->user->username,
                            'contact_name'  => $subscribedUser->user->contact_name,
                            'email'         => $subscribedUser->user->email,
                        ]
                        : [
                            'subscriber_id' => $subscribedUser->id,
                            'email'         => $subscribedUser->external_email,
                        ];
                })
            ];
        }


        return [
            'outbox'          => [
                'id'      => $outbox->id,
                'slug'    => $outbox->slug,
                'subject' => $outbox->emailOngoingRun?->email?->subject,
                'sender'  => $outbox->shop?->senderEmail?->email_address
            ],
            'state'           => $outbox->state,
            'builder'         => $outbox->builder,
            'compiled_layout' => ($outbox->builder->value == "blade")
                ? Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template')
                : $outbox->emailOngoingRun?->email?->liveSnapshot?->compiled_layout,

            'dashboard_stats'      => [
                'widgets' => [
                    'column_count' => 1,
                    'components'   => array_filter([
                        [
                            'type' => 'circle_display',

                            'data' => $stats
                        ],
                    ])
                ]
            ],
            'outbox_subscribe'     => $userSubscribers,
            'has_user_subscribers' => $outbox->type == OutboxTypeEnum::USER_NOTIFICATION,
        ];
    }
}
