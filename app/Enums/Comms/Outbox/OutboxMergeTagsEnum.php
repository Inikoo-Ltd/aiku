<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Dec 2024 22:42:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Outbox;

use App\Enums\EnumHelperTrait;

enum OutboxMergeTagsEnum: string
{
    use EnumHelperTrait;

    case USERNAME = 'Username';
    case CUSTOMER_SHOP = 'Customer Shop';
    case CUSTOMER_NAME = 'Customer Name';
    case CUSTOMER_URL = 'Customer Url';
    case CUSTOMER_EMAIL = 'Customer Email';
    case CUSTOMER_REGISTER_DATE = 'Customer Register Date';
    case INVOICE_URL = 'Invoice_Url';
    case RESET_PASSWORD_URL = 'Reset_Password_URL';
    case UNSUBSCRIBE = 'Unsubscribe';
    case REJECTED_NOTES = 'Rejected Notes';

    public static function tags(): array
    {
        return [
            [
                'name'  => __('Username'),
                'value' => '[Username]'
            ],
            [
                'name'  => __('Customer Name'),
                'value' => '[Customer Name]'
            ],
            [
                'name'  => __('Invoice URL'),
                'value' => '[Invoice_Url]'
            ],
            [
                'name'  => __('Reset Password URL'),
                'value' => '[Reset_Password_URL]'
            ],
            [
                'name'  => __('Unsubscribe'),
                'value' => '[Unsubscribe]'
            ],
            [
                'name'  => __('Customer Shop'),
                'value' => '[Customer Shop]'
            ],
            [
                'name'  => __('Customer Url'),
                'value' => '[Customer Url]'
            ],
            [
                'name'  => __('Customer Email'),
                'value' => '[Customer Email]'
            ],
            [
                'name'  => __('Customer Register Date'),
                'value' => '[Customer Register Date]'
            ],
            [
                'name'  => __('Rejected Notes'),
                'value' => '[Rejected Notes]'
            ],
        ];
    }
}
