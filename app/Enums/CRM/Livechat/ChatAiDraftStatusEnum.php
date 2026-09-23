<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What became of a reply the AI drafted from the facts. Counted to decide whether drafts can
 * ever be sent without a person: used as written, used after a change, thrown away, or left
 * behind because the conversation moved on before anybody looked.
 */
enum ChatAiDraftStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case USED = 'used';
    case EDITED = 'edited';
    case DISCARDED = 'discarded';
    case SUPERSEDED = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING    => __('Waiting for staff'),
            self::USED       => __('Sent as written'),
            self::EDITED     => __('Sent after changes'),
            self::DISCARDED  => __('Discarded'),
            self::SUPERSEDED => __('Not used'),
        };
    }
}
