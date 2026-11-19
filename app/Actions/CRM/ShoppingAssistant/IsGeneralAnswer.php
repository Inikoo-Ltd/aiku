<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\AI\AskJev;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether a guru's answer is worth keeping as shop knowledge for every customer: a real question,
 * answered, in a way any other customer asking the same could use. Gurus are trusted, so this
 * replaces the second guru's yes (INI-402); it keeps out answers to another question, replies
 * that are not answers ("we will check"), one customer's case and answers too vague to reuse.
 * Fails closed: no reading from Jev keeps nothing.
 */
class IsGeneralAnswer
{
    use AsAction;

    public const float MIN_PROBABILITY = 0.7;

    public function handle(string $question, string $answer): bool
    {
        $probability = AskJev::make()->noul(
            ['question' => $question, 'answer' => $answer],
            'Staff of a wholesaler answered a customer; the question is the customer\'s question or the topic of a rule learned from many staff replies. Should the answer be kept as shop knowledge, useful to every customer who asks the same?',
            'The question is a real question or topic about the shop, its products, prices, delivery, payment or policies, and the answer answers that same question in full, in a way that holds for any customer.',
            'The question is not a real question or topic, or the answer is about something else, only promises to check or come back, depends on one customer\'s order, account or situation, is a decision staff make case by case (a refund, credit, replacement, compensation or exception for a claim), or is too short or vague to help another customer.'
        );

        return $probability !== null && $probability >= self::MIN_PROBABILITY;
    }
}
