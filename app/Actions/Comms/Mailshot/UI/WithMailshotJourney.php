<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Aug 2026 15:30:00 Central European Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\Comms\Mailshot\GetMailshotRecipientsQueryBuilder;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Number;

trait WithMailshotJourney
{
    /**
     * @return array<int, array{label: string, current: bool, route: array{name: string, parameters: array<int, string>}}>
     */
    public function getMailshotJourney(Mailshot $mailshot, string $current): array
    {
        if (!in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY])) {
            return [];
        }

        $isMarketing = $mailshot->type === MailshotTypeEnum::MARKETING;
        $prefix      = $isMarketing ? 'mailshots' : 'newsletters';
        $parameters  = [
            $mailshot->organisation->slug,
            $mailshot->shop->slug,
            $mailshot->slug
        ];

        $email      = $mailshot->email;
        $snapshot   = $email?->unpublishedSnapshot;
        $isComposed = (bool) $email?->liveSnapshot?->compiled_layout
            || ($snapshot && !$snapshot->updated_at->eq($snapshot->created_at));

        $steps = [];
        if ($isMarketing) {
            $estimatedRecipients = GetMailshotRecipientsQueryBuilder::make()->handle($mailshot)?->count('customers.id') ?? 0;

            $steps[] = [
                'key'   => 'recipients',
                'done'  => (bool) $mailshot->recipients_recipe,
                'label' => __('Recipients').' ('.Number::abbreviate($estimatedRecipients).')',
                'route' => [
                    'name'       => 'grp.org.shops.show.marketing.mailshots.recipients',
                    'parameters' => $parameters
                ]
            ];
        }
        $steps[] = [
            'key'   => 'compose',
            'done'  => $isComposed,
            'label' => __('Compose'),
            'route' => [
                'name'       => "grp.org.shops.show.marketing.$prefix.workshop",
                'parameters' => $parameters
            ]
        ];
        $steps[] = [
            'key'      => 'review',
            'done'     => false,
            'disabled' => !$isComposed,
            'label'    => __('Review & send'),
            'route' => [
                'name'       => "grp.org.shops.show.marketing.$prefix.show",
                'parameters' => $parameters
            ]
        ];

        return array_map(
            fn (array $step) => array_merge($step, [
                'current' => $step['key'] === $current,
                'done'    => $step['done'] && $step['key'] !== $current
            ]),
            $steps
        );
    }
}
