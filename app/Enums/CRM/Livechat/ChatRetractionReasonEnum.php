<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * Why a message was taken back. The customer is shown this line, so it is a fixed list
 * rather than free writing: it can be translated properly into their language, and it
 * reads the same whoever sends it.
 */
enum ChatRetractionReasonEnum: string
{
    use EnumHelperTrait;

    /**
     * Written to the customer, because the agent has nothing further to say to them: the
     * message belonged in somebody else's conversation.
     */
    case WRONG_CONVERSATION = 'wrong_conversation';

    /**
     * The agent stays in this conversation and puts it right in their own words, so the
     * customer is told only that something was withdrawn, and the apology is a real one.
     */
    case EXPLAINING = 'explaining';

    public static function labels(): array
    {
        return [
            'wrong_conversation' => __('Sorry, that message was meant for another conversation'),
            'explaining'         => __('This message was removed'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
