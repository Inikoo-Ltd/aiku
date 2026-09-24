<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * Everything the chat does on its own: the fixed messages it sends a customer, and the noise
 * check that decides whether a stranger reaches the queue. What the AI tab lists, so staff can
 * see every one of them.
 */
enum ChatAutomationKindEnum: string
{
    use EnumHelperTrait;

    case OUT_OF_HOURS = 'out_of_hours';
    case CLAIM_DETAILS = 'claim_details';
    case GREETING = 'greeting';
    case ASKED_IF_CUSTOMER = 'asked_if_customer';
    case NOISE_CHECK = 'noise_check';
    case AI_DRAFT = 'ai_draft';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_HOURS      => __('Closed-now reply'),
            self::CLAIM_DETAILS     => __('Asked for claim details'),
            self::GREETING          => __('Greeting'),
            self::ASKED_IF_CUSTOMER => __('Asked if customer'),
            self::NOISE_CHECK       => __('Noise check'),
            self::AI_DRAFT          => __('AI draft reply'),
        };
    }

    public function sendsMessage(): bool
    {
        return !in_array($this, [self::NOISE_CHECK, self::AI_DRAFT], true);
    }
}
