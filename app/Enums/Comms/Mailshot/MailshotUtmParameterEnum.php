<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum MailshotUtmParameterEnum: string
{
    use EnumHelperTrait;

    case SOURCE   = 'utm_source';
    case MEDIUM   = 'utm_medium';
    case CAMPAIGN = 'utm_campaign';
    case ID       = 'utm_id';
    case TERM     = 'utm_term';
    case CONTENT  = 'utm_content';

    public function label(): string
    {
        return match ($this) {
            self::SOURCE   => __('Source'),
            self::MEDIUM   => __('Medium'),
            self::CAMPAIGN => __('Campaign'),
            self::ID       => __('Campaign ID'),
            self::TERM     => __('Term'),
            self::CONTENT  => __('Content'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::SOURCE   => __('Which list or channel the click came from'),
            self::MEDIUM   => __('How it was delivered, email for a mailshot'),
            self::CAMPAIGN => __('Name that groups every link of this mailshot'),
            self::ID       => __('Your own reference for this send'),
            self::TERM     => __('Keyword or audience segment, optional'),
            self::CONTENT  => __('Which element was clicked, to compare placements'),
        };
    }
}
