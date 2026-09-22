<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Runs the state changes that DiscontinueOrgStocks stored for a future date, once that date has
 * come. Each org stock is replayed on its own with the state it was given, so an override that
 * kept an organisation active stays kept.
 */
class ApplyScheduledOrgStockStateChanges
{
    use AsAction;

    public string $commandSignature = 'org_stocks:apply_scheduled_state_changes';

    /**
     * @return array{applied: int, waiting: int}
     */
    public function handle(): array
    {
        $stats = ['applied' => 0, 'waiting' => 0];

        OrgStock::whereNotNull('data->'.DiscontinueOrgStocks::SCHEDULED_KEY)
            ->with('organisation')
            ->orderBy('id')
            ->each(function (OrgStock $orgStock) use (&$stats) {
                $record = $orgStock->data[DiscontinueOrgStocks::SCHEDULED_KEY];

                if (Carbon::parse($record['effective_at'])->isFuture()) {
                    $stats['waiting']++;

                    return;
                }

                DiscontinueOrgStocks::make()->action($orgStock->organisation, [
                    'org_stock_ids'       => [$orgStock->id],
                    'state'               => $record['to_state'],
                    'scope'               => 'organisation',
                    'reason'              => $record['reason'],
                    'effective_at'        => $record['effective_at'],
                    'source'              => 'schedule',
                    'request_text'        => $record['request_text'],
                ]);
                $stats['applied']++;
            });

        return $stats;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $stats = $this->handle();

        $command->info("Applied {$stats['applied']} scheduled SKO state changes, {$stats['waiting']} still waiting");

        return 0;
    }
}
