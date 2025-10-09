<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Mar 2023 23:45:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Payment;

use App\Enums\EnumHelperTrait;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum PaymentStateEnum: string
{
    use EnumHelperTrait;
    case IN_PROCESS = 'in_process';
    case APPROVING  = 'approving';
    case COMPLETED  = 'completed';
    case CANCELLED  = 'cancelled';
    case ERROR      = 'error';
    case DECLINED   = 'declined';

    public static function labels(): array
    {
        return [
            'in_process'    => __('In Process'),
            'approving'     => __('Approving'),
            'completed'     => __('Completed'),
            'cancelled'     => __('Cancelled'),
            'error'         => __('Error'),
            'declined'      => __('Declined'),
        ];
    }

    public static function count(Group|Organisation|PaymentAccount|Shop|Fulfilment|OrgPaymentServiceProvider|Invoice|Order|Customer $parent): array
    {
        if ($parent instanceof Group || $parent instanceof Organisation || $parent instanceof Shop) {
            $stats = $parent->accountingStats;
        } elseif ($parent instanceof Fulfilment) {
            $stats = $parent->shop->accountingStats;
        } elseif ($parent instanceof OrgPaymentServiceProvider || $parent instanceof PaymentAccount || $parent instanceof Customer) {
            $stats = $parent->stats;
        }
        return [
            'in_process'                => $stats->number_payments_state_in_process,
            'approving'                 => $stats->number_payments_state_approving,
            'completed'                 => $stats->number_payments_state_completed,
            'cancelled'                 => $stats->number_payments_state_cancelled,
            'error'                     => $stats->number_payments_state_error,
            'declined'                  => $stats->number_payments_state_declined,
        ];
    }
}
