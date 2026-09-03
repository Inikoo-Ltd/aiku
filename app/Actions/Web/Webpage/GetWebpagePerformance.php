<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 15:10:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class GetWebpagePerformance
{
    use AsAction;

    /**
     * @return array{start_date: string, end_date: string, currency: string, search: array, sales: array, events: array}
     */
    public function handle(Webpage $webpage, array $modelData): array
    {
        $endDate   = Carbon::parse(Arr::get($modelData, 'endDate') ?? now()->toDateString())->endOfDay();
        $startDate = Carbon::parse(Arr::get($modelData, 'startDate') ?? $endDate->copy()->subDays(90)->toDateString())->startOfDay();

        try {
            $search = GetWebpageGoogleCloud::make()->action($webpage, [
                'startDate' => $startDate->toDateString(),
                'endDate'   => $endDate->toDateString(),
            ]);
        } catch (Throwable) {
            $search = [];
        }

        return [
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'currency'   => $webpage->shop->currency->code,
            'search'     => $search,
            'sales'      => $this->sales($webpage, $startDate, $endDate),
            'events'     => $this->events($webpage, $startDate, $endDate),
        ];
    }

    private function sales(Webpage $webpage, Carbon $startDate, Carbon $endDate): array
    {
        $model      = $webpage->model;
        $timeSeries = match (true) {
            $model instanceof Product => Asset::find($model->asset_id)?->timeSeries(),
            $model instanceof ProductCategory, $model instanceof Collection => $model->timeSeries(),
            default => null,
        };
        if (!$timeSeries) {
            return [];
        }
        $timeSeries = $timeSeries->where('frequency', TimeSeriesFrequencyEnum::DAILY)->first();
        if (!$timeSeries) {
            return [];
        }

        $timezone = $webpage->shop->timezone->name;

        return $timeSeries->records()
            ->whereBetween('from', [$startDate, $endDate])
            ->orderBy('from')
            ->get()
            ->map(fn ($record) => [
                'date'   => $record->from->setTimezone($timezone)->toDateString(),
                'sales'  => round($record->sales_external + $record->sales_internal, 2),
                'orders' => (int)$record->orders,
            ])->values()->all();
    }

    private function events(Webpage $webpage, Carbon $startDate, Carbon $endDate): array
    {
        $events = [];

        $snapshots = $webpage->snapshots()
            ->whereBetween('published_at', [$startDate, $endDate])
            ->select(['id', 'parent_type', 'parent_id', 'published_at', 'comment', 'publisher_type', 'publisher_id'])
            ->with('publisher')
            ->get();
        foreach ($snapshots as $snapshot) {
            $events[] = [
                'datetime' => $snapshot->published_at,
                'type'     => 'publish',
                'label'    => $snapshot->comment ?: __('Page published'),
                'user'     => $snapshot->publisher?->contact_name,
            ];
        }

        $productIds = match (true) {
            $webpage->model instanceof Product => [$webpage->model->id],
            $webpage->model instanceof ProductCategory => Product::where($webpage->model->type->value.'_id', $webpage->model->id)->pluck('id')->all(),
            default => [],
        };

        if ($productIds) {
            $priceAudits = Audit::where('auditable_type', 'Product')
                ->whereIn('auditable_id', $productIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('new_values->price')
                ->get();
            $products    = Product::whereIn('id', $priceAudits->pluck('auditable_id')->unique())->pluck('code', 'id');
            $users       = User::whereIn('id', $priceAudits->where('user_type', 'User')->pluck('user_id')->unique())->pluck('contact_name', 'id');

            foreach ($priceAudits as $audit) {
                $events[] = [
                    'datetime' => $audit->created_at,
                    'type'     => 'price',
                    'label'    => $products[$audit->auditable_id].': '.Arr::get($audit->old_values, 'price').' → '.Arr::get($audit->new_values, 'price'),
                    'user'     => $users[$audit->user_id] ?? null,
                ];
            }
        }

        usort($events, fn ($a, $b) => $a['datetime'] <=> $b['datetime']);
        $timezone = $webpage->shop->timezone->name;

        return array_map(fn ($event) => [
            ...$event,
            'date'     => $event['datetime']->setTimezone($timezone)->toDateString(),
            'datetime' => $event['datetime']->toIso8601String()
        ], $events);
    }
}
