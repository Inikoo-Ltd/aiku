<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RecalculateWebsiteVisitorDurations
{
    use AsAction;

    public function handle(Website $website): int
    {
        return DB::update(
            'UPDATE website_visitors
            SET duration_seconds = page_view_durations.total_seconds
            FROM (
                SELECT website_visitor_id, SUM(duration_seconds) AS total_seconds
                FROM website_page_views
                WHERE website_id = ?
                GROUP BY website_visitor_id
            ) AS page_view_durations
            WHERE website_visitors.id = page_view_durations.website_visitor_id
            AND website_visitors.website_id = ?
            AND website_visitors.duration_seconds <> page_view_durations.total_seconds',
            [$website->id, $website->id]
        );
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:recalculate_website_visitor_durations {website? : Website slug}';
    }

    public function asCommand(Command $command): int
    {
        $websites = Website::query()
            ->when($command->argument('website'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        try {
            foreach ($websites as $website) {
                $updatedVisitors = $this->handle($website);
                $command->line("$website->slug: $updatedVisitors visitors updated");
            }
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
