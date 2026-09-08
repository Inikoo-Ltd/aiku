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
        $outboxStats = $outbox->stats;
        $stateCount  = fn (string $state): int => (int) $outboxStats->{'number_dispatched_emails_state_'.$state};

        $clicked   = $stateCount(DispatchedEmailStateEnum::CLICKED->value);
        $opened    = $stateCount(DispatchedEmailStateEnum::OPENED->value) + $clicked;
        $delivered = $stateCount(DispatchedEmailStateEnum::DELIVERED->value)
            + $opened
            + $stateCount(DispatchedEmailStateEnum::SPAM->value)
            + $stateCount(DispatchedEmailStateEnum::UNSUBSCRIBED->value);
        $sent = $delivered
            + $stateCount(DispatchedEmailStateEnum::SENT->value)
            + $stateCount(DispatchedEmailStateEnum::SENT_TO_PROVIDER->value)
            + $stateCount(DispatchedEmailStateEnum::DELAY->value)
            + $stateCount(DispatchedEmailStateEnum::SOFT_BOUNCE->value)
            + $stateCount(DispatchedEmailStateEnum::HARD_BOUNCE->value);

        $percentage = fn (int $part, int $total): ?float => $total > 0 ? round($part / $total * 100, 1) : null;

        $stats = [
            'funnel' => [
                [
                    'key'   => 'sent',
                    'label' => __('Sent'),
                    'icon'  => 'fal fa-paper-plane',
                    'value' => $sent,
                ],
                [
                    'key'        => 'delivered',
                    'label'      => __('Delivered'),
                    'icon'       => 'fal fa-inbox-in',
                    'value'      => $delivered,
                    'percentage' => $percentage($delivered, $sent),
                ],
                [
                    'key'        => 'opened',
                    'label'      => __('Opened'),
                    'icon'       => 'fal fa-envelope-open',
                    'value'      => $opened,
                    'percentage' => $percentage($opened, $delivered),
                ],
                [
                    'key'        => 'clicked',
                    'label'      => __('Clicked'),
                    'icon'       => 'fal fa-mouse-pointer',
                    'value'      => $clicked,
                    'percentage' => $percentage($clicked, $delivered),
                ],
            ],
            'issues' => collect([
                DispatchedEmailStateEnum::READY,
                DispatchedEmailStateEnum::ERROR,
                DispatchedEmailStateEnum::REJECTED_BY_PROVIDER,
                DispatchedEmailStateEnum::DELAY,
                DispatchedEmailStateEnum::HARD_BOUNCE,
                DispatchedEmailStateEnum::SOFT_BOUNCE,
                DispatchedEmailStateEnum::SPAM,
                DispatchedEmailStateEnum::UNSUBSCRIBED,
            ])->map(fn (DispatchedEmailStateEnum $state) => [
                'key'   => $state->value,
                'label' => DispatchedEmailStateEnum::labels()[$state->value],
                'icon'  => DispatchedEmailStateEnum::stateIcon()[$state->value]['icon'],
                'value' => $stateCount($state->value),
            ])->filter(fn (array $issue) => $issue['value'] > 0)->values()->all(),
        ];


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
                'sender'  => $outbox->shop?->senderEmail?->email_address ?? $outbox->shop?->email
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
