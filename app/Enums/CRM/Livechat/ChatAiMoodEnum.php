<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * How the customer sounds in their latest message, as Aura reads it (INI-520). A fixed list so
 * moods can be coloured, counted and compared with the customer's usual one. Listed from the
 * happiest to the most worrying.
 */
enum ChatAiMoodEnum: string
{
    use EnumHelperTrait;

    case FRIENDLY = 'friendly';
    case NEUTRAL = 'neutral';
    case UPSET = 'upset';
    case ANGRY = 'angry';
    case MAY_LEAVE_US = 'may_leave_us';
    case OUR_BIG_MISTAKE = 'our_big_mistake';

    /**
     * What each one means, for the routine. English, because it is read by a model.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'friendly'        => 'warm, polite, thanks us or is easy going',
            'neutral'         => 'matter of fact, no feeling either way',
            'upset'           => 'disappointed or frustrated, but still civil',
            'angry'           => 'hostile, shouting, threatening or insulting',
            'may_leave_us'    => 'says or hints they will stop buying, switch supplier, cancel the account or leave a bad review',
            'our_big_mistake' => 'we clearly failed them badly (lost, wrong or very late order, repeated failures) and they are right to be unhappy',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::FRIENDLY        => __('Friendly'),
            self::NEUTRAL         => __('Neutral'),
            self::UPSET           => __('Upset'),
            self::ANGRY           => __('Angry'),
            self::MAY_LEAVE_US    => __('May leave us'),
            self::OUR_BIG_MISTAKE => __('Our big mistake'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::FRIENDLY                                       => 'green',
            self::NEUTRAL                                        => 'gray',
            self::UPSET                                          => 'amber',
            self::ANGRY, self::MAY_LEAVE_US, self::OUR_BIG_MISTAKE => 'red',
        };
    }
}
