<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What the customer wrote next after a reply that came from a draft. Staff sending a draft says
 * they thought it fine; the customer being confused or unhappy says it was not, whatever staff
 * thought, and counts against that kind of draft ever going out alone.
 */
enum ChatAiReactionEnum: string
{
    use EnumHelperTrait;

    case THANKS = 'thanks';
    case FOLLOW_UP = 'follow_up';
    case REPLY = 'reply';
    case CONFUSED = 'confused';
    case COMPLAINT = 'complaint';

    /**
     * What each one means, for Jev. English, because it is read by a model.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'thanks'    => 'only thanks, a goodbye, or confirms it is sorted',
            'follow_up' => 'asks something new or something more, and our reply was fine',
            'reply'     => 'answers what we asked, gives the details we needed, or says they did what we said',
            'confused'  => 'did not understand our reply, or it did not answer what they asked, or they ask the same thing again',
            'complaint' => 'unhappy with our reply, says it was wrong, or that what we said did not work',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::THANKS    => __('Thanked us'),
            self::FOLLOW_UP => __('Asked something more'),
            self::REPLY     => __('Answered us'),
            self::CONFUSED  => __('Confused by the reply'),
            self::COMPLAINT => __('Unhappy with the reply'),
        };
    }

    public function isHarm(): bool
    {
        return in_array($this, [self::CONFUSED, self::COMPLAINT], true);
    }
}
