<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateOrgStocksWithoutProducts;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductsLinkedToDiscontinuedOrgStock
{
    use AsAction;

    public string $commandSignature = 'products:repair-discontinued-org-stock-links
        {--dry-run : List the repointings without writing}
        {--organisation= : Restrict to one organisation slug}';

    /**
     * Active products whose picking SKU is a discontinued org stock while an active
     * org stock exists on the very same stock in the same organisation.
     *
     * Deliberately limited to same-stock swaps: the underlying stock does not change,
     * so picking still resolves to what the master says and no mismatch is created.
     * Cross-stock candidates are left alone, because there the naming is what rotted
     * (ArtTT-11's "-error" org stock is the one on the correct stock) and repointing
     * would move picking onto the wrong stock.
     *
     * @return array<int, object>
     */
    public function getCandidates(?int $organisationId = null): array
    {
        return DB::select(
            "
            select phs.product_id,
                   p.slug            as product_slug,
                   p.organisation_id,
                   bad.id            as bad_org_stock_id,
                   bad.code          as bad_code,
                   good.id           as good_org_stock_id,
                   good.code         as good_code
            from product_has_org_stocks phs
                join products p on p.id = phs.product_id
                join org_stocks bad on bad.id = phs.org_stock_id
                join lateral (
                    select o.id, o.code
                    from org_stocks o
                    where o.organisation_id = bad.organisation_id
                      and o.state = 'active'
                      and o.stock_id = bad.stock_id
                    order by o.id
                    limit 1
                ) good on true
            where bad.state = 'discontinued'
              and p.state = 'active'
              and (?::int is null or p.organisation_id = ?::int)
            order by p.organisation_id, bad.code, p.slug
            ",
            [$organisationId, $organisationId]
        );
    }

    public function handle(?int $organisationId = null, bool $dryRun = false): int
    {
        $candidates = $this->getCandidates($organisationId);

        if ($dryRun || !$candidates) {
            return count($candidates);
        }

        $repaired = 0;

        DB::transaction(function () use ($candidates, &$repaired) {
            foreach ($candidates as $candidate) {
                $alreadyLinked = DB::table('product_has_org_stocks')
                    ->where('product_id', $candidate->product_id)
                    ->where('org_stock_id', $candidate->good_org_stock_id)
                    ->exists();

                $badLink = DB::table('product_has_org_stocks')
                    ->where('product_id', $candidate->product_id)
                    ->where('org_stock_id', $candidate->bad_org_stock_id);

                $repaired += $alreadyLinked
                    ? $badLink->delete()
                    : $badLink->update([
                        'org_stock_id' => $candidate->good_org_stock_id,
                        'updated_at'   => now(),
                    ]);
            }
        });

        foreach (Organisation::whereIn('id', array_unique(array_column($candidates, 'organisation_id')))->get() as $organisation) {
            foreach ($organisation->warehouses as $warehouse) {
                WarehouseHydrateOrgStocksWithoutProducts::dispatch($warehouse)->delay(2);
            }
        }

        return $repaired;
    }

    public function asCommand(Command $command): int
    {
        $organisationId = null;

        if ($slug = $command->option('organisation')) {
            $organisation = Organisation::where('slug', $slug)->first();

            if (!$organisation) {
                $command->error("Organisation $slug not found.");

                return Command::FAILURE;
            }

            $organisationId = $organisation->id;
        }

        $candidates = $this->getCandidates($organisationId);

        $command->line('Products linked to a discontinued org stock that has an active replacement: '.count($candidates));

        if (!$candidates) {
            $command->info('Nothing to repair.');

            return Command::SUCCESS;
        }

        if ($command->option('dry-run')) {
            $command->table(
                ['product', 'org', 'from (discontinued)', 'to (active)'],
                array_map(fn ($candidate) => [
                    $candidate->product_slug,
                    $candidate->organisation_id,
                    $candidate->bad_code.' #'.$candidate->bad_org_stock_id,
                    $candidate->good_code.' #'.$candidate->good_org_stock_id,
                ], $candidates)
            );

            $command->info('Dry run only. No changes were written.');

            return Command::SUCCESS;
        }

        $command->info('Repaired links: '.$this->handle($organisationId));

        return Command::SUCCESS;
    }
}
