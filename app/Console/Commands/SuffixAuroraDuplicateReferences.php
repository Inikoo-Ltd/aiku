<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SuffixAuroraDuplicateReferences extends Command
{
    protected $signature = 'aurora:suffix_duplicate_references {--O|organisation= : Organisation slug} {--apply : Write the changes, otherwise dry run}';

    protected $description = 'Append -au to aurora fetched invoices and delivery notes whose reference collides with an aiku native one';

    private const TABLES = ['invoices', 'delivery_notes'];

    public function handle(): int
    {
        $organisationId = null;
        if ($slug = $this->option('organisation')) {
            $organisationId = DB::table('organisations')->where('slug', $slug)->value('id');
            if (!$organisationId) {
                $this->error("Organisation $slug not found");

                return 1;
            }
        }

        $apply = (bool)$this->option('apply');

        foreach (self::TABLES as $table) {
            $rows = $this->collisions($table, $organisationId);

            $this->info(strtoupper($table).': '.$rows->count().' aurora records to suffix');

            foreach ($rows as $row) {
                $this->line("  $row->reference -> $row->reference-au  (id $row->id, source $row->source_id)");

                if ($apply) {
                    DB::table($table)->where('id', $row->id)->update(['reference' => $row->reference.'-au']);
                }
            }
        }

        if (!$apply) {
            $this->warn('Dry run, nothing written. Re-run with --apply');
        }

        return 0;
    }

    /**
     * Aurora sourced records sharing a reference with at least one aiku native record.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function collisions(string $table, ?int $organisationId)
    {
        $duplicated = DB::table($table)
            ->select('reference', 'organisation_id')
            ->whereNull('deleted_at')
            ->groupBy('reference', 'organisation_id')
            ->havingRaw('count(*) > 1')
            ->havingRaw('count(source_id) > 0')
            ->havingRaw('count(*) - count(source_id) > 0');

        if ($organisationId) {
            $duplicated->where('organisation_id', $organisationId);
        }

        $query = DB::table($table)
            ->joinSub($duplicated, 'duplicated', function ($join) use ($table) {
                $join->on('duplicated.reference', '=', $table.'.reference')
                    ->on('duplicated.organisation_id', '=', $table.'.organisation_id');
            })
            ->whereNotNull($table.'.source_id')
            ->whereNull($table.'.deleted_at')
            ->select($table.'.id', $table.'.reference', $table.'.source_id')
            ->orderBy($table.'.reference');

        if ($organisationId) {
            $query->where($table.'.organisation_id', $organisationId);
        }

        return $query->get();
    }
}
