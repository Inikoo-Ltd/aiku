<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairIsInWebsite
{
    use AsAction;

    /**
     * Bulk SQL backfill of is_in_website (live webpage + sellable rule for products).
     * Bypasses Eloquent on purpose: it does not touch Scout, run a search reindex after.
     */
    public function handle(Command $command): void
    {
        $live = WebpageStateEnum::LIVE->value;

        $productsUpdated = DB::update("
            update products set is_in_website = computed.value
            from (
                select p.id, (
                    w.id is not null
                    and (p.is_variant_leader or (not p.is_minion_variant and p.is_for_sale))
                ) as value
                from products p
                left join webpages w on w.id = p.webpage_id and w.state = ? and w.deleted_at is null
            ) computed
            where products.id = computed.id and products.is_in_website is distinct from computed.value
        ", [$live]);
        $command->info("products updated: {$productsUpdated}");

        foreach (['product_categories', 'collections'] as $tableName) {
            $updated = DB::update("
                update {$tableName} set is_in_website = computed.value
                from (
                    select t.id, (w.id is not null) as value
                    from {$tableName} t
                    left join webpages w on w.id = t.webpage_id and w.state = ? and w.deleted_at is null
                ) computed
                where {$tableName}.id = computed.id and {$tableName}.is_in_website is distinct from computed.value
            ", [$live]);
            $command->info("{$tableName} updated: {$updated}");
        }
    }

    public string $commandSignature = 'repair:is_in_website';

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
