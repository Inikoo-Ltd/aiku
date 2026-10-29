<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What the AI customer service sweep did with a customer message. One per message: when it is
 * recorded twice, the case listed first here is kept, so a draft is never hidden by a later skip.
 */
enum ChatAiOutcomeEnum: string
{
    use EnumHelperTrait;

    case DRAFT = 'draft';
    case REVIEW_FAILED_NOTE = 'review_failed_note';
    case NOTE = 'note';
    case ANSWERED_BY_STAFF = 'answered_by_staff';
    case SUGGESTION_ALREADY_WAITING = 'suggestion_already_waiting';
    case HELD = 'held';
    case OPENED = 'opened';
    case WHATSAPP_WINDOW_CLOSED = 'whatsapp_window_closed';
    case ANSWERED_ELSEWHERE = 'answered_elsewhere';
    case SPAM = 'spam';
    case THANKS = 'thanks';
    case NOTICE = 'notice';

    /**
     * @return array<int, string>
     */
    public static function skips(): array
    {
        return [
            self::ANSWERED_BY_STAFF->value,
            self::SUGGESTION_ALREADY_WAITING->value,
            self::HELD->value,
            self::OPENED->value,
            self::WHATSAPP_WINDOW_CLOSED->value,
            self::ANSWERED_ELSEWHERE->value,
            self::SPAM->value,
            self::THANKS->value,
            self::NOTICE->value,
        ];
    }

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT                      => __('Suggestion'),
            self::REVIEW_FAILED_NOTE         => __('Note, suggestion failed review'),
            self::NOTE                       => __('Note for staff, no suggestion'),
            self::ANSWERED_BY_STAFF          => __('Already answered by staff'),
            self::SUGGESTION_ALREADY_WAITING => __('Suggestion already waiting'),
            self::HELD                       => __('Held by a colleague'),
            self::OPENED                     => __('Opened by a colleague, no suggestion'),
            self::WHATSAPP_WINDOW_CLOSED     => __('WhatsApp 24-hour window closed: only an approved template can be sent'),
            self::ANSWERED_ELSEWHERE         => __('Answered in another chat'),
            self::SPAM                       => __('Spam or marketing'),
            self::THANKS                     => __('Thank-you only'),
            self::NOTICE                     => __('System notice'),
        };
    }
}
