<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\AI\AskJev;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether a text written for one customer would tell other customers something about that
 * customer (INI-389, hard rule: Merlin never shows a customer another customer's data). Every
 * text that becomes shop knowledge for all customers passes here first. Fails closed: an email
 * address outside our own domains, or no reading from Jev, counts as about one customer. Phone
 * numbers are left to Jev: a digit pattern also matches our IBANs and barcodes.
 */
class IsAboutOneCustomer
{
    use AsAction;

    public const float MAX_PROBABILITY = 0.3;

    public function handle(string $title, string $body): bool
    {
        $text = trim($title."\n".$body);

        if ($this->hasOutsideContact($text)) {
            return true;
        }

        $probability = AskJev::make()->noul(
            ['text' => $text],
            'Staff of a wholesaler wrote this while answering one customer. It may be kept as general knowledge shown to every other customer of the shop. Does it hold anything about one specific customer?',
            'It names or identifies a specific customer or person, or gives their contact details, address, an order, basket, invoice or payment of theirs, an amount refunded, credited, owed or charged to them, or a deal, discount, exception or promise made only to them.',
            'It is general: how the shop, its products, prices, delivery, payment or policies work for any customer, with no detail about any one customer.'
        );

        return $probability === null || $probability > self::MAX_PROBABILITY;
    }

    private function hasOutsideContact(string $text): bool
    {
        preg_match_all('/[\p{L}\d._%+-]+@([\p{L}\d.-]+\.\p{L}{2,})/u', $text, $emails);
        $ourDomains = '/(^|\.)(ancientwisdom|aw-dropship|awgifts|awartisan|aw-aromatics|aw-fulfilment)\./i';

        return collect($emails[1])->contains(fn (string $domain) => !preg_match($ourDomains, $domain));
    }
}
