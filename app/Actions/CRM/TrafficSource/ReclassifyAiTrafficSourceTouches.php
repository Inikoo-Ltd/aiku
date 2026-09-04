<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves the touches recorded before the AI channel existed out of Referral and into it.
 *
 * Those touches are recoverable because a referral touch stores the referring host as its campaign
 * reference, so chatgpt.com is still named in the history. Gemini is not: it was classified as organic
 * Google, which stores no reference at all, and nothing in the stored history distinguishes it from a
 * Google search. Those touches stay where they are.
 *
 * The campaign rows are repointed rather than recreated, because `reference` is unique across every
 * traffic source: leaving `chatgpt.com` claimed by the Referral source would stop the AI source ever
 * getting a campaign row of its own, and the reclassified touches would land on the channel total with
 * no per-assistant breakdown.
 */
class ReclassifyAiTrafficSourceTouches
{
    use AsAction;

    /**
     * @throws \RuntimeException when a shop has no AI channel row yet, since rewriting its touches
     *                           first would move them onto a channel that cannot hold them and the
     *                           customer would lose that share of their attribution entirely.
     */
    public function handle(bool $dryRun = false): array
    {
        $summary = ['campaigns' => 0, 'customers' => 0, 'prospects' => 0, 'orders' => 0, 'clicks' => 0, 'visit_days' => 0];

        $aiSources = TrafficSource::where('type', TrafficSourcesTypeEnum::AI->value)->pluck('id', 'shop_id');

        $referralSources = TrafficSource::whereIn('type', [
            TrafficSourcesTypeEnum::REFERRAL->value,
            TrafficSourcesTypeEnum::ORGANIC_SEARCH->value,
        ])->get(['id', 'shop_id']);

        foreach ($referralSources as $referralSource) {
            $aiSourceId = $aiSources[$referralSource->shop_id] ?? null;

            if (!$aiSourceId) {
                throw new \RuntimeException('Shop '.$referralSource->shop_id.' has no AI traffic source; run traffic-source:seed first.');
            }

            $campaigns = TrafficSourceCampaign::where('traffic_source_id', $referralSource->id)
                ->get(['id', 'reference'])
                ->filter(fn ($campaign) => GetTrafficSourceFromRefererHeader::isAiAssistantHost($campaign->reference));

            foreach ($campaigns as $campaign) {
                $summary['campaigns']++;

                if ($dryRun) {
                    continue;
                }

                TrafficSourceCampaign::where('id', $campaign->id)->update([
                    'traffic_source_id' => $aiSourceId,
                    'type'              => TrafficSourcesTypeEnum::AI->value,
                ]);
            }
        }

        foreach ([Customer::class => 'customers', Prospect::class => 'prospects', Order::class => 'orders'] as $class => $key) {
            $summary[$key] = $this->reclassifyModels($class, $dryRun);
        }

        [$summary['clicks'], $summary['visit_days']] = $this->reclassifyClicksAndVisits($aiSources, $dryRun);

        if (!$dryRun) {
            TrafficSource::whereIn('type', [
                TrafficSourcesTypeEnum::REFERRAL->value,
                TrafficSourcesTypeEnum::AI->value,
            ])->each(fn (TrafficSource $trafficSource) => TrafficSourceHydrateCustomers::dispatch($trafficSource));
        }

        return $summary;
    }

    /**
     * The click log and the daily visit counts remember the channel an arrival was filed under at
     * the time, so before the AI channel existed a ChatGPT arrival is a Referral click and a Referral
     * visit. Left alone, the assistant lines under AI count arrivals the AI channel total does not,
     * and a child bigger than its parent is a report nobody trusts. The clicks are retyped, and for
     * every shop and day the retyped arrivals are moved from the old channel's visit row to the AI
     * one, counted the way the counter counts: one per browser per day.
     *
     * @param \Illuminate\Support\Collection<int, int> $aiSources AI traffic source id by shop id
     *
     * @return array{0: int, 1: int}
     */
    private function reclassifyClicksAndVisits($aiSources, bool $dryRun): array
    {
        $oldTypes = [TrafficSourcesTypeEnum::REFERRAL->value, TrafficSourcesTypeEnum::ORGANIC_SEARCH->value];

        $aiHosts = DB::table('traffic_source_clicks')
            ->whereIn('type', $oldTypes)
            ->whereNotNull('campaign_ref')
            ->distinct()
            ->pluck('campaign_ref')
            ->filter(fn (string $host) => GetTrafficSourceFromRefererHeader::isAiAssistantHost($host))
            ->values();

        if ($aiHosts->isEmpty()) {
            return [0, 0];
        }

        $moved = DB::table('traffic_source_clicks')
            ->whereIn('type', $oldTypes)
            ->whereIn('campaign_ref', $aiHosts)
            ->where('is_bot', false)
            ->groupBy('shop_id', 'type', DB::raw('created_at::date'))
            ->select('shop_id', 'type', DB::raw('created_at::date as day'), DB::raw('COUNT(DISTINCT (ip, user_agent)) as visits'))
            ->get();

        $clicks = DB::table('traffic_source_clicks')
            ->whereIn('type', $oldTypes)
            ->whereIn('campaign_ref', $aiHosts)
            ->count();

        if ($dryRun) {
            return [$clicks, $moved->count()];
        }

        $oldSources = TrafficSource::whereIn('type', $oldTypes)->get(['id', 'shop_id', 'type'])
            ->keyBy(fn (TrafficSource $source) => $source->shop_id.':'.$source->type);

        foreach ($moved as $row) {
            $oldSource = $oldSources[$row->shop_id.':'.$row->type] ?? null;
            $aiSourceId = $aiSources[$row->shop_id] ?? null;

            if (!$oldSource || !$aiSourceId) {
                continue;
            }

            DB::table('traffic_source_visits')
                ->where('traffic_source_id', $oldSource->id)
                ->where('date', $row->day)
                ->update(['visits' => DB::raw('GREATEST(visits - '.(int) $row->visits.', 0)'), 'updated_at' => now()]);

            $aiRow = DB::table('traffic_source_visits')->where('traffic_source_id', $aiSourceId)->where('date', $row->day)->first();

            if ($aiRow) {
                DB::table('traffic_source_visits')->where('id', $aiRow->id)
                    ->update(['visits' => DB::raw('visits + '.(int) $row->visits), 'updated_at' => now()]);
            } else {
                DB::table('traffic_source_visits')->insert([
                    'shop_id'           => $row->shop_id,
                    'traffic_source_id' => $aiSourceId,
                    'date'              => $row->day,
                    'visits'            => (int) $row->visits,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }

        DB::table('traffic_source_clicks')
            ->whereIn('type', $oldTypes)
            ->whereIn('campaign_ref', $aiHosts)
            ->update(['type' => TrafficSourcesTypeEnum::AI->value]);

        return [$clicks, $moved->count()];
    }

    /**
     * @param class-string<Model> $class
     */
    private function reclassifyModels(string $class, bool $dryRun): int
    {
        $changed = 0;

        $class::whereNotNull('traffic_sources')
            ->where(function ($query) {
                foreach (self::assistantHosts() as $host) {
                    $query->orWhere('traffic_sources', 'like', '%'.$host.'%');
                }
            })
            ->chunkById(200, function ($models) use (&$changed, $dryRun) {
                foreach ($models as $model) {
                    $rewritten = $this->rewriteTouches($model->traffic_sources);

                    if ($rewritten === null) {
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        continue;
                    }

                    $model->updateQuietly(['traffic_sources' => $rewritten]);
                    RecalculateTrafficSourceAttribution::run($model);
                }
            });

        return $changed;
    }

    /**
     * The literal hosts behind the patterns, used only to keep the backfill from reading every row
     * that has any touch history at all. The patterns stay the authority on what is actually AI.
     *
     * @return array<int, string>
     */
    private static function assistantHosts(): array
    {
        return array_map(
            fn (string $pattern) => str_replace(['/(^|\\.)', '\\.', '$/'], ['', '.', ''], $pattern),
            GetTrafficSourceFromRefererHeader::AI_ASSISTANT_PATTERNS
        );
    }

    private function rewriteTouches(string $rawTouches): ?string
    {
        $touches = ParseTrafficSourceTouches::run($rawTouches);

        if ($touches === []) {
            return null;
        }

        $aiAbbr    = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::AI->value];
        $rewritten = false;

        $segments = array_map(function (array $touch) use ($aiAbbr, &$rewritten) {
            $isReclassifiable = $touch['type'] === TrafficSourcesTypeEnum::REFERRAL
                && GetTrafficSourceFromRefererHeader::isAiAssistantHost($touch['campaign_ref']);

            if ($isReclassifiable) {
                $rewritten = true;
            }

            return $touch['timestamp'].($isReclassifiable ? $aiAbbr : $touch['abbr']).($touch['campaign_ref'] ?? '');
        }, $touches);

        return $rewritten ? implode('|', $segments) : null;
    }

    public function getCommandSignature(): string
    {
        return 'traffic-source:reclassify-ai {--dry-run}';
    }

    public function asCommand(Command $command): void
    {
        $summary = $this->handle((bool) $command->option('dry-run'));

        $command->table(
            ['what', 'count'],
            collect($summary)
                ->map(fn ($value, $key) => [$key, $value])
                ->values()
                ->all()
        );
    }
}
