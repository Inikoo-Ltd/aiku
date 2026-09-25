<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Guards against HELP-3418: a location lost 47 SKOs when a pick was edited, and nobody knew until
 * the shelf came up short. A location's quantity must always equal its last audit plus every
 * movement since, and every pick must have taken its stock off a location. Either one failing
 * means stock is leaking somewhere, so this reports it the next morning instead of when a
 * picker finds an empty shelf.
 */
class MonitorStockLocationIntegrity
{
    use AsAction;

    public string $commandSignature = 'monitor:stock_location_integrity {--days=2 : Locations and picks touched in this many days}';
    public string $commandDescription = 'Alert Discord about locations whose quantity does not match their stock movements';

    private const int MAX_LISTED = 25;

    /**
     * @return array<int, string> list of problems found
     */
    public function handle(int $days = 2, ?Command $command = null): array
    {
        $issues = array_merge(
            $this->locationsNotMatchingTheirMovements($days),
            $this->picksNeverTakenOffALocation($days),
        );

        foreach ($issues as $issue) {
            $command?->error($issue);
        }

        if ($issues) {
            $this->notifyDiscord($issues, $command);
        } else {
            $command?->info('Every location matches its stock movements');
        }

        return $issues;
    }

    /**
     * Movements written in the last few minutes are left out, a pick's movement is queued and may
     * not have landed yet.
     *
     * @return array<int, string>
     */
    protected function locationsNotMatchingTheirMovements(int $days): array
    {
        $rows = $this->locationsOutOfStep($days);

        if (!$rows) {
            return [];
        }

        $issues = [count($rows).' locations hold a quantity that does not match their last audit plus movements:'];
        foreach (array_slice($rows, 0, self::MAX_LISTED) as $row) {
            $issues[] = sprintf(
                '- %s %s @ %s: location %s, movements %s',
                $row->organisation,
                $row->code,
                $row->location,
                $this->formatQuantity($row->quantity),
                $this->formatQuantity($row->expected)
            );
        }

        return $issues;
    }

    /**
     * @return array<int, object{location_org_stock_id: int, organisation: string, code: string, location: string, quantity: string, expected: string}>
     */
    public function locationsOutOfStep(int $days, ?int $organisationId = null): array
    {
        return DB::connection('aiku_no_sticky')->select(
            <<<'SQL'
            with touched as (
                select los.id, los.org_stock_id, los.location_id, los.quantity
                from location_org_stocks los
                join organisations o on o.id = los.organisation_id and o.is_aiku_stock_control
                where los.updated_at > now() - make_interval(days => ?)
                  and los.updated_at < now() - interval '5 minutes'
                  and (?::int is null or los.organisation_id = ?::int)
            ),
            seeded as (
                select touched.*, helper.date as seeded_at, coalesce(helper.audited_quantity, 0) as seed
                from touched
                left join lateral (
                    select m.date, m.audited_quantity
                    from org_stock_movements m
                    where m.org_stock_id = touched.org_stock_id and m.location_id = touched.location_id and m.class = 'helper'
                    order by m.date desc
                    limit 1
                ) helper on true
            ),
            ledger as (
                select seeded.*, seeded.seed + coalesce((
                    select sum(m.quantity)
                    from org_stock_movements m
                    where m.org_stock_id = seeded.org_stock_id and m.location_id = seeded.location_id and m.class = 'movement'
                      and (seeded.seeded_at is null or m.date > seeded.seeded_at)
                ), 0) as expected
                from seeded
            )
            select ledger.id as location_org_stock_id, o.slug as organisation, os.code, l.code as location, ledger.quantity, ledger.expected
            from ledger
            join org_stocks os on os.id = ledger.org_stock_id
            join organisations o on o.id = os.organisation_id
            join locations l on l.id = ledger.location_id
            where abs(ledger.quantity - ledger.expected) > 0.001
            order by abs(ledger.quantity - ledger.expected) desc
            SQL,
            [$days, $organisationId, $organisationId]
        );
    }

    /**
     * @return array<int, string>
     */
    protected function picksNeverTakenOffALocation(int $days): array
    {
        $count = DB::connection('aiku_no_sticky')->table('pickings')
            ->join('organisations', 'organisations.id', '=', 'pickings.organisation_id')
            ->where('organisations.is_aiku_stock_control', true)
            ->where('pickings.type', 'pick')
            ->where('pickings.quantity', '>', 0)
            ->whereNull('pickings.org_stock_movement_id')
            ->where('pickings.created_at', '>', now()->subDays($days))
            ->where('pickings.created_at', '<', now()->subMinutes(15))
            ->count();

        return $count === 0 ? [] : ["$count picks have no stock movement, their stock was never taken off a location"];
    }

    private function formatQuantity(mixed $quantity): string
    {
        return rtrim(rtrim(number_format((float)$quantity, 3, '.', ''), '0'), '.');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        return $this->handle((int)$command->option('days'), $command) === [] ? 0 : 1;
    }

    protected function notifyDiscord(array $issues, ?Command $command = null): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured. Please set it in config/services.php or .env');

            return;
        }

        try {
            Http::post($webhookUrl, [
                'content' => mb_substr("📦 **Stock leaking from locations** 📦\n\n".implode("\n", $issues), 0, 1990),
            ]);
        } catch (\Exception $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }
}
