<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Console\Commands;

use App\Models\CRM\TrafficSourceClick;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ties clicks recorded before audience attribution existed to the visitor who made them.
 *
 * Clicks now carry the session id, so from here on the link is exact. Everything already in the table
 * predates that and would otherwise read as never identified for as long as it survives the ninety day
 * prune, which means a channel's split stays empty for a month before it says anything.
 *
 * The match is same website, identical user agent, within minutes of each other. Where more than one
 * visitor fits, the click is left alone: a wrong customer attached to a click is worse than no
 * customer, because it is invisible afterwards. Expect roughly half to match, a tenth to be ambiguous
 * and the rest to have no visitor record at all, so treat the result as a floor and not a census.
 */
class BackfillTrafficSourceClickAudience extends Command
{
    protected $signature = 'traffic-source:backfill-audience
                           {--minutes=5 : How far either side of the click to look for the visitor}
                           {--chunk=500 : Clicks to process per batch}
                           {--dry-run : Report what would be matched without writing}';

    protected $description = 'Link historical traffic source clicks to the visitor who made them';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun  = (bool) $this->option('dry-run');

        $pending = TrafficSourceClick::whereNull('session_id')
            ->whereNotNull('user_agent')
            ->whereNotNull('website_id')
            ->where('is_bot', false);

        $total = (clone $pending)->count();

        if ($total === 0) {
            $this->info('Nothing to backfill.');

            return Command::SUCCESS;
        }

        $this->info("Examining {$total} click(s)".($dryRun ? ' [dry run, nothing written]' : '').'.');

        $matched = 0;
        $ambiguous = 0;
        $unmatched = 0;
        $bar = $this->output->createProgressBar($total);

        $pending->chunkById((int) $this->option('chunk'), function ($clicks) use ($minutes, $dryRun, &$matched, &$ambiguous, &$unmatched, $bar) {
            foreach ($clicks as $click) {
                $visitors = WebsiteVisitor::where('website_id', $click->website_id)
                    ->where('user_agent', $click->user_agent)
                    ->whereBetween('created_at', [
                        $click->created_at->copy()->subMinutes($minutes),
                        $click->created_at->copy()->addMinutes($minutes),
                    ])
                    ->limit(2)
                    ->get(['id', 'session_id', 'web_user_id']);

                if ($visitors->count() > 1) {
                    $ambiguous++;
                    $bar->advance();

                    continue;
                }

                if ($visitors->isEmpty()) {
                    $unmatched++;
                    $bar->advance();

                    continue;
                }

                $matched++;

                if (!$dryRun) {
                    $click->update($this->attribution($visitors->first(), $click));
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Matched: {$matched}");
        $this->line("Ambiguous, skipped: {$ambiguous}");
        $this->line("No visitor record: {$unmatched}");

        return Command::SUCCESS;
    }

    /**
     * What to write onto a click whose visitor we found.
     *
     * A customer created after the click was not a customer when it happened, so the identity is left
     * off deliberately and the normal forward resolution picks them up as an acquisition. For somebody
     * who already existed the state is today's, because no record of what it was in August survives.
     * That is the one figure here that a live click does not have to guess.
     *
     * @return array<string, mixed>
     */
    private function attribution(WebsiteVisitor $visitor, TrafficSourceClick $click): array
    {
        $attribution = ['session_id' => $visitor->session_id];

        if (!$visitor->web_user_id) {
            return $attribution;
        }

        $customer = DB::table('web_users')
            ->join('customers', 'customers.id', '=', 'web_users.customer_id')
            ->where('web_users.id', $visitor->web_user_id)
            ->select('customers.id', 'customers.state', 'customers.created_at')
            ->first();

        if (!$customer || $customer->created_at > $click->created_at) {
            return $attribution;
        }

        return [
            ...$attribution,
            'web_user_id'    => $visitor->web_user_id,
            'customer_id'    => $customer->id,
            'customer_state' => $customer->state,
        ];
    }
}
