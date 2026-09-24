<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What a stranger's first message turned out to be. The reasons an agent can put a conversation
 * aside for, plus spam, plus the only answer that leaves it in the queue. Anything the model
 * says that is not on this list is read as genuine.
 */
enum ChatNoiseVerdictEnum: string
{
    use EnumHelperTrait;

    case GENUINE = 'genuine';
    case SPAM = 'spam';
    case OUT_OF_OFFICE = 'out_of_office';
    case MARKETING = 'marketing';
    case SUPPLIER_CIRCULAR = 'supplier_circular';
    case AUTOMATED_NOTIFICATION = 'automated_notification';
    case NOT_FOR_US = 'not_for_us';

    /**
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'genuine'                => 'a person who wants something from us as a seller: a customer or somebody who may become one, asking about products, prices, an order, delivery, an account. Also anybody we are already dealing with, such as a courier or a supplier answering us. A voicemail notice is genuine too: somebody rang us and is waiting to be called back. When in doubt, this one.',
            'spam'                   => 'unsolicited selling to us or fishing: somebody offering their products, manufacturing, SEO, marketing, collaborations, loans, legal scares, or small talk with no request.',
            'out_of_office'          => 'an automatic reply saying the person is away.',
            'marketing'              => 'a newsletter or a promotion sent to a list.',
            'supplier_circular'      => 'a stock list or an offer a supplier sends to all its customers at once.',
            'automated_notification' => 'a machine reporting something: an alert, a receipt, a report, a delivery failure. Not a voicemail notice.',
            'not_for_us'             => 'meant for somebody else, or nothing to do with us.',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::GENUINE => __('Genuine'),
            self::SPAM    => __('Spam'),
            default       => ChatIgnoreReasonEnum::from($this->value)->label(),
        };
    }

    public function isNoise(): bool
    {
        return $this !== self::GENUINE;
    }

    public function ignoreReason(): ?ChatIgnoreReasonEnum
    {
        return ChatIgnoreReasonEnum::tryFrom($this->value);
    }
}
