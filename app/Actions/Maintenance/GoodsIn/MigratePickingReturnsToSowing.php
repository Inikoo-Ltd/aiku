<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\GoodsIn;

use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\Picking;
use App\Models\GoodsIn\Sowing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MigratePickingReturnsToSowing
{
    use AsAction;

    public string $commandSignature = 'sowing:migrate-from-pickings {--dry-run : Run without making changes} {--limit= : Limit the number of records to process}';
    public string $commandDescription = 'Migrate Picking records with type="return" to the new Sowing model';


    public function handle(bool $dryRun = false, ?int $limit = null): array
    {
        $stats = [
            'migrated' => 0,
            'skipped'  => 0,
            'errors'   => [],
        ];

        $query = Picking::where('type', PickingTypeEnum::RETURN);

        if ($limit) {
            $query->limit($limit);
        }

        $pickings = $query->get();

        /** @var Picking $picking */
        foreach ($pickings as $picking) {
            try {
                if (!$dryRun) {
                    DB::transaction(function () use ($picking) {
                        Sowing::create([
                            'group_id'              => $picking->group_id,
                            'organisation_id'       => $picking->organisation_id,
                            'shop_id'               => $picking->shop_id,
                            'delivery_note_id'      => $picking->delivery_note_id,
                            'delivery_note_item_id' => $picking->delivery_note_item_id,
                            'quantity'              => abs($picking->quantity),
                            'org_stock_movement_id' => $picking->org_stock_movement_id,
                            'org_stock_id'          => $picking->org_stock_id,
                            'sower_user_id'         => $picking->picker_user_id,
                            'location_id'           => $picking->location_id,
                            'data'                  => $picking->data,
                            'sowed_at'              => $picking->created_at,
                            'created_at'            => $picking->created_at,
                            'updated_at'            => $picking->updated_at,
                        ]);

                        $picking->forceDelete();
                    });
                }

                $stats['migrated']++;
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'picking_id' => $picking->id,
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool)$command->option('dry-run');
        $limit  = $command->option('limit') ? (int)$command->option('limit') : null;

        if ($dryRun) {
            $command->info('Running in DRY-RUN mode - no changes will be made');
        }

        $command->info('Migrating Picking records with type="return" to Sowing model...');

        // Count total records to migrate
        $totalCount = Picking::where('type', PickingTypeEnum::RETURN)->count();
        $command->info("Found $totalCount picking records with type='return'");

        if ($totalCount === 0) {
            $command->info('No records to migrate.');

            return 0;
        }

        $stats = $this->handle($dryRun, $limit);

        $command->newLine();
        $command->info('Migration Summary:');
        $command->table(
            ['Metric', 'Count'],
            [
                ['Migrated', $stats['migrated']],
                ['Skipped (already migrated)', $stats['skipped']],
                ['Errors', count($stats['errors'])],
            ]
        );

        if (!empty($stats['errors'])) {
            $command->newLine();
            $command->error('Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $command->line("  Picking ID {$error['picking_id']}: {$error['error']}");
            }
        }

        if ($dryRun) {
            $command->newLine();
            $command->warn('This was a DRY-RUN. Run without --dry-run to apply changes.');
        }

        return empty($stats['errors']) ? 0 : 1;
    }
}
