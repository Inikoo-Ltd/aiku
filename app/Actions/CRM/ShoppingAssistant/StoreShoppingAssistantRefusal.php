<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A storefront question the assistant refused, because of the rate limit, because the website's
 * monthly budget is spent or because a never-invoiced customer used their own allowance. Written after the response, only for a signed-in customer of the
 * website's shop and only while the website's assistant switch is on, at most one row per
 * customer, reason and minute, in one statement.
 */
class StoreShoppingAssistantRefusal
{
    use AsAction;

    public const string RATE_LIMIT = 'rate_limit';

    public const string BUDGET = 'budget';

    public const string CUSTOMER_CAP = 'customer_cap';

    public const int MAX_QUESTION = 1000;

    public static function afterResponse(mixed $website, ?int $customerId, string $reason, mixed $question): void
    {
        if (!$website instanceof Website || !$customerId || !data_get($website->settings, 'shopping_assistant.enabled')) {
            return;
        }

        $question = is_string($question) ? mb_substr(trim($question), 0, self::MAX_QUESTION) : null;

        logAfterResponse(fn () => static::run($website, $customerId, $reason, $question));
    }

    public function handle(Website $website, int $customerId, string $reason, ?string $question): void
    {
        $now = now();

        DB::insert(
            <<<'SQL'
            insert into shopping_assistant_refusals (group_id, organisation_id, shop_id, website_id, customer_id, reason, question, created_at)
            select ?, ?, ?, ?, customers.id, ?, ?, ?
            from customers
            where customers.id = ? and customers.shop_id = ?
                and not exists (
                    select 1 from shopping_assistant_refusals refusals
                    where refusals.customer_id = customers.id and refusals.reason = ? and refusals.created_at >= date_trunc('minute', ?::timestamptz)
                )
            SQL,
            [
                $website->group_id, $website->organisation_id, $website->shop_id, $website->id, $reason, $question, $now,
                $customerId, $website->shop_id,
                $reason, $now,
            ]
        );
    }
}
