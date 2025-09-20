<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Nov 2024 12:15:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Outbox;

use App\Enums\EnumHelperTrait;

enum OutboxTypeEnum: string
{
    use EnumHelperTrait;

    case NEWSLETTER = 'newsletter';
    case MARKETING = 'marketing';
    case MARKETING_NOTIFICATION = 'marketing_notification'; // halfway between marketing and transactional
    case CUSTOMER_NOTIFICATION = 'customer_notification'; // e.g. forgot email, welcome email, etc
    case COLD_EMAIL = 'cold_emails'; // send to prospects
    case USER_NOTIFICATION = 'user_notification'; // internal notifications
    case PUSH = 'push';
    case TEST = 'test';

    public function label(): string
    {
        return match ($this) {
            OutboxTypeEnum::NEWSLETTER => 'Newsletters',
            OutboxTypeEnum::MARKETING => 'Marketing',
            OutboxTypeEnum::MARKETING_NOTIFICATION => 'Marketing notifications',
            OutboxTypeEnum::CUSTOMER_NOTIFICATION => 'Customer notifications',
            OutboxTypeEnum::COLD_EMAIL => 'Cold emails',
            OutboxTypeEnum::USER_NOTIFICATION => 'User notifications',
            OutboxTypeEnum::TEST => 'Tests',
            OutboxTypeEnum::PUSH => 'Push campaign',
        };
    }


    public function icon(): array
    {
        return [
            OutboxTypeEnum::NEWSLETTER->value             => [
                'tooltip' => __(OutboxTypeEnum::NEWSLETTER->value),
                'icon'    => 'fal fa-newspaper',
            ],
            OutboxTypeEnum::MARKETING->value              => [
                'tooltip' => __(OutboxTypeEnum::MARKETING->value),
                'icon'    => 'fal fa-bullhorn',
            ],
            OutboxTypeEnum::MARKETING_NOTIFICATION->value => [
                'tooltip' => __(OutboxTypeEnum::MARKETING_NOTIFICATION->value),
                'icon'    => 'fal fa-radio',
            ],
            OutboxTypeEnum::CUSTOMER_NOTIFICATION->value  => [
                'tooltip' => __(OutboxTypeEnum::CUSTOMER_NOTIFICATION->value),
                'icon'    => 'fal fa-sort-alt',
                'class'   => 'rotate-90'
            ],
            OutboxTypeEnum::COLD_EMAIL->value             => [
                'tooltip' => __(OutboxTypeEnum::COLD_EMAIL->value),
                'icon'    => 'fal fa-phone-volume',
            ],
            OutboxTypeEnum::USER_NOTIFICATION->value      => [
                'tooltip' => __(OutboxTypeEnum::USER_NOTIFICATION->value),
                'icon'    => 'fal fa-bell',
            ],
            OutboxTypeEnum::TEST->value                   => [
                'tooltip' => __(OutboxTypeEnum::TEST->value),
                'icon'    => 'fal fa-vial',
            ],
            OutboxTypeEnum::PUSH->value                   => [
                'tooltip' => __(OutboxTypeEnum::PUSH->value),
                'icon'    => 'fal fa-project-diagram',
            ]
        ];
    }
}
