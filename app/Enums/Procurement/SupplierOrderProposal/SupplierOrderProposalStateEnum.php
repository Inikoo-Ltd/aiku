<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Enums\Procurement\SupplierOrderProposal;

use App\Enums\EnumHelperTrait;

enum SupplierOrderProposalStateEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';

    public static function labels(): array
    {
        return [
            'pending'  => __('Waiting for the buyer'),
            'accepted' => __('Accepted'),
            'rejected' => __('Not ordered'),
        ];
    }
}
