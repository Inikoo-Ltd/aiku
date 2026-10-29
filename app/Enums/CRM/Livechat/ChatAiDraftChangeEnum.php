<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * How much staff changed a draft before sending, finer than its status. Status counts a draft
 * whose [[gaps]] staff filled with real facts as sent as written; here it is gaps_filled, since
 * only a draft sent as it is, or with a greeting or a name added, could ever go out alone.
 */
enum ChatAiDraftChangeEnum: string
{
    use EnumHelperTrait;

    case AS_IS = 'as_is';
    case COSMETIC = 'cosmetic';
    case GAPS_FILLED = 'gaps_filled';
    case SMALL = 'small';
    case BIG = 'big';
    case REWRITTEN = 'rewritten';
    case OWN_REPLY = 'own_reply';
    case DISCARDED = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::AS_IS       => __('Sent as it was'),
            self::COSMETIC    => __('Greeting or name added'),
            self::GAPS_FILLED => __('Gaps filled in'),
            self::SMALL       => __('Small changes'),
            self::BIG         => __('Big changes'),
            self::REWRITTEN   => __('Rewritten'),
            self::OWN_REPLY   => __('Own reply instead'),
            self::DISCARDED   => __('Discarded'),
        };
    }

    public function couldGoAlone(): bool
    {
        return in_array($this, [self::AS_IS, self::COSMETIC], true);
    }
}
