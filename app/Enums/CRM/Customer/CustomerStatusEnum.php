<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:22:44 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;

enum CustomerStatusEnum: string
{
    use EnumHelperTrait;

    case PRE_REGISTRATION = 'pre_registration';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED         = 'approved';
    case REJECTED         = 'rejected';
    case BANNED           = 'banned';

    public static function labels(): array
    {
        return [
            'pending_approval' => __('Pending Approval'),
            'approved'         => __('Approved'),
            'rejected'         => __('Rejected'),
            'banned'           => __('Banned'),
            'pre_registration' => __('Pre Registration'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'pending_approval' => [
                'tooltip' => __('Pending Approval'),
                'icon'    => 'fal fa-hourglass-half',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow'
            ],
            'approved'         => [
                'tooltip' => __('Approved'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-green-500',
                'color'   => 'green'
            ],
            'rejected'         => [
                'tooltip' => __('Rejected'),
                'icon'    => 'fas fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'red',
            ],
            'banned'           => [
                'tooltip' => __('Banned'),
                'icon'    => 'fas fa-ban',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
            ],
            'pre_registration' => [
                'tooltip' => __('Pre Registration'),
                'icon'    => 'fal fa-user-clock',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
            ],
        ];
    }

    public static function count(Shop $parent): array
    {
        $stats = $parent->crmStats;

        return [
            'pending_approval' => $stats->number_customers_status_pending_approval,
            'approved'         => $stats->number_customers_status_approved,
            'rejected'         => $stats->number_customers_status_rejected,
            'banned'           => $stats->number_customers_status_banned,
            'pre_registration' => $stats->number_customers_status_pre_registration,
        ];
    }
}
