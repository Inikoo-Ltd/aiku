<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

trait WithTimeSeriesRedo
{
    /** @var array<string, string>|null */
    protected ?array $activityDateRanges = null;

    protected function modifyQuery(Builder $query): Builder
    {
        return $query;
    }

    protected function beforeCommand(Command $command): void
    {
    }

    /**
     * Tables holding the activity dates a time series is rebuilt from.
     *
     * @return array<int, array{query: callable(): Builder, key: string|array<int, string>, date: string}>
     */
    protected function dateRangeSources(): array
    {
        return [];
    }

    protected function scopeOrganisation(Builder $query, Command $command): Builder
    {
        if ($command->hasOption('organisation') && $command->option('organisation')) {
            $organisation = Organisation::where('slug', $command->option('organisation'))->first();

            if ($organisation) {
                $query->where('organisation_id', $organisation->id);
            }
        }

        return $query;
    }

    protected function scopeShop(Builder $query, Command $command): Builder
    {
        if ($command->hasOption('shop') && $command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->first();

            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }

        return $query;
    }

    public function asCommand(Command $command): int
    {
        $this->beforeCommand($command);
        $this->preloadDateRanges($command);
        $command->info($command->getName());
        $tableName = (new $this->model())->getTable();
        $query     = $this->modifyQuery($this->scopeShop($this->scopeOrganisation($this->prepareQuery($tableName, $command), $command), $command));
        $count     = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (Collection $modelsData) use ($bar, $command) {
            foreach ($modelsData as $modelId) {
                try {
                    $this->handle($modelId->id, $command->option('from'), $command->option('to'), (bool) $command->option('async'));
                } catch (Throwable $e) {
                    $command->error($e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');

        return 0;
    }

    /**
     * One grouped aggregate per source replaces a per row min/max query, which Postgres
     * would otherwise answer by walking the whole table through the date index.
     */
    protected function preloadDateRanges(Command $command): void
    {
        $sources   = $this->dateRangeSources();
        $hasWindow = $command->hasOption('from') && $command->option('from') && $command->hasOption('to') && $command->option('to');

        if (!$sources || $hasWindow) {
            return;
        }

        $ranges = [];

        foreach ($sources as $source) {
            $keyColumns = Arr::wrap($source['key']);
            $selects    = [];

            foreach (array_values($keyColumns) as $position => $keyColumn) {
                $selects[] = "$keyColumn as range_key_$position";
            }

            $selects[] = "min({$source['date']}) as min_date";
            $selects[] = "max({$source['date']}) as max_date";

            $query = ($source['query'])()->selectRaw(implode(', ', $selects))->groupBy($keyColumns);

            foreach ($keyColumns as $keyColumn) {
                $query->whereNotNull($keyColumn);
            }

            foreach ($query->cursor() as $row) {
                $rangeKey = implode(':', array_map(fn ($position) => $row->{"range_key_$position"}, array_keys(array_values($keyColumns))));

                $ranges[$rangeKey] = $this->mergeDateRange($ranges[$rangeKey] ?? null, $row->min_date, $row->max_date);
            }
        }

        $this->activityDateRanges = $ranges;
    }

    /**
     * @param  int|string|array<int, int|string>  $key
     * @return array{from: ?string, to: ?string}
     */
    protected function getDateRange(int|string|array $key): array
    {
        $keyValues = array_values(Arr::wrap($key));

        if ($this->activityDateRanges !== null) {
            return $this->unpackDateRange($this->activityDateRanges[implode(':', $keyValues)] ?? null);
        }

        $packed = null;

        foreach ($this->dateRangeSources() as $source) {
            $keyColumns = array_values(Arr::wrap($source['key']));
            $query      = ($source['query'])()
                ->selectRaw("min({$source['date']}) as min_date, max({$source['date']}) as max_date")
                ->groupBy($keyColumns);

            foreach ($keyColumns as $position => $keyColumn) {
                $query->where($keyColumn, $keyValues[$position]);
            }

            $row = $query->first();

            if ($row) {
                $packed = $this->mergeDateRange($packed, $row->min_date, $row->max_date);
            }
        }

        return $this->unpackDateRange($packed);
    }

    /**
     * Both dates live in one string because an array per entry costs about four times
     * the memory, and the biggest preload holds a few hundred thousand entries.
     */
    protected function mergeDateRange(?string $packed, ?string $minDate, ?string $maxDate): string
    {
        [$currentFrom, $currentTo] = $this->splitDateRange($packed);

        return $this->pickDate($currentFrom, $minDate, true).'|'.$this->pickDate($currentTo, $maxDate, false);
    }

    /**
     * @return array{from: ?string, to: ?string}
     */
    protected function unpackDateRange(?string $packed): array
    {
        [$from, $to] = $this->splitDateRange($packed);

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitDateRange(?string $packed): array
    {
        if ($packed === null) {
            return [null, null];
        }

        [$from, $to] = explode('|', $packed, 2);

        return [$from === '' ? null : $from, $to === '' ? null : $to];
    }

    protected function pickDate(?string $current, ?string $candidate, bool $earliest): ?string
    {
        if (!$candidate) {
            return $current;
        }

        if (!$current) {
            return $candidate;
        }

        $isEarlier = Carbon::parse($candidate)->lt(Carbon::parse($current));

        return $isEarlier === $earliest ? $candidate : $current;
    }
}
