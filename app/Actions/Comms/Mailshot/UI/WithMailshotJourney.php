<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Aug 2026 15:30:00 Central European Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;

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

        $steps = [];
        if ($isMarketing) {
            $steps[] = [
                'key'   => 'recipients',
                'label' => __('Recipients'),
                'route' => [
                    'name'       => 'grp.org.shops.show.marketing.mailshots.recipients',
                    'parameters' => $parameters
                ]
            ];
        }
        $steps[] = [
            'key'   => 'compose',
            'label' => __('Compose'),
            'route' => [
                'name'       => "grp.org.shops.show.marketing.$prefix.workshop",
                'parameters' => $parameters
            ]
        ];
        $steps[] = [
            'key'   => 'review',
            'label' => __('Review & send'),
            'route' => [
                'name'       => "grp.org.shops.show.marketing.$prefix.show",
                'parameters' => $parameters
            ]
        ];

        $currentIndex = array_search($current, array_column($steps, 'key'));

        return array_map(
            fn (array $step, int $index) => $step + [
                'current' => $index === $currentIndex,
                'done'    => $currentIndex !== false && $index < $currentIndex
            ],
            $steps,
            array_keys($steps)
        );
    }
}
