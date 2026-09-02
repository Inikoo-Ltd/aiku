<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\History;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes audits that never carried history: rows whose only changed attributes are machine
 * bookkeeping — platform sync flags, derived counters, credential refreshes — plus the rows
 * whose payload ended up empty. They are not archived: nothing reads a trail of a robot
 * telling itself a flag is still false. The models now exclude these attributes, so this is
 * the one-off catch up for what was written before that.
 *
 * @var array<string, array<int, string>> NOISE_ATTRIBUTES auditable type => attributes with no history value
 */
class PurgeNoiseAudits
{
    use AsAction;

    public string $commandSignature = 'helpers:purge_noise_audits {--c|chunk=5000} {--l|limit=} {--d|dry-run}';

    public string $commandDescription = 'Delete flag-only, counter-only and credential-only audits';

    private const NOISE_ATTRIBUTES = [
        'Portfolio' => [
            'platform_status',
            'exist_in_platform',
            'has_valid_platform_product_id',
            'number_platform_possible_matches',
            'platform_possible_matches',
            'mark_for_update_stock',
            'stock_last_updated_at',
            'stock_last_fail_updated_at',
            'last_stock_value',
            'last_fetched_at',
            'last_added_at',
            'last_removed_at',
        ],
        'EbayUser' => [
            'settings.credentials.ebay_access_token',
            'settings.credentials.ebay_token_expires_at',
            'settings.credentials.ebay_refresh_token',
        ],
        'CustomerSalesChannel' => [
            'ban_stock_update_util',
            'ping_error_count',
            'checked_as_down_at',
            'checked_as_down_days',
            'is_down',
            'exist_in_platform',
            'can_connect_to_platform',
            'last_order_created_at',
            'last_order_submitted_at',
            'last_order_dispatched_at',
            'number_portfolios',
            'number_portfolio_broken',
            'number_customer_clients',
            'number_downside',
            'number_fulfilment_orders',
            'number_fulfilment_orders_state_confirmed',
            'number_orders',
            'number_orders_handing_type_shipping',
            'number_orders_state_cancelled',
            'number_orders_state_creating',
            'number_orders_state_dispatched',
            'number_orders_state_handling',
            'number_orders_state_handling_blocked',
            'number_orders_state_in_warehouse',
            'number_orders_state_packed',
            'number_orders_state_packing',
            'number_orders_state_picked',
            'number_orders_state_submitted',
            'number_orders_status_creating',
            'number_orders_status_processing',
        ],
    ];

    public function handle(int $chunkSize = 5000, ?int $limit = null, bool $dryRun = false, ?Command $command = null): int
    {
        $deletedTotal = 0;

        foreach (self::NOISE_ATTRIBUTES as $auditableType => $attributes) {
            if ($dryRun) {
                $count = $this->noiseAudits($auditableType, $attributes)->count();
                $command?->info("$auditableType: $count noise audits");
                $deletedTotal += $count;
                continue;
            }

            while (true) {
                $batchSize = $limit ? min($chunkSize, $limit - $deletedTotal) : $chunkSize;
                if ($batchSize <= 0) {
                    return $deletedTotal;
                }

                $auditIds = $this->noiseAudits($auditableType, $attributes)->limit($batchSize)->pluck('id')->all();

                if (!$auditIds) {
                    break;
                }

                DB::table('audits')->whereIn('id', $auditIds)->delete();
                $deletedTotal += count($auditIds);
                $command?->info("$auditableType: $deletedTotal deleted");
            }
        }

        return $deletedTotal;
    }

    /**
     * @param array<int, string> $attributes
     */
    private function noiseAudits(string $auditableType, array $attributes): \Illuminate\Database\Query\Builder
    {
        return DB::table('audits')
            ->select('id')
            ->where('auditable_type', $auditableType)
            ->whereRaw(
                "not exists (select 1 from jsonb_object_keys(case when jsonb_typeof(new_values::jsonb) = 'object' then new_values::jsonb else '{}'::jsonb end) k where k <> all(?))",
                ['{'.implode(',', $attributes).'}']
            );
    }

    public function asCommand(Command $command): int
    {
        $deleted = $this->handle(
            chunkSize: (int) $command->option('chunk'),
            limit: $command->option('limit') ? (int) $command->option('limit') : null,
            dryRun: (bool) $command->option('dry-run'),
            command: $command
        );

        $command->info(($command->option('dry-run') ? 'Would delete' : 'Deleted')." $deleted audits");
        $command->info('Run VACUUM (ANALYZE) audits; after a large run.');

        return 0;
    }
}
