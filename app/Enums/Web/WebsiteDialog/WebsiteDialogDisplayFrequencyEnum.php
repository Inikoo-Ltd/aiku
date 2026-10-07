<?php

namespace App\Enums\Web\WebsiteDialog;

use App\Enums\EnumHelperTrait;

enum WebsiteDialogDisplayFrequencyEnum: string
{
    use EnumHelperTrait;

    case EVERY_PAGE_VIEW = 'every_page_view';
    case ONCE_PER_SESSION = 'once_per_session';
    case ONCE = 'once';
    case ONCE_PER_CUSTOMER = 'once_per_customer';

    public static function labels(): array
    {
        return [
            'every_page_view'  => __('Every page view'),
            'once_per_session' => __('Once per visit (browser session)'),
            'once'             => __('Only once on this browser, until it is published again'),
            'once_per_customer' => __('Only once per customer account, until it is published again'),
        ];
    }
}
