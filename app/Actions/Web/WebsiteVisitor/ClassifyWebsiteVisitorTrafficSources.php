<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ClassifyWebsiteVisitorTrafficSources
{
    use AsAction;

    private const int UPDATE_BATCH_SIZE = 2000;

    private array $visitorIdsBySource = [];

    private int $updatedVisitors = 0;

    public function handle(Website $website): int
    {
        $this->visitorIdsBySource = [];
        $this->updatedVisitors    = 0;

        $classifier   = GetWebsiteVisitorTrafficSource::make();
        $previousVisit = null;

        $visitors = DB::table('website_visitors')
            ->where('website_id', $website->id)
            ->orderBy('visitor_hash')
            ->orderBy('first_seen_at')
            ->orderBy('id')
            ->select(['id', 'visitor_hash', 'first_seen_at', 'last_seen_at', 'landing_page', 'referrer_url'])
            ->lazy();

        foreach ($visitors as $visitor) {
            $firstSeenAt = Carbon::parse($visitor->first_seen_at);
            $lastSeenAt  = Carbon::parse($visitor->last_seen_at);

            $continuesPreviousVisit = $previousVisit
                && $previousVisit['visitor_hash'] === $visitor->visitor_hash
                && $previousVisit['last_seen_at']->gte($firstSeenAt->copy()->subSeconds(UpdateWebsiteVisitor::MAX_IDLE_SECONDS));

            $source = $classifier->fromLandingPage($visitor->landing_page)
                ?? ($continuesPreviousVisit ? $previousVisit['source'] : $classifier->fromReferrer($visitor->referrer_url));

            $previousVisit = [
                'visitor_hash' => $visitor->visitor_hash,
                'last_seen_at' => $continuesPreviousVisit ? $lastSeenAt->max($previousVisit['last_seen_at']) : $lastSeenAt,
                'source'       => $source,
            ];

            $this->queueUpdate($visitor->id, $source);
        }

        foreach (array_keys($this->visitorIdsBySource) as $sourceKey) {
            $this->flush($sourceKey);
        }

        return $this->updatedVisitors;
    }

    private function queueUpdate(int $visitorId, array $source): void
    {
        $sourceKey = json_encode($source);

        $this->visitorIdsBySource[$sourceKey][] = $visitorId;

        if (count($this->visitorIdsBySource[$sourceKey]) >= self::UPDATE_BATCH_SIZE) {
            $this->flush($sourceKey);
        }
    }

    private function flush(string $sourceKey): void
    {
        $source = json_decode($sourceKey, true);

        $this->updatedVisitors += DB::table('website_visitors')
            ->whereIn('id', $this->visitorIdsBySource[$sourceKey])
            ->where(function ($query) use ($source) {
                $query->whereRaw('traffic_source_type IS DISTINCT FROM ?', [$source['traffic_source_type']])
                    ->orWhereRaw('traffic_source_reference IS DISTINCT FROM ?', [$source['traffic_source_reference']]);
            })
            ->update($source);

        $this->visitorIdsBySource[$sourceKey] = [];
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:classify_website_visitor_traffic_sources {website? : Website slug}';
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
