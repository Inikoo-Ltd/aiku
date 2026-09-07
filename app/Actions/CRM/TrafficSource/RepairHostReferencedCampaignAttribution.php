<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fills in the campaign on the host-referenced pivot rows that were attached without one while
 * `traffic_source_campaigns.reference` was unique across the whole table: a second shop seeing a host
 * the first shop already owned could not mint its own row, so its visitors were credited to the channel
 * but to no referrer.
 *
 * Customers and prospects are rebuilt from their own touch history, which is what now mints the per-shop
 * campaign. That rebuild applies today's touch filters, so a touch that no longer passes them is dropped
 * and its share moves to the customer's other channels - the same outcome any later recalculation of
 * that customer would produce.
 *
 * Orders carry no history of their own - their attribution is the snapshot taken from the customer at
 * submit time - so the campaign is read off the customer's history instead, and only when that channel
 * resolves to a single host there; a customer who arrived from two referrers leaves the order's row as
 * it is rather than guessing.
 */
class RepairHostReferencedCampaignAttribution
{
    use AsAction;

    public function handle(?Shop $shop = null, bool $dryRun = false): array
    {
        $summary = [
            'customers'            => 0,
            'prospects'            => 0,
            'orders'               => 0,
            'orders_ambiguous'     => 0,
            'campaign_unavailable' => 0,
            'without_history'      => 0,
            'failed'               => 0,
        ];

        $hostSources = TrafficSource::whereIn('type', TrafficSourcesTypeEnum::hostReferencedValues())
            ->when($shop, fn ($query) => $query->where('shop_id', $shop->id))
            ->get(['id', 'type'])
            ->keyBy('id');

        $pendingRows = DB::table('model_has_traffic_sources')
            ->whereIn('traffic_source_id', $hostSources->keys())
            ->whereNull('traffic_source_campaign_id')
            ->orderBy('id')
            ->get(['id', 'model_type', 'model_id', 'traffic_source_id'])
            ->groupBy(fn ($row) => $row->model_type.'|'.$row->model_id);

        foreach ($pendingRows as $rows) {
            $model = $this->resolveModel($rows->first()->model_type, (int) $rows->first()->model_id);

            if (!$model) {
                $summary['failed']++;

                continue;
            }

            try {
                if ($model instanceof Order && blank($model->traffic_sources)) {
                    $summary[$this->stampOrderFromCustomerHistory($model, $rows, $hostSources, $dryRun)]++;

                    continue;
                }

                if (blank($model->traffic_sources)) {
                    $summary['without_history']++;

                    continue;
                }

                if (!$dryRun) {
                    DB::transaction(fn () => RecalculateTrafficSourceAttribution::run($model));
                }

                $summary[$model instanceof Order ? 'orders' : strtolower(class_basename($model)).'s']++;
            } catch (\Throwable $e) {
                report($e);
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function resolveModel(string $morphType, int $id): ?Model
    {
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        return class_exists($class) ? $class::find($id) : null;
    }

    /**
     * @param Collection<int, object{id: int, traffic_source_id: int}> $rows
     * @param Collection<int, TrafficSource>                           $hostSources
     */
    private function stampOrderFromCustomerHistory(Order $order, Collection $rows, Collection $hostSources, bool $dryRun): string
    {
        $touches = ParseTrafficSourceTouches::run($order->customer?->traffic_sources);

        if ($touches === []) {
            return 'without_history';
        }

        $hostByRow = [];

        foreach ($rows as $row) {
            $source = $hostSources[$row->traffic_source_id];
            $type   = TrafficSourcesTypeEnum::from($source->type);

            $hosts = collect($touches)
                ->filter(fn (array $touch) => $touch['type'] === $type
                    && GetTrafficSourceFromRefererHeader::normaliseHost($touch['campaign_ref']) === $touch['campaign_ref'])
                ->pluck('campaign_ref')
                ->unique();

            if ($hosts->count() !== 1) {
                return 'orders_ambiguous';
            }

            $hostByRow[$row->id] = [$source, $hosts->first()];
        }

        if ($dryRun) {
            return 'orders';
        }

        $campaignByRow = [];

        foreach ($hostByRow as $rowId => [$source, $host]) {
            $campaignId = AttachTrafficSourcesToModel::make()->resolveCampaignId($source, $host);

            if (!$campaignId) {
                return 'campaign_unavailable';
            }

            $campaignByRow[$rowId] = $campaignId;
        }

        foreach ($campaignByRow as $rowId => $campaignId) {
            DB::table('model_has_traffic_sources')->where('id', $rowId)->update(['traffic_source_campaign_id' => $campaignId]);
        }

        foreach (TrafficSourceCampaign::whereIn('id', array_unique($campaignByRow))->get() as $campaign) {
            TrafficSourceCampaignHydrateStats::dispatch($campaign);
        }

        return 'orders';
    }

    public function getCommandSignature(): string
    {
        return 'traffic-source:repair-host-campaigns {--shop= : shop slug, every shop when omitted} {--dry-run}';
    }

    public function asCommand(Command $command): void
    {
        $shop = $command->option('shop') ? Shop::where('slug', $command->option('shop'))->firstOrFail() : null;

        $summary = $this->handle($shop, (bool) $command->option('dry-run'));

        $command->table(
            ['what', 'count'],
            collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );
    }
}
